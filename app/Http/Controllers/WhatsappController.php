<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Document;
use App\Models\MessageTemplate;
use App\Models\Memo;
use App\Models\WhatsappMessage;
use App\Services\ActivityLogger;
use App\Services\SecureAttachmentLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WhatsappController extends Controller
{
    public function index(): View
    {
        $messages = WhatsappMessage::query()
            ->with(['document', 'memo', 'creator', 'contact', 'messageTemplate'])
            ->latest()
            ->paginate(15);

        $summary = [
            'total' => WhatsappMessage::query()->count(),
            'opened' => WhatsappMessage::query()->where('status', 'opened')->count(),
            'with_documents' => WhatsappMessage::query()->whereNotNull('document_id')->count(),
            'with_memos' => WhatsappMessage::query()->whereNotNull('memo_id')->count(),
            'today' => WhatsappMessage::query()->whereDate('created_at', now()->toDateString())->count(),
        ];

        return view('whatsapp.index', compact('messages', 'summary'));
    }

    public function compose(Request $request): View
    {
        $document = null;
        $memo = null;

        if ($request->filled('document_id')) {
            $document = Document::query()
                ->with(['department', 'documentType', 'attachments'])
                ->findOrFail((int) $request->document_id);
        }

        if (!$document && $request->filled('memo_id')) {
            $memo = Memo::query()
                ->with(['department', 'attachments'])
                ->findOrFail((int) $request->memo_id);
        }

        $documents = Document::query()
            ->with(['department', 'documentType'])
            ->latest('id')
            ->limit(120)
            ->get();

        $memos = Memo::query()
            ->with(['department'])
            ->withCount('attachments')
            ->latest('id')
            ->limit(120)
            ->get();

        $defaults = $memo ? $this->defaultsForMemo($memo) : $this->defaultsForDocument($document);
        $composeSupport = $this->composeSupport($document, 'whatsapp', $memo);

        return view('whatsapp.compose', array_merge(compact('document', 'memo', 'documents', 'memos', 'defaults'), $composeSupport));
    }

    public function composeDocument(Document $document): View
    {
        $document->load(['department', 'documentType', 'attachments']);

        $documents = Document::query()
            ->with(['department', 'documentType'])
            ->latest('id')
            ->limit(120)
            ->get();

        $memos = Memo::query()
            ->with(['department'])
            ->withCount('attachments')
            ->latest('id')
            ->limit(120)
            ->get();

        $memo = null;
        $defaults = $this->defaultsForDocument($document);
        $composeSupport = $this->composeSupport($document, 'whatsapp', $memo);

        return view('whatsapp.compose', array_merge(compact('document', 'memo', 'documents', 'memos', 'defaults'), $composeSupport));
    }


    public function composeMemo(Memo $memo): View
    {
        $memo->load(['department', 'attachments']);

        $documents = Document::query()
            ->with(['department', 'documentType'])
            ->latest('id')
            ->limit(120)
            ->get();

        $memos = Memo::query()
            ->with(['department'])
            ->withCount('attachments')
            ->latest('id')
            ->limit(120)
            ->get();

        $document = null;
        $defaults = $this->defaultsForMemo($memo);
        $composeSupport = $this->composeSupport($document, 'whatsapp', $memo);

        return view('whatsapp.compose', array_merge(compact('document', 'memo', 'documents', 'memos', 'defaults'), $composeSupport));
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'memo_id' => ['nullable', 'integer', 'exists:memos,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'message_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'phone_number' => ['required', 'string', 'max:40'],
            'message_body' => ['required', 'string', 'max:4000'],
            'include_secure_attachment_link' => ['nullable', 'boolean'],
            'secure_link_expires_in' => ['nullable', 'in:1h,3h,12h,24h,3d,7d'],
            'secure_link_password' => ['nullable', 'string', 'max:100'],
        ]);

        $normalizedPhone = $this->normalizePhone((string) $validated['phone_number']);

        if ($normalizedPhone === null) {
            return back()
                ->withErrors(['phone_number' => 'رقم واتساب غير صحيح. اكتب الرقم بصيغة دولية مثل +965XXXXXXXX أو 965XXXXXXXX.'])
                ->withInput();
        }

        $document = null;
        $memo = null;
        if (!empty($validated['document_id'])) {
            $document = Document::query()
                ->with(['department', 'documentType', 'attachments'])
                ->findOrFail((int) $validated['document_id']);
        } elseif (!empty($validated['memo_id'])) {
            $memo = Memo::query()
                ->with(['department', 'attachments'])
                ->findOrFail((int) $validated['memo_id']);
        }

        $contact = null;
        if (!empty($validated['contact_id'])) {
            $contact = Contact::query()->find((int) $validated['contact_id']);
        }

        $template = null;
        if (!empty($validated['message_template_id'])) {
            $template = MessageTemplate::query()->find((int) $validated['message_template_id']);
        }

        $secureAttachmentLink = null;
        $messageBody = (string) $validated['message_body'];

        if (($document || $memo) && $request->boolean('include_secure_attachment_link')) {
            try {
                if ($memo) {
                    $secureAttachmentLink = app(SecureAttachmentLinkService::class)->createForMemo(
                        $memo,
                        $memo->attachments->pluck('id')->all(),
                        Auth::id(),
                        (string) ($validated['secure_link_expires_in'] ?? '24h'),
                        null,
                        $validated['secure_link_password'] ?? null,
                        'تم إنشاء الرابط من صفحة واتساب للمذكرة.'
                    );
                } else {
                    $secureAttachmentLink = app(SecureAttachmentLinkService::class)->createForDocument(
                        $document,
                        $document->attachments->pluck('id')->all(),
                        Auth::id(),
                        (string) ($validated['secure_link_expires_in'] ?? '24h'),
                        null,
                        $validated['secure_link_password'] ?? null,
                        'تم إنشاء الرابط من صفحة واتساب.'
                    );
                }

                $messageBody = app(SecureAttachmentLinkService::class)->appendLinkToBody($messageBody, $secureAttachmentLink);
            } catch (\Throwable $exception) {
                return back()
                    ->withErrors(['include_secure_attachment_link' => 'تعذر إنشاء رابط المرفقات الآمن: ' . $exception->getMessage()])
                    ->withInput();
            }
        }

        $whatsappUrl = 'https://wa.me/' . $normalizedPhone . '?text=' . rawurlencode($messageBody);

        $message = WhatsappMessage::create([
            'document_id' => $document?->id,
            'memo_id' => $memo?->id,
            'created_by' => Auth::id(),
            'contact_id' => $contact?->id,
            'message_template_id' => $template?->id,
            'recipient_name' => $validated['recipient_name'] ?? null,
            'phone_number' => $validated['phone_number'],
            'normalized_phone' => $normalizedPhone,
            'message_body' => $messageBody,
            'whatsapp_url' => $whatsappUrl,
            'status' => 'opened',
            'opened_at' => now(),
        ]);

        ActivityLogger::log(
            'whatsapp.opened',
            'تم فتح واتساب لإرسال رسالة' . ($document ? ' للكتاب رقم ' . $document->reference_number : ($memo ? ' للمذكرة رقم ' . $memo->memo_number : '')),
            $message,
            [
                'document_id' => $document?->id,
                'memo_id' => $memo?->id,
                'reference_number' => $document?->reference_number ?: $memo?->memo_number,
                'contact_id' => $contact?->id,
                'message_template_id' => $template?->id,
                'secure_attachment_link_id' => $secureAttachmentLink?->id,
                'phone' => $normalizedPhone,
            ]
        );

        return redirect()->away($whatsappUrl);
    }

    public function show(WhatsappMessage $whatsappMessage): View
    {
        $whatsappMessage->load(['document.department', 'document.documentType', 'memo.department', 'creator', 'contact', 'messageTemplate']);

        return view('whatsapp.show', compact('whatsappMessage'));
    }

    private function defaultsForDocument(?Document $document): array
    {
        if (!$document) {
            return [
                'message_body' => '',
            ];
        }

        $date = $document->reference_date ? $document->reference_date->format('d/m/Y') : '-';
        $department = $document->department?->name ?: '-';
        $type = $document->documentType?->name ?: '-';
        $attachmentsCount = $document->attachments?->count() ?? 0;

        $body = implode("\n", array_filter([
            'السلام عليكم ورحمة الله وبركاته،',
            '',
            'نرسل لكم بيانات الكتاب التالي:',
            '',
            'رقم الكتاب: ' . ($document->reference_number ?: '-'),
            'تاريخ الكتاب: ' . $date,
            'موضوع الكتاب: ' . ($document->subject ?: $document->title ?: '-'),
            'الإدارة: ' . $department,
            'نوع الكتاب: ' . $type,
            'المرسل: ' . ($document->sender ?: '-'),
            'المستلم: ' . ($document->receiver ?: '-'),
            'البوليصة الرئيسية: ' . ($document->main_policy_number ?: '-'),
            'البوليصة الفرعية: ' . ($document->sub_policy_number ?: '-'),
            '',
            'عدد المرفقات المسجلة في النظام: ' . $attachmentsCount,
            'ملاحظة: لا يتم إرفاق ملفات الكتاب تلقائيًا عبر رابط واتساب. يمكن إرسال المرفقات يدويًا من النظام عند الحاجة.',
            '',
            'مع التحية،',
        ], fn ($line) => $line !== null));

        return [
            'message_body' => $body,
        ];
    }


    private function defaultsForMemo(?Memo $memo): array
    {
        if (!$memo) {
            return [
                'message_body' => '',
            ];
        }

        $date = $memo->memo_date ? $memo->memo_date->format('d/m/Y') : '-';
        $department = $memo->department?->name ?: '-';
        $attachmentsCount = $memo->attachments?->count() ?? 0;

        $body = implode("\n", array_filter([
            'السلام عليكم ورحمة الله وبركاته،',
            '',
            'نرسل لكم بيانات المذكرة التالية:',
            '',
            'رقم المذكرة: ' . ($memo->memo_number ?: '-'),
            'تاريخ المذكرة: ' . $date,
            'موضوع المذكرة: ' . ($memo->subject ?: '-'),
            'الإدارة: ' . $department,
            'الواردة من: ' . ($memo->sender ?: '-'),
            'المستلم: ' . ($memo->receiver ?: '-'),
            '',
            'عدد المرفقات المسجلة في النظام: ' . $attachmentsCount,
            'ملاحظة: يمكن إضافة رابط مرفقات آمن داخل الرسالة من الخيار أدناه.',
            '',
            'مع التحية،',
        ], fn ($line) => $line !== null));

        return [
            'message_body' => $body,
        ];
    }

    private function composeSupport(?Document $document, string $channel, ?Memo $memo = null): array
    {
        $contacts = Contact::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $templates = MessageTemplate::query()
            ->active()
            ->forChannel($channel)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $contactPayload = $contacts->mapWithKeys(fn (Contact $contact) => [
            $contact->id => [
                'name' => $contact->name,
                'display_name' => $contact->display_name,
                'organization' => $contact->organization,
                'contact_person' => $contact->contact_person,
                'email' => $contact->email,
                'whatsapp_number' => $contact->whatsapp_number,
                'phone' => $contact->phone,
            ],
        ])->all();

        $templatePayload = $templates->mapWithKeys(fn (MessageTemplate $template) => [
            $template->id => [
                'name' => $template->name,
                'subject' => $template->subject_template ?: '',
                'body' => $template->body_template,
                'is_default' => $template->is_default,
            ],
        ])->all();

        $documentVariables = $this->templateVariables($document, $memo);

        return compact('contacts', 'templates', 'contactPayload', 'templatePayload', 'documentVariables');
    }


    private function templateVariables(?Document $document = null, ?Memo $memo = null): array
    {
        if ($memo) {
            $attachmentsCount = $memo->relationLoaded('attachments') ? $memo->attachments->count() : $memo->attachments()->count();
            $date = $memo->memo_date ? $memo->memo_date->format('d/m/Y') : '-';

            return [
                'document_number' => $memo->memo_number ?: '-',
                'reference_number' => $memo->memo_number ?: '-',
                'memo_number' => $memo->memo_number ?: '-',
                'document_date' => $date,
                'reference_date' => $date,
                'memo_date' => $date,
                'title' => $memo->subject ?: '-',
                'subject' => $memo->subject ?: '-',
                'description' => $memo->description ?: '-',
                'department' => $memo->department?->name ?: '-',
                'document_type' => 'مذكرة واردة',
                'sender' => $memo->sender ?: '-',
                'receiver' => $memo->receiver ?: '-',
                'main_policy_number' => '-',
                'sub_policy_number' => '-',
                'attachments_count' => (string) $attachmentsCount,
                'today' => now()->format('d/m/Y'),
                'system_name' => \App\Models\Setting::getValue('system_name', 'الأرشيف الإلكتروني'),
                'department_name' => \App\Models\Setting::getValue('system_department_name', 'الشحن والتأمين'),
                'share_link' => '-',
            ];
        }

        return MessageTemplate::variableValues($document);
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = $this->convertArabicDigits($phone);
        $phone = trim($phone);
        $phone = preg_replace('/[^0-9+]/', '', $phone) ?: '';

        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        $phone = preg_replace('/\D+/', '', $phone) ?: '';

        if (strlen($phone) === 8) {
            $phone = '965' . $phone;
        }

        if (strlen($phone) < 8 || strlen($phone) > 15) {
            return null;
        }

        return $phone;
    }

    private function convertArabicDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}

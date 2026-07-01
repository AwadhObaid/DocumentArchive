<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Document;
use App\Models\MessageTemplate;
use App\Models\WhatsappMessage;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WhatsappController extends Controller
{
    public function index(): View
    {
        $messages = WhatsappMessage::query()
            ->with(['document', 'creator', 'contact', 'messageTemplate'])
            ->latest()
            ->paginate(15);

        $summary = [
            'total' => WhatsappMessage::query()->count(),
            'opened' => WhatsappMessage::query()->where('status', 'opened')->count(),
            'with_documents' => WhatsappMessage::query()->whereNotNull('document_id')->count(),
            'today' => WhatsappMessage::query()->whereDate('created_at', now()->toDateString())->count(),
        ];

        return view('whatsapp.index', compact('messages', 'summary'));
    }

    public function compose(Request $request): View
    {
        $document = null;

        if ($request->filled('document_id')) {
            $document = Document::query()
                ->with(['department', 'documentType', 'attachments'])
                ->findOrFail((int) $request->document_id);
        }

        $documents = Document::query()
            ->with(['department', 'documentType'])
            ->latest('id')
            ->limit(120)
            ->get();

        $defaults = $this->defaultsForDocument($document);
        $composeSupport = $this->composeSupport($document, 'whatsapp');

        return view('whatsapp.compose', array_merge(compact('document', 'documents', 'defaults'), $composeSupport));
    }

    public function composeDocument(Document $document): View
    {
        $document->load(['department', 'documentType', 'attachments']);

        $documents = Document::query()
            ->with(['department', 'documentType'])
            ->latest('id')
            ->limit(120)
            ->get();

        $defaults = $this->defaultsForDocument($document);
        $composeSupport = $this->composeSupport($document, 'whatsapp');

        return view('whatsapp.compose', array_merge(compact('document', 'documents', 'defaults'), $composeSupport));
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'message_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'phone_number' => ['required', 'string', 'max:40'],
            'message_body' => ['required', 'string', 'max:4000'],
        ]);

        $normalizedPhone = $this->normalizePhone((string) $validated['phone_number']);

        if ($normalizedPhone === null) {
            return back()
                ->withErrors(['phone_number' => 'رقم واتساب غير صحيح. اكتب الرقم بصيغة دولية مثل +965XXXXXXXX أو 965XXXXXXXX.'])
                ->withInput();
        }

        $document = null;
        if (!empty($validated['document_id'])) {
            $document = Document::query()
                ->with(['department', 'documentType', 'attachments'])
                ->findOrFail((int) $validated['document_id']);
        }

        $contact = null;
        if (!empty($validated['contact_id'])) {
            $contact = Contact::query()->find((int) $validated['contact_id']);
        }

        $template = null;
        if (!empty($validated['message_template_id'])) {
            $template = MessageTemplate::query()->find((int) $validated['message_template_id']);
        }

        $whatsappUrl = 'https://wa.me/' . $normalizedPhone . '?text=' . rawurlencode((string) $validated['message_body']);

        $message = WhatsappMessage::create([
            'document_id' => $document?->id,
            'created_by' => Auth::id(),
            'contact_id' => $contact?->id,
            'message_template_id' => $template?->id,
            'recipient_name' => $validated['recipient_name'] ?? null,
            'phone_number' => $validated['phone_number'],
            'normalized_phone' => $normalizedPhone,
            'message_body' => $validated['message_body'],
            'whatsapp_url' => $whatsappUrl,
            'status' => 'opened',
            'opened_at' => now(),
        ]);

        ActivityLogger::log(
            'whatsapp.opened',
            'تم فتح واتساب لإرسال رسالة' . ($document ? ' للكتاب رقم ' . $document->reference_number : ''),
            $message,
            [
                'document_id' => $document?->id,
                'reference_number' => $document?->reference_number,
                'contact_id' => $contact?->id,
                'message_template_id' => $template?->id,
                'phone' => $normalizedPhone,
            ]
        );

        return redirect()->away($whatsappUrl);
    }

    public function show(WhatsappMessage $whatsappMessage): View
    {
        $whatsappMessage->load(['document.department', 'document.documentType', 'creator', 'contact', 'messageTemplate']);

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

    private function composeSupport(?Document $document, string $channel): array
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

        $documentVariables = MessageTemplate::variableValues($document);

        return compact('contacts', 'templates', 'contactPayload', 'templatePayload', 'documentVariables');
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

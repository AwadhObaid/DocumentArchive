<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\EmailMessage;
use App\Models\MessageTemplate;
use App\Services\ActivityLogger;
use App\Services\SecureAttachmentLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class EmailController extends Controller
{
    private int $maxTotalAttachmentBytes = 26214400; // 25 MB

    public function index(): View
    {
        $messages = EmailMessage::query()
            ->with(['document', 'creator', 'contact', 'messageTemplate'])
            ->latest()
            ->paginate(15);

        $summary = [
            'total' => EmailMessage::query()->count(),
            'sent' => EmailMessage::query()->where('status', 'sent')->count(),
            'failed' => EmailMessage::query()->where('status', 'failed')->count(),
            'with_documents' => EmailMessage::query()->whereNotNull('document_id')->count(),
        ];

        return view('emails.index', compact('messages', 'summary'));
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
        $composeSupport = $this->composeSupport($document, 'email');

        return view('emails.compose', array_merge(compact('document', 'documents', 'defaults'), $composeSupport));
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
        $composeSupport = $this->composeSupport($document, 'email');

        return view('emails.compose', array_merge(compact('document', 'documents', 'defaults'), $composeSupport));
    }

    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'message_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'to' => ['required', 'string', 'max:2000'],
            'cc' => ['nullable', 'string', 'max:2000'],
            'bcc' => ['nullable', 'string', 'max:2000'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'attachment_ids' => ['nullable', 'array'],
            'attachment_ids.*' => ['integer', 'exists:document_attachments,id'],
            'include_secure_attachment_link' => ['nullable', 'boolean'],
            'secure_link_expires_in' => ['nullable', 'in:1h,3h,12h,24h,3d,7d'],
            'secure_link_password' => ['nullable', 'string', 'max:100'],
        ]);

        $to = $this->parseRecipients((string) $validated['to']);
        $cc = $this->parseRecipients((string) ($validated['cc'] ?? ''));
        $bcc = $this->parseRecipients((string) ($validated['bcc'] ?? ''));

        $recipientErrors = $this->validateRecipients($to, 'إلى')
            + $this->validateRecipients($cc, 'CC')
            + $this->validateRecipients($bcc, 'BCC');

        if ($to === []) {
            $recipientErrors['to'] = 'يجب إدخال بريد إلكتروني واحد على الأقل في خانة إلى.';
        }

        if ($recipientErrors !== []) {
            return back()->withErrors($recipientErrors)->withInput();
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

        $selectedAttachmentIds = array_values(array_unique(array_map('intval', (array) ($validated['attachment_ids'] ?? []))));
        $attachments = $this->resolveAttachments($selectedAttachmentIds, $document);

        $missingFiles = [];
        $totalSize = 0;
        foreach ($attachments as $attachment) {
            if (!$attachment->existsOnDisk()) {
                $missingFiles[] = $attachment->original_name ?: $attachment->file_name;
                continue;
            }
            $totalSize += (int) $attachment->file_size;
        }

        if ($missingFiles !== []) {
            return back()
                ->withErrors(['attachment_ids' => 'بعض المرفقات غير موجودة على التخزين: ' . implode('، ', $missingFiles)])
                ->withInput();
        }

        if ($totalSize > $this->maxTotalAttachmentBytes) {
            return back()
                ->withErrors(['attachment_ids' => 'إجمالي حجم المرفقات أكبر من الحد المسموح 25 MB. اختر مرفقات أقل ثم أعد المحاولة.'])
                ->withInput();
        }

        $secureAttachmentLink = null;
        $body = (string) $validated['body'];

        if ($document && $request->boolean('include_secure_attachment_link')) {
            try {
                $secureAttachmentLink = app(SecureAttachmentLinkService::class)->createForDocument(
                    $document,
                    $selectedAttachmentIds !== [] ? $selectedAttachmentIds : $document->attachments->pluck('id')->all(),
                    Auth::id(),
                    (string) ($validated['secure_link_expires_in'] ?? '24h'),
                    null,
                    $validated['secure_link_password'] ?? null,
                    'تم إنشاء الرابط من صفحة البريد الإلكتروني.'
                );

                $body = app(SecureAttachmentLinkService::class)->appendLinkToBody($body, $secureAttachmentLink);
            } catch (\Throwable $exception) {
                return back()
                    ->withErrors(['include_secure_attachment_link' => 'تعذر إنشاء رابط المرفقات الآمن: ' . $exception->getMessage()])
                    ->withInput();
            }
        }

        $emailMessage = EmailMessage::create([
            'document_id' => $document?->id,
            'created_by' => Auth::id(),
            'contact_id' => $contact?->id,
            'message_template_id' => $template?->id,
            'to_recipients' => $to,
            'cc_recipients' => $cc,
            'bcc_recipients' => $bcc,
            'subject' => $validated['subject'],
            'body' => $body,
            'attachment_ids' => $attachments->pluck('id')->values()->all(),
            'attachment_names' => $attachments->map(fn ($attachment) => $attachment->original_name ?: $attachment->file_name)->values()->all(),
            'attachments_count' => $attachments->count(),
            'total_attachment_size' => $totalSize,
            'status' => 'pending',
        ]);

        try {
            Mail::send('emails.mail.document', [
                'document' => $document,
                'bodyText' => $body,
                'emailMessage' => $emailMessage,
            ], function ($message) use ($to, $cc, $bcc, $validated, $attachments) {
                $message->to($to)->subject($validated['subject']);

                if ($cc !== []) {
                    $message->cc($cc);
                }

                if ($bcc !== []) {
                    $message->bcc($bcc);
                }

                foreach ($attachments as $attachment) {
                    $disk = Storage::disk($attachment->disk ?: 'local');
                    $fileName = $attachment->original_name ?: $attachment->file_name;
                    $mimeType = $attachment->mime_type ?: 'application/octet-stream';

                    if (method_exists($disk, 'path')) {
                        $message->attach($disk->path($attachment->file_path), [
                            'as' => $fileName,
                            'mime' => $mimeType,
                        ]);
                    } else {
                        $message->attachData($disk->get($attachment->file_path), $fileName, [
                            'mime' => $mimeType,
                        ]);
                    }
                }
            });

            $emailMessage->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);

            ActivityLogger::log(
                'email.sent',
                'تم إرسال بريد إلكتروني' . ($document ? ' للكتاب رقم ' . $document->reference_number : ''),
                $emailMessage,
                [
                    'document_id' => $document?->id,
                    'reference_number' => $document?->reference_number,
                    'contact_id' => $contact?->id,
                    'message_template_id' => $template?->id,
                    'to' => $to,
                    'attachments_count' => $attachments->count(),
                    'secure_attachment_link_id' => $secureAttachmentLink?->id,
                ]
            );

            return redirect()
                ->route('emails.show', $emailMessage)
                ->with('success', 'تم إرسال البريد الإلكتروني وتسجيل العملية بنجاح.');
        } catch (\Throwable $exception) {
            report($exception);

            $emailMessage->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            ActivityLogger::log(
                'email.failed',
                'فشل إرسال بريد إلكتروني' . ($document ? ' للكتاب رقم ' . $document->reference_number : ''),
                $emailMessage,
                [
                    'document_id' => $document?->id,
                    'reference_number' => $document?->reference_number,
                    'contact_id' => $contact?->id,
                    'message_template_id' => $template?->id,
                    'error' => $exception->getMessage(),
                ]
            );

            return redirect()
                ->route('emails.show', $emailMessage)
                ->withErrors(['email' => 'فشل إرسال البريد. راجع إعدادات البريد أو تفاصيل الخطأ في سجل الإرسال.']);
        }
    }

    public function show(EmailMessage $emailMessage): View
    {
        $emailMessage->load(['document.department', 'document.documentType', 'creator', 'contact', 'messageTemplate']);

        return view('emails.show', compact('emailMessage'));
    }

    private function defaultsForDocument(?Document $document): array
    {
        if (!$document) {
            return [
                'subject' => '',
                'body' => '',
            ];
        }

        $date = $document->reference_date ? $document->reference_date->format('d/m/Y') : '-';
        $department = $document->department?->name ?: '-';
        $type = $document->documentType?->name ?: '-';

        $subject = 'إرسال كتاب رقم ' . ($document->reference_number ?: '-') . ' - ' . ($document->subject ?: $document->title ?: '');

        $body = implode("\n", array_filter([
            'السلام عليكم ورحمة الله وبركاته،',
            '',
            'مرفق لكم بيانات ومرفقات الكتاب التالي:',
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
            'مع التحية،',
        ], fn ($line) => $line !== null));

        return [
            'subject' => $subject,
            'body' => $body,
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

    private function parseRecipients(string $value): array
    {
        $parts = preg_split('/[;,،\s]+/u', $value) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn ($email) => $email !== '')));
    }

    private function validateRecipients(array $emails, string $label): array
    {
        $errors = [];

        foreach ($emails as $email) {
            $validator = Validator::make(['email' => $email], ['email' => ['required', 'email:rfc']]);

            if ($validator->fails()) {
                $errors[$label . '_' . md5($email)] = 'البريد الإلكتروني غير صحيح في خانة ' . $label . ': ' . $email;
            }
        }

        return $errors;
    }

    private function resolveAttachments(array $selectedAttachmentIds, ?Document $document)
    {
        if ($selectedAttachmentIds === []) {
            return collect();
        }

        $query = DocumentAttachment::query()
            ->whereIn('id', $selectedAttachmentIds);

        if ($document) {
            $query->where('document_id', $document->id);
        }

        return $query->get();
    }
}

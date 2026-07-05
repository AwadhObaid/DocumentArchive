@extends('layouts.app')

@section('title', 'إرسال بريد إلكتروني')
@section('page_title', 'إرسال بريد إلكتروني')
@section('page_subtitle', 'اختر جهة اتصال وقالبًا وكتابًا لإرسال بياناته مع المرفقات.')

@section('content')
@php
    $memo = $memo ?? null;
    $memos = $memos ?? collect();
    $selectedDocumentId = old('document_id', $document?->id);
    $selectedMemoId = old('memo_id', $memo?->id);
    $selectedContactId = old('contact_id');
    $selectedTemplateId = old('message_template_id');
    $selectedAttachments = old('attachment_ids');
    if ($selectedAttachments === null && $document) {
        $selectedAttachments = $document->attachments->pluck('id')->map(fn ($id) => (string) $id)->all();
    }
    if ($selectedAttachments === null && $memo) {
        $selectedAttachments = $memo->attachments->pluck('id')->map(fn ($id) => (string) $id)->all();
    }
    $selectedAttachments = array_map('strval', (array) $selectedAttachments);
@endphp

<div class="email-page ct-enhanced-compose" data-compose-channel="email">
    <div class="page-header">
        <div>
            <h1>إرسال بريد إلكتروني</h1>
            <p>يمكنك اختيار جهة اتصال وقالب رسالة لتجهيز البريد تلقائيًا من بيانات الكتاب.</p>
        </div>
        <div class="page-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <a href="{{ route('emails.index') }}" class="btn btn-light">سجل البريد</a>
            @if(auth()->user()?->hasPermission('contacts.view'))
                <a href="{{ route('contacts.index') }}" class="btn btn-light">جهات الاتصال</a>
            @endif
            @if(auth()->user()?->hasPermission('message_templates.view'))
                <a href="{{ route('message-templates.index') }}" class="btn btn-light">قوالب الرسائل</a>
            @endif
            @if($document)
                <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary">عرض الكتاب</a>
            @endif
            @if($memo)
                <a href="{{ route('memos.show', $memo) }}" class="btn btn-secondary">عرض المذكرة</a>
            @endif
        </div>
    </div>

    <div class="email-compose-layout">
        <form class="email-panel email-form" method="POST" action="{{ route('emails.send') }}"
              data-confirm-title="تأكيد إرسال البريد الإلكتروني"
              data-confirm="سيتم إرسال الرسالة إلى العناوين المحددة مع بيانات الكتاب والمرفقات المختارة. هل تريد المتابعة؟"
              data-confirm-extra="راجع البريد الإلكتروني والمرفقات قبل الإرسال، لأن العملية سيتم تسجيلها في سجل البريد."
              data-confirm-yes="نعم، إرسال الآن"
              data-confirm-no="مراجعة قبل الإرسال">
            @csrf
            <input type="hidden" name="document_id" value="{{ $selectedDocumentId }}">
            <input type="hidden" name="memo_id" value="{{ $selectedMemoId }}">

            <h2>بيانات الرسالة</h2>
            <div class="email-grid">
                <div class="form-group full">
                    <label>اختيار كتاب</label>
                    <select onchange="if(this.value){ window.location='{{ route('emails.compose') }}?document_id=' + this.value; } else { window.location='{{ route('emails.compose') }}'; }">
                        <option value="">رسالة عامة بدون كتاب</option>
                        @foreach($documents as $doc)
                            <option value="{{ $doc->id }}" @selected((int) $selectedDocumentId === (int) $doc->id)>
                                {{ $doc->reference_number }} - {{ \Illuminate\Support\Str::limit($doc->subject ?: $doc->title ?: 'بدون موضوع', 80) }}
                            </option>
                        @endforeach
                    </select>
                    <div class="email-help">عند اختيار كتاب، سيتم تجهيز الموضوع ونص الرسالة والمرفقات تلقائيًا.</div>
                </div>

                @if($memo)
                    <div class="form-group full">
                        <label>المذكرة المختارة</label>
                        <input type="text" value="{{ $memo->memo_number }} - {{ \Illuminate\Support\Str::limit($memo->subject ?: 'بدون موضوع', 90) }}" readonly>
                        <div class="email-help">تم فتح هذه الصفحة من جدول المذكرات؛ سيتم إرسال بيانات ومرفقات هذه المذكرة.</div>
                    </div>
                @endif

                <div class="form-group full">
                    <label>جهة الاتصال</label>
                    <select name="contact_id" id="emailContactSelect" data-contact-select>
                        <option value="">بدون جهة محفوظة</option>
                        @foreach($contacts as $contact)
                            <option value="{{ $contact->id }}" @selected((string)$selectedContactId === (string)$contact->id)>
                                {{ $contact->display_name }}{{ $contact->email ? ' — ' . $contact->email : '' }}
                            </option>
                        @endforeach
                    </select>
                    <div class="email-help">عند اختيار جهة محفوظة سيتم تعبئة البريد تلقائيًا إن وجد.</div>
                </div>

                <div class="form-group full">
                    <label>قالب الرسالة</label>
                    <select name="message_template_id" id="emailTemplateSelect" data-template-select>
                        <option value="">بدون قالب</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" @selected((string)$selectedTemplateId === (string)$template->id)>
                                {{ $template->name }}{{ $template->is_default ? ' — افتراضي' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <div class="email-help">القالب يستبدل المتغيرات مثل <code>{document_number}</code> و <code>{subject}</code> تلقائيًا.</div>
                </div>

                <div class="form-group full">
                    <label>إلى <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="to" data-email-to value="{{ old('to') }}" required placeholder="example@domain.com ويمكن فصل أكثر من بريد بفاصلة">
                    <div class="email-help">يمكن كتابة أكثر من بريد بفاصلة أو فاصلة منقوطة أو سطر جديد.</div>
                </div>

                <div class="form-group">
                    <label>CC</label>
                    <input type="text" name="cc" value="{{ old('cc') }}" placeholder="اختياري">
                </div>

                <div class="form-group">
                    <label>BCC</label>
                    <input type="text" name="bcc" value="{{ old('bcc') }}" placeholder="اختياري">
                </div>

                <div class="form-group full">
                    <label>الموضوع <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="subject" data-email-subject value="{{ old('subject', $defaults['subject'] ?? '') }}" required>
                </div>

                <div class="form-group full">
                    <label>نص الرسالة <span style="color:#dc2626;">*</span></label>
                    <textarea name="body" data-email-body required>{{ old('body', $defaults['body'] ?? '') }}</textarea>
                </div>
            </div>

            @if($document && $document->attachments->count())
                <div class="form-group full" style="margin-top:16px;">
                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                        <label style="margin:0;">مرفقات الكتاب</label>
                        <button type="button" class="btn btn-sm btn-light" onclick="document.querySelectorAll('[data-email-attachment]').forEach(el => el.checked = true)">تحديد الكل</button>
                    </div>
                    <div class="email-attachments-list">
                        @foreach($document->attachments as $attachment)
                            @php
                                $exists = method_exists($attachment, 'existsOnDisk') ? $attachment->existsOnDisk() : false;
                                $fileName = $attachment->original_name ?: $attachment->file_name;
                            @endphp
                            <label class="email-attachment-item">
                                <input data-email-attachment type="checkbox" name="attachment_ids[]" value="{{ $attachment->id }}" @checked(in_array((string) $attachment->id, $selectedAttachments, true)) @disabled(!$exists)>
                                <span>
                                    <strong>{{ $fileName }}</strong>
                                    <small>{{ $attachment->file_size_for_humans }} — {{ $exists ? 'موجود على التخزين' : 'مفقود من التخزين' }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="email-help">الحد الأقصى لإجمالي المرفقات في هذه المرحلة 25 MB.</div>
                </div>
            @elseif($document)
                <div class="email-note-box" style="margin-top:16px;">هذا الكتاب لا يحتوي على مرفقات، سيتم إرسال بياناته فقط.</div>
            @endif

            @if($memo && $memo->attachments->count())
                <div class="form-group full" style="margin-top:16px;">
                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                        <label style="margin:0;">مرفقات المذكرة</label>
                        <button type="button" class="btn btn-sm btn-light" onclick="document.querySelectorAll('[data-email-attachment]').forEach(el => el.checked = true)">تحديد الكل</button>
                    </div>
                    <div class="email-attachments-list">
                        @foreach($memo->attachments as $attachment)
                            @php
                                $exists = method_exists($attachment, 'existsOnDisk') ? $attachment->existsOnDisk() : false;
                                $fileName = $attachment->original_name ?: $attachment->file_name;
                            @endphp
                            <label class="email-attachment-item">
                                <input data-email-attachment type="checkbox" name="attachment_ids[]" value="{{ $attachment->id }}" @checked(in_array((string) $attachment->id, $selectedAttachments, true)) @disabled(!$exists)>
                                <span>
                                    <strong>{{ $fileName }}</strong>
                                    <small>{{ $attachment->file_size_for_humans }} — {{ $exists ? 'موجود على التخزين' : 'مفقود من التخزين' }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="email-help">الحد الأقصى لإجمالي المرفقات في هذه المرحلة 25 MB.</div>
                </div>
            @elseif($memo)
                <div class="email-note-box" style="margin-top:16px;">هذه المذكرة لا تحتوي على مرفقات، سيتم إرسال بياناتها فقط.</div>
            @endif


            @if(($document && $document->attachments->count()) || ($memo && $memo->attachments->count()))
                <div class="secure-link-compose-box">
                    <div class="secure-title">🔗 رابط مرفقات آمن</div>
                    <label class="inline-check">
                        <input type="checkbox" name="include_secure_attachment_link" value="1" @checked(old('include_secure_attachment_link'))>
                        <span>إضافة رابط آمن مؤقت للمرفقات داخل نص البريد</span>
                    </label>
                    <div class="secure-link-options">
                        <div>
                            <label>مدة صلاحية الرابط</label>
                            <select name="secure_link_expires_in">
                                <option value="1h" @selected(old('secure_link_expires_in') === '1h')>ساعة واحدة</option>
                                <option value="3h" @selected(old('secure_link_expires_in') === '3h')>3 ساعات</option>
                                <option value="12h" @selected(old('secure_link_expires_in') === '12h')>12 ساعة</option>
                                <option value="24h" @selected(old('secure_link_expires_in', '24h') === '24h')>24 ساعة</option>
                                <option value="3d" @selected(old('secure_link_expires_in') === '3d')>3 أيام</option>
                                <option value="7d" @selected(old('secure_link_expires_in') === '7d')>7 أيام</option>
                            </select>
                        </div>
                        <div>
                            <label>كلمة مرور للرابط</label>
                            <input type="text" name="secure_link_password" value="{{ old('secure_link_password') }}" placeholder="اختياري">
                            <small>عند تعبئة كلمة المرور، أرسلها للمستلم بطريقة منفصلة.</small>
                        </div>
                    </div>
                    @error('include_secure_attachment_link')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            @endif

            <div class="email-actions-row">
                <button type="submit" class="btn btn-primary">إرسال البريد الآن</button>
                <a href="{{ route('emails.index') }}" class="btn btn-light">إلغاء</a>
            </div>
        </form>

        <aside class="email-document-panel">
            @if($document)
                <h2>بيانات الكتاب المختار</h2>
                <div class="email-document-meta">
                    <div><span>رقم الكتاب</span><strong>{{ $document->reference_number }}</strong></div>
                    <div><span>التاريخ</span><strong>{{ optional($document->reference_date)->format('d/m/Y') ?: '-' }}</strong></div>
                    <div><span>الموضوع</span><strong>{{ \Illuminate\Support\Str::limit($document->subject ?: $document->title ?: '-', 70) }}</strong></div>
                    <div><span>الإدارة</span><strong>{{ $document->department?->name ?? '-' }}</strong></div>
                    <div><span>النوع</span><strong>{{ $document->documentType?->name ?? '-' }}</strong></div>
                    <div><span>المرفقات</span><strong>{{ $document->attachments->count() }}</strong></div>
                </div>
                <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary">فتح صفحة الكتاب</a>
            @elseif($memo)
                <h2>بيانات المذكرة المختارة</h2>
                <div class="email-document-meta">
                    <div><span>رقم المذكرة</span><strong>{{ $memo->memo_number }}</strong></div>
                    <div><span>التاريخ</span><strong>{{ optional($memo->memo_date)->format('d/m/Y') ?: '-' }}</strong></div>
                    <div><span>الموضوع</span><strong>{{ \Illuminate\Support\Str::limit($memo->subject ?: '-', 70) }}</strong></div>
                    <div><span>الإدارة</span><strong>{{ $memo->department?->name ?? '-' }}</strong></div>
                    <div><span>المرفقات</span><strong>{{ $memo->attachments->count() }}</strong></div>
                </div>
                <a href="{{ route('memos.show', $memo) }}" class="btn btn-secondary">فتح صفحة المذكرة</a>
            @else
                <h2>رسالة عامة</h2>
                <div class="email-note-box">لم يتم اختيار كتاب أو مذكرة. يمكنك إرسال رسالة بريدية عامة من النظام.</div>
            @endif

            <div class="email-note-box" style="margin-top:14px;">
                <strong>تنبيه:</strong> إذا كان إعداد البريد في ملف <code>.env</code> مضبوطًا على <code>MAIL_MAILER=log</code> فسيتم تسجيل الرسالة في ملف السجل بدل إرسالها فعليًا، وهذا مناسب للتجربة الأولى.
            </div>
        </aside>
    </div>
</div>

<script type="application/json" id="email-contact-payload">@json($contactPayload)</script>
<script type="application/json" id="email-template-payload">@json($templatePayload)</script>
<script type="application/json" id="email-document-variables">@json($documentVariables)</script>
<script>
(function() {
    const contacts = JSON.parse(document.getElementById('email-contact-payload').textContent || '{}');
    const templates = JSON.parse(document.getElementById('email-template-payload').textContent || '{}');
    const documentVars = JSON.parse(document.getElementById('email-document-variables').textContent || '{}');
    const contactSelect = document.querySelector('[data-contact-select]');
    const templateSelect = document.querySelector('[data-template-select]');
    const toInput = document.querySelector('[data-email-to]');
    const subjectInput = document.querySelector('[data-email-subject]');
    const bodyInput = document.querySelector('[data-email-body]');

    function vars() {
        const contact = contacts[contactSelect?.value || ''] || {};
        return Object.assign({}, documentVars, {
            contact_name: contact.name || '-',
            contact_person: contact.contact_person || '-',
            contact_organization: contact.organization || '-',
            contact_email: contact.email || '-',
            contact_whatsapp: contact.whatsapp_number || '-'
        });
    }

    function render(text) {
        const values = vars();
        return (text || '').replace(/\{([a-zA-Z0-9_]+)\}/g, function(match, key) {
            return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : match;
        });
    }

    contactSelect?.addEventListener('change', function() {
        const contact = contacts[this.value] || {};
        if (contact.email) toInput.value = contact.email;
        applyTemplate(false);
    });

    function applyTemplate(force = true) {
        const template = templates[templateSelect?.value || ''];
        if (!template) return;
        if (force || !subjectInput.value.trim()) subjectInput.value = render(template.subject || '');
        if (force || !bodyInput.value.trim()) bodyInput.value = render(template.body || '');
    }

    templateSelect?.addEventListener('change', function() { applyTemplate(true); });
})();
</script>
@endsection

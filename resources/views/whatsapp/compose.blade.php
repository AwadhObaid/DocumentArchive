@extends('layouts.app')

@section('title', 'إرسال واتساب')
@section('page_title', 'إرسال واتساب')
@section('page_subtitle', 'اختر جهة اتصال وقالبًا وجهّز رسالة واتساب من بيانات الكتاب.')

@section('content')
@php
    $selectedDocumentId = old('document_id', $document?->id);
    $selectedContactId = old('contact_id');
    $selectedTemplateId = old('message_template_id');
@endphp

<div class="whatsapp-page ct-enhanced-compose" data-compose-channel="whatsapp">
    <div class="page-header">
        <div>
            <h1>إرسال واتساب</h1>
            <p>سيتم تجهيز الرسالة وفتح WhatsApp Web أو تطبيق واتساب بالرقم والنص المحددين.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('whatsapp.index') }}" class="btn btn-light">سجل واتساب</a>
            @if(auth()->user()?->hasPermission('contacts.view'))
                <a href="{{ route('contacts.index') }}" class="btn btn-light">جهات الاتصال</a>
            @endif
            @if(auth()->user()?->hasPermission('message_templates.view'))
                <a href="{{ route('message-templates.index') }}" class="btn btn-light">قوالب الرسائل</a>
            @endif
            @if($document)
                <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary">عرض الكتاب</a>
            @endif
        </div>
    </div>

    <div class="whatsapp-compose-layout">
        <form class="whatsapp-panel" method="POST" action="{{ route('whatsapp.send') }}"
              data-confirm-title="تأكيد فتح واتساب"
              data-confirm="سيتم فتح واتساب بالرقم والنص المحددين، وسيتم تسجيل العملية في سجل واتساب. هل تريد المتابعة؟"
              data-confirm-extra="راجع رقم المستلم ونص الرسالة قبل المتابعة."
              data-confirm-yes="نعم، افتح واتساب"
              data-confirm-no="مراجعة قبل الفتح">
            @csrf
            <input type="hidden" name="document_id" value="{{ $selectedDocumentId }}">

            <h2>بيانات الرسالة</h2>
            <div class="whatsapp-grid">
                <div class="form-group full">
                    <label>اختيار كتاب</label>
                    <select onchange="if(this.value){ window.location='{{ route('whatsapp.compose') }}?document_id=' + this.value; } else { window.location='{{ route('whatsapp.compose') }}'; }">
                        <option value="">رسالة عامة بدون كتاب</option>
                        @foreach($documents as $doc)
                            <option value="{{ $doc->id }}" @selected((int) $selectedDocumentId === (int) $doc->id)>
                                {{ $doc->reference_number }} - {{ \Illuminate\Support\Str::limit($doc->subject ?: $doc->title ?: 'بدون موضوع', 80) }}
                            </option>
                        @endforeach
                    </select>
                    <div class="whatsapp-help">عند اختيار كتاب، سيتم تجهيز نص الرسالة تلقائيًا من بياناته.</div>
                </div>

                <div class="form-group full">
                    <label>جهة الاتصال</label>
                    <select name="contact_id" data-contact-select>
                        <option value="">بدون جهة محفوظة</option>
                        @foreach($contacts as $contact)
                            <option value="{{ $contact->id }}" @selected((string)$selectedContactId === (string)$contact->id)>
                                {{ $contact->display_name }}{{ $contact->whatsapp_number ? ' — ' . $contact->whatsapp_number : '' }}
                            </option>
                        @endforeach
                    </select>
                    <div class="whatsapp-help">عند اختيار جهة محفوظة سيتم تعبئة الاسم ورقم واتساب إن وجد.</div>
                </div>

                <div class="form-group full">
                    <label>قالب الرسالة</label>
                    <select name="message_template_id" data-template-select>
                        <option value="">بدون قالب</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}" @selected((string)$selectedTemplateId === (string)$template->id)>
                                {{ $template->name }}{{ $template->is_default ? ' — افتراضي' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <div class="whatsapp-help">القالب يستبدل متغيرات الكتاب والجهة تلقائيًا.</div>
                </div>

                <div class="form-group">
                    <label>اسم المستلم</label>
                    <input type="text" name="recipient_name" data-whatsapp-recipient value="{{ old('recipient_name') }}" placeholder="اختياري">
                </div>

                <div class="form-group">
                    <label>رقم واتساب <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="phone_number" data-whatsapp-phone value="{{ old('phone_number') }}" required placeholder="+965XXXXXXXX أو الرقم الدولي">
                    <div class="whatsapp-help">اكتب الرقم بصيغة دولية. إذا كتبت رقمًا محليًا من 8 أرقام سيتم إضافة 965 تلقائيًا.</div>
                </div>

                <div class="form-group full">
                    <label>نص رسالة واتساب <span style="color:#dc2626;">*</span></label>
                    <textarea name="message_body" data-whatsapp-body required>{{ old('message_body', $defaults['message_body'] ?? '') }}</textarea>
                    <div class="whatsapp-help">يمكنك تعديل النص قبل فتح واتساب. سيتم ترميز الرسالة تلقائيًا داخل رابط واتساب.</div>
                </div>
            </div>

            <div class="whatsapp-actions-row" style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">فتح واتساب الآن</button>
                <a href="{{ route('whatsapp.index') }}" class="btn btn-light">إلغاء</a>
            </div>
        </form>

        <aside class="whatsapp-document-panel">
            @if($document)
                <h2>بيانات الكتاب المختار</h2>
                <div class="whatsapp-document-meta">
                    <div><span>رقم الكتاب</span><strong>{{ $document->reference_number }}</strong></div>
                    <div><span>التاريخ</span><strong>{{ optional($document->reference_date)->format('d/m/Y') ?: '-' }}</strong></div>
                    <div><span>الموضوع</span><strong>{{ \Illuminate\Support\Str::limit($document->subject ?: $document->title ?: '-', 70) }}</strong></div>
                    <div><span>الإدارة</span><strong>{{ $document->department?->name ?? '-' }}</strong></div>
                    <div><span>النوع</span><strong>{{ $document->documentType?->name ?? '-' }}</strong></div>
                    <div><span>المرفقات</span><strong>{{ $document->attachments->count() }}</strong></div>
                </div>
                <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary">فتح صفحة الكتاب</a>
            @else
                <h2>رسالة عامة</h2>
                <div class="whatsapp-note-box">لم يتم اختيار كتاب. يمكنك تجهيز رسالة واتساب عامة، أو اختيار كتاب من القائمة لتعبئة بياناته تلقائيًا.</div>
            @endif

            <div class="whatsapp-warning-box" style="margin-top:14px;">
                <strong>ملاحظة:</strong> لا يمكن إرفاق الملفات تلقائيًا عبر رابط واتساب العادي. هذه المرحلة مخصصة لتجهيز النص وفتح المحادثة بسرعة، ويمكن إرسال المرفقات يدويًا من جهاز المستخدم.
            </div>
        </aside>
    </div>
</div>

<script type="application/json" id="whatsapp-contact-payload">@json($contactPayload)</script>
<script type="application/json" id="whatsapp-template-payload">@json($templatePayload)</script>
<script type="application/json" id="whatsapp-document-variables">@json($documentVariables)</script>
<script>
(function() {
    const contacts = JSON.parse(document.getElementById('whatsapp-contact-payload').textContent || '{}');
    const templates = JSON.parse(document.getElementById('whatsapp-template-payload').textContent || '{}');
    const documentVars = JSON.parse(document.getElementById('whatsapp-document-variables').textContent || '{}');
    const contactSelect = document.querySelector('[data-contact-select]');
    const templateSelect = document.querySelector('[data-template-select]');
    const recipientInput = document.querySelector('[data-whatsapp-recipient]');
    const phoneInput = document.querySelector('[data-whatsapp-phone]');
    const bodyInput = document.querySelector('[data-whatsapp-body]');

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
        if (contact.name) recipientInput.value = contact.contact_person || contact.name;
        if (contact.whatsapp_number) phoneInput.value = contact.whatsapp_number;
        applyTemplate(false);
    });

    function applyTemplate(force = true) {
        const template = templates[templateSelect?.value || ''];
        if (!template) return;
        if (force || !bodyInput.value.trim()) bodyInput.value = render(template.body || '');
    }

    templateSelect?.addEventListener('change', function() { applyTemplate(true); });
})();
</script>
@endsection

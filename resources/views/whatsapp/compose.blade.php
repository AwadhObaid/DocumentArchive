@extends('layouts.app')

@section('title', 'إرسال واتساب')
@section('page_title', 'إرسال واتساب')
@section('page_subtitle', 'اختر كتابًا وجهّز رسالة واتساب من بياناته.')

@section('content')
@php
    $selectedDocumentId = old('document_id', $document?->id);
@endphp

<div class="whatsapp-page">
    <div class="page-header">
        <div>
            <h1>إرسال واتساب</h1>
            <p>سيتم تجهيز الرسالة وفتح WhatsApp Web أو تطبيق واتساب بالرقم والنص المحددين.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('whatsapp.index') }}" class="btn btn-light">سجل واتساب</a>
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

                <div class="form-group">
                    <label>اسم المستلم</label>
                    <input type="text" name="recipient_name" value="{{ old('recipient_name') }}" placeholder="اختياري">
                </div>

                <div class="form-group">
                    <label>رقم واتساب <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="phone_number" value="{{ old('phone_number') }}" required placeholder="+965XXXXXXXX أو الرقم الدولي">
                    <div class="whatsapp-help">اكتب الرقم بصيغة دولية. إذا كتبت رقمًا محليًا من 8 أرقام سيتم إضافة 965 تلقائيًا.</div>
                </div>

                <div class="form-group full">
                    <label>نص رسالة واتساب <span style="color:#dc2626;">*</span></label>
                    <textarea name="message_body" required>{{ old('message_body', $defaults['message_body'] ?? '') }}</textarea>
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
@endsection

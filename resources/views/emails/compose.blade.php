@extends('layouts.app')

@section('title', 'إرسال بريد إلكتروني')
@section('page_title', 'إرسال بريد إلكتروني')
@section('page_subtitle', 'اختر كتابًا وأرسل بياناته مع المرفقات المحددة.')

@section('content')
@php
    $selectedDocumentId = old('document_id', $document?->id);
    $selectedAttachments = old('attachment_ids');
    if ($selectedAttachments === null && $document) {
        $selectedAttachments = $document->attachments->pluck('id')->map(fn ($id) => (string) $id)->all();
    }
    $selectedAttachments = array_map('strval', (array) $selectedAttachments);
@endphp

<div class="email-page">
    <div class="page-header">
        <div>
            <h1>إرسال بريد إلكتروني</h1>
            <p>يمكنك إرسال كتاب محدد مع بياناته ومرفقاته، أو إرسال رسالة عامة من النظام.</p>
        </div>
        <div class="page-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <a href="{{ route('emails.index') }}" class="btn btn-light">سجل البريد</a>
            @if($document)
                <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary">عرض الكتاب</a>
            @endif
        </div>
    </div>

    <div class="email-compose-layout">
        <form class="email-panel email-form" method="POST" action="{{ route('emails.send') }}" data-confirm="هل أنت متأكد من إرسال البريد الإلكتروني الآن؟">
            @csrf
            <input type="hidden" name="document_id" value="{{ $selectedDocumentId }}">

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

                <div class="form-group full">
                    <label>إلى <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="to" value="{{ old('to') }}" required placeholder="example@domain.com ويمكن فصل أكثر من بريد بفاصلة">
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
                    <input type="text" name="subject" value="{{ old('subject', $defaults['subject'] ?? '') }}" required>
                </div>

                <div class="form-group full">
                    <label>نص الرسالة <span style="color:#dc2626;">*</span></label>
                    <textarea name="body" required>{{ old('body', $defaults['body'] ?? '') }}</textarea>
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
            @else
                <h2>رسالة عامة</h2>
                <div class="email-note-box">لم يتم اختيار كتاب. يمكنك إرسال رسالة بريدية عامة من النظام، أو اختيار كتاب من القائمة لتجهيز بياناته ومرفقاته تلقائيًا.</div>
            @endif

            <div class="email-note-box" style="margin-top:14px;">
                <strong>تنبيه:</strong> إذا كان إعداد البريد في ملف <code>.env</code> مضبوطًا على <code>MAIL_MAILER=log</code> فسيتم تسجيل الرسالة في ملف السجل بدل إرسالها فعليًا، وهذا مناسب للتجربة الأولى.
            </div>
        </aside>
    </div>
</div>
@endsection

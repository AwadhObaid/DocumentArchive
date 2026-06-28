@extends('layouts.app')

@section('title', 'عرض الكتاب')
@section('page_title', 'عرض الكتاب')
@section('page_subtitle', 'بيانات الكتاب، البوالص، المرفقات، وسجل الحركة')

@section('content')
    <div class="page-title">
        <h1>عرض الكتاب</h1>

        <div class="actions">
            @if(auth()->user()?->hasPermission('documents.view'))
            <a href="{{ route('documents.index') }}" class="btn btn-secondary">رجوع</a>
            @endif
            @if(auth()->user()?->hasPermission('documents.update'))
            <a href="{{ route('documents.edit', $document) }}" class="btn btn-primary">تعديل</a>
            @endif
            @if(auth()->user()?->hasPermission('activity_logs.view'))
            <a href="{{ route('documents.activity', $document) }}" class="btn btn-info">سجل الحركة</a>
            @endif
            @if(auth()->user()?->hasPermission('documents.print_reference'))
            <a href="{{ route('documents.print-reference', $document) }}" target="_blank" class="btn btn-warning">طباعة رقم الكتاب</a>
            @endif
        </div>
    </div>

    <div class="card">
        <h2>رقم الكتاب: {{ $document->reference_number }}</h2>

        <table>
            <tr><th>تاريخ الكتاب</th><td>{{ $document->formatted_date }}</td></tr>
            <tr><th>موضوع الكتاب</th><td>{{ $document->subject ?? '-' }}</td></tr>
            <tr><th>عنوان الكتاب</th><td>{{ $document->title }}</td></tr>
            <tr><th>البوليصة الرئيسية</th><td>{{ $document->main_policy_number ?? '-' }}</td></tr>
            <tr><th>البوليصة الفرعية</th><td>{{ $document->sub_policy_number ?? '-' }}</td></tr>
            <tr><th>الإدارة</th><td>{{ $document->department?->name ?? '-' }}</td></tr>
            <tr><th>نوع الكتاب</th><td>{{ $document->documentType?->name ?? '-' }}</td></tr>
            <tr><th>المرسل</th><td>{{ $document->sender ?? '-' }}</td></tr>
            <tr><th>المستلم</th><td>{{ $document->receiver ?? '-' }}</td></tr>
            <tr><th>الحالة</th><td>{{ $document->status_name }}</td></tr>
            <tr><th>درجة السرية</th><td>{{ $document->confidentiality_name }}</td></tr>
            <tr><th>الأولوية</th><td>{{ $document->priority_name }}</td></tr>
            <tr><th>الوصف</th><td>{{ $document->description ?? '-' }}</td></tr>
            <tr><th>الملاحظات</th><td>{{ $document->notes ?? '-' }}</td></tr>
        </table>
    </div>

    <div class="card">
        <h3>المرفقات</h3>

        @forelse($document->attachments as $attachment)
            <div class="attachment-row">
                <div>
                    <p><strong>{{ $attachment->original_name }}</strong></p>
                    <p class="muted">
                        النسخة: {{ $attachment->version_no }} |
                        الحجم: {{ $attachment->file_size_for_humans }} |
                        النوع: {{ $attachment->extension ?? '-' }} |
                        @if($attachment->is_main)
                            <span class="badge">المرفق الحالي</span>
                        @else
                            <span class="badge">نسخة سابقة</span>
                        @endif
                    </p>
                </div>

                <div class="actions">
                    @if(auth()->user()?->hasPermission('attachments.preview'))
                    <a href="{{ route('attachments.preview', $attachment) }}" class="btn btn-info">استعراض</a>
                    @endif
                    @if(auth()->user()?->hasPermission('attachments.download'))
                    <a href="{{ route('attachments.download', $attachment) }}" class="btn btn-secondary">تنزيل المرفق</a>
                    @endif
                </div>
            </div>
        @empty
            <p>لا توجد مرفقات.</p>
        @endforelse
    </div>

{{-- QR Code للكتاب --}}
<div class="document-qr-panel no-print" style="margin:16px 0;padding:14px;border:1px solid rgba(148,163,184,.25);border-radius:16px;display:flex;align-items:center;gap:14px;background:rgba(15,23,42,.35);">
    <img src="{{ route('documents.qr', $document) }}" alt="QR Code" width="116" height="116" style="background:#fff;padding:8px;border-radius:12px;">
    <div>
        <strong>QR Code للكتاب</strong>
        <div style="font-size:13px;color:#94a3b8;margin-top:4px;">امسح الرمز للوصول مباشرة إلى صفحة الكتاب داخل النظام.</div>
    </div>
</div>
@endsection
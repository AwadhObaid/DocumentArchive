@extends('layouts.app')

@section('title', 'عرض الكتاب')
@section('page_title', 'عرض الكتاب')
@section('page_subtitle', 'بيانات الكتاب، البوالص، المرفقات، وطباعة رقم الكتاب')

@section('content')
    <div class="page-title">
        <h1>عرض الكتاب</h1>

        <div class="actions">
            <a href="{{ route('documents.index') }}" class="btn btn-secondary">رجوع</a>
            <a href="{{ route('documents.edit', $document) }}" class="btn btn-primary">تعديل</a>
            <a href="{{ route('documents.print-reference', $document) }}" target="_blank" class="btn btn-warning">طباعة رقم الكتاب</a>
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
                    <a href="{{ route('attachments.preview', $attachment) }}" class="btn btn-info">استعراض</a>
                    <a href="{{ route('attachments.download', $attachment) }}" class="btn btn-secondary">تنزيل المرفق</a>
                </div>
            </div>
        @empty
            <p>لا توجد مرفقات.</p>
        @endforelse
    </div>
@endsection

@extends('layouts.app')

@php
    $extension = strtolower($attachment->extension ?: pathinfo($attachment->file_name, PATHINFO_EXTENSION));
    $isPdf = $extension === 'pdf' || str_contains((string) $attachment->mime_type, 'pdf');
    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
    $inlineUrl = route('attachments.inline', $attachment);
@endphp

@section('title', 'م
عاينة الم
رفق')
@section('page_title', 'م
عاينة الم
رفق')
@section('page_subtitle', 'استعراض م
لفات PDF والصور داخل النظام
 بدون كشف م
سار التخزين الحقيقي')

@section('content')
    <div class="page-title">
        <h1>م
عاينة الم
رفق</h1>

        <div class="actions">
            @if($attachment->document && !$attachment->document->trashed())
                @if(auth()->user()?->hasPermission('documents.view'))
                <a href="{{ route('documents.show', $attachment->document) }}" class="btn btn-secondary">رجوع للكتاب</a>
                @endif
            @else
                @if(auth()->user()?->hasPermission('documents.restore'))
                <a href="{{ route('documents.trash') }}" class="btn btn-secondary">رجوع لسلة الم
حذوفات</a>
                @endif
            @endif

            @if(auth()->user()?->hasPermission('attachments.download'))
            <a href="{{ route('attachments.download', $attachment) }}" class="btn btn-primary">تنزيل الم
رفق</a>
            @endif
            <a href="{{ $inlineUrl }}" target="_blank" class="btn btn-warning">فتح في تبويب جديد</a>
        </div>
    </div>

    <div class="card">
        <div class="attachment-preview-header">
            <div>
                <h2>{{ $attachment->original_name }}</h2>
                <p class="muted">
                    النوع: {{ strtoupper($extension ?: '-') }} |
                    الحجم
: {{ $attachment->file_size_for_humans }} |
                    النسخة: {{ $attachment->version_no }}
                </p>
            </div>
        </div>

        @if($isPdf)
            <div class="pdf-preview-wrap">
                <object
                    data="{{ $inlineUrl }}#toolbar=1&navpanes=0&scrollbar=1"
                    type="application/pdf"
                    class="preview-frame"
                    style="width:100%; min-height:82vh; background:#fff; border:0; border-radius:12px;"
                >
                    <iframe
                        class="preview-frame"
                        src="{{ $inlineUrl }}#toolbar=1&navpanes=0&scrollbar=1"
                        style="width:100%; min-height:82vh; background:#fff; border:0; border-radius:12px;"
                    ></iframe>
                </object>
            </div>

            <p class="muted" style="margin-top:12px;">
                إذا لم
 تظهر الم
عاينة داخل الصفحة، اضغط زر
                <strong>فتح في تبويب جديد</strong> أو <strong>تنزيل الم
رفق</strong>.
            </p>
        @elseif($isImage)
            <div class="image-preview-wrap">
                <img src="{{ $inlineUrl }}" alt="{{ $attachment->original_name }}" class="image-preview">
            </div>
        @else
            <div class="empty-state">
                <h3>لا يم
كن م
عاينة هذا النوع م
باشرة داخل الم
تصفح</h3>
                <p>يم
كنك تنزيل الم
لف وفتحه م
ن جهازك.</p>
                @if(auth()->user()?->hasPermission('attachments.download'))
                <a href="{{ route('attachments.download', $attachment) }}" class="btn btn-primary">تنزيل الم
رفق</a>
                @endif
            </div>
        @endif
    </div>
@endsection

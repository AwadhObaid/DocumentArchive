@extends('layouts.app')

@php
    $document = $attachment->document ?? null;
    $fileName = $attachment->original_name ?: ($attachment->file_name ?: 'المرفق');
    $extension = strtolower($attachment->extension ?: pathinfo($fileName, PATHINFO_EXTENSION));
    $mimeType = strtolower($attachment->mime_type ?: '');
    $isPdf = $extension === 'pdf' || str_contains($mimeType, 'pdf');
    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true) || str_starts_with($mimeType, 'image/');
    $inlineUrl = route('attachments.inline', $attachment);
    $downloadUrl = route('attachments.download', $attachment);
@endphp

@section('title', 'معاينة المرفق')
@section('page_title', 'معاينة المرفق')
@section('page_subtitle', 'استعراض ملفات PDF والصور داخل النظام بدون إجبار المستخدم على التنزيل')

@section('content')
<style>
    .attachment-preview-shell {
        background: var(--card);
        color: var(--text);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: var(--shadow);
        padding: 18px;
    }
    .attachment-preview-header-clean {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }
    .attachment-preview-title-clean {
        margin: 0 0 6px;
        font-size: 20px;
        font-weight: 800;
        line-height: 1.6;
        word-break: break-word;
    }
    .attachment-preview-meta-clean {
        color: var(--muted);
        font-size: 13px;
        line-height: 1.8;
    }
    .attachment-preview-toolbar-clean {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }
    .attachment-preview-box-clean {
        width: 100%;
        min-height: 72vh;
        border: 1px solid var(--border);
        border-radius: 14px;
        background: #fff;
        overflow: hidden;
    }
    html[data-theme="dark"] .attachment-preview-box-clean {
        background: #0f172a;
    }
    .attachment-preview-frame-clean {
        display: block;
        width: 100%;
        min-height: 72vh;
        border: 0;
        background: #fff;
    }
    .attachment-preview-image-wrap-clean {
        min-height: 72vh;
        display: grid;
        place-items: center;
        padding: 16px;
        overflow: auto;
        background: #f8fafc;
    }
    html[data-theme="dark"] .attachment-preview-image-wrap-clean {
        background: #0f172a;
    }
    .attachment-preview-image-clean {
        max-width: 100%;
        height: auto;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(15, 23, 42, .18);
        background: #fff;
    }
    .attachment-preview-message-clean {
        border: 1px dashed var(--border);
        border-radius: 14px;
        padding: 28px;
        background: rgba(148, 163, 184, .08);
        color: var(--text);
        line-height: 1.9;
        text-align: center;
    }
    @media print {
        .sidebar, .topbar, .attachment-preview-toolbar-clean, .page-header, .no-print { display: none !important; }
        .main-area { margin: 0 !important; }
        .content-area { padding: 0 !important; }
        .attachment-preview-shell { border: 0 !important; box-shadow: none !important; padding: 0 !important; }
        .attachment-preview-box-clean, .attachment-preview-frame-clean { min-height: 100vh !important; border: 0 !important; border-radius: 0 !important; }
    }
</style>

<div class="page-header">
    <div>
        <h1>معاينة المرفق</h1>
        <p>يمكن للمستخدم معاينة الملف داخل الصفحة، أو فتحه في تبويب جديد، أو تنزيله بإرادته فقط.</p>
    </div>
</div>

<div class="attachment-preview-shell">
    <div class="attachment-preview-header-clean">
        <div>
            <h2 class="attachment-preview-title-clean">{{ $fileName }}</h2>
            <div class="attachment-preview-meta-clean">
                النوع: {{ strtoupper($extension ?: 'FILE') }}
                <span class="mx-1">|</span>
                الحجم: {{ number_format(($attachment->file_size ?? 0) / 1024 / 1024, 2) }} MB
                <span class="mx-1">|</span>
                النسخة: {{ $attachment->version_no ?? 1 }}
            </div>
        </div>

        <div class="attachment-preview-toolbar-clean no-print">
            @if($document && auth()->user()?->hasPermission('documents.view'))
                <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary">رجوع للكتاب</a>
            @else
                <a href="{{ url()->previous() }}" class="btn btn-secondary">رجوع</a>
            @endif

            <a href="{{ $inlineUrl }}" target="_blank" rel="noopener" class="btn btn-warning">فتح في تبويب جديد</a>

            @if(auth()->user()?->hasPermission('attachments.download'))
                <a href="{{ $downloadUrl }}" class="btn btn-primary">تنزيل المرفق</a>
            @endif

            @if($isPdf || $isImage)
                <button type="button" class="btn btn-success" onclick="window.print()">طباعة المعاينة</button>
            @endif
        </div>
    </div>

    @if($isPdf)
        <div class="attachment-preview-box-clean">
            <iframe
                class="attachment-preview-frame-clean"
                src="{{ $inlineUrl }}#toolbar=1&navpanes=0&scrollbar=1"
                title="معاينة المرفق: {{ $fileName }}"
            ></iframe>
        </div>
        <p class="muted" style="margin-top:12px;line-height:1.9;">
            إذا لم تظهر المعاينة داخل الصفحة، استخدم زر <strong>فتح في تبويب جديد</strong>. التنزيل يبقى اختيارياً فقط من زر <strong>تنزيل المرفق</strong>.
        </p>
    @elseif($isImage)
        <div class="attachment-preview-box-clean">
            <div class="attachment-preview-image-wrap-clean">
                <img class="attachment-preview-image-clean" src="{{ $inlineUrl }}" alt="{{ $fileName }}">
            </div>
        </div>
    @else
        <div class="attachment-preview-message-clean">
            <h3>لا يمكن معاينة هذا النوع مباشرة داخل المتصفح</h3>
            <p>لم يتم تنزيل الملف تلقائياً. يمكنك فتحه في تبويب جديد أو تنزيله إذا رغبت.</p>
            <div class="attachment-preview-toolbar-clean" style="justify-content:center;margin-top:12px;">
                <a href="{{ $inlineUrl }}" target="_blank" rel="noopener" class="btn btn-warning">فتح في تبويب جديد</a>
                @if(auth()->user()?->hasPermission('attachments.download'))
                    <a href="{{ $downloadUrl }}" class="btn btn-primary">تنزيل المرفق</a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
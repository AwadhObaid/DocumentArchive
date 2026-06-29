@extends('layouts.app')

@php
    $document = $attachment->document ?? null;
    $fileName = $attachment->original_name ?: ($attachment->file_name ?: 'المرفق');
    $extension = strtolower($attachment->extension ?: pathinfo($fileName, PATHINFO_EXTENSION));
    $mimeType = strtolower($attachment->mime_type ?: '');
    $isPdf = $extension === 'pdf' || str_contains($mimeType, 'pdf');
    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true) || str_starts_with($mimeType, 'image/');
    $canPreview = $isPdf || $isImage;
    $dataUrl = route('attachments.data', $attachment);
    $inlineUrl = route('attachments.inline', $attachment);
    $downloadUrl = route('attachments.download', $attachment);
@endphp

@section('title', 'معاينة المرفق')
@section('page_title', 'معاينة المرفق')
@section('page_subtitle', 'استعراض ملفات PDF والصور داخل النظام بدون إجبار المستخدم على التنزيل')

@section('content')
<style>
    .attachment-preview-page {
        max-width: 1100px;
        margin-inline: auto;
    }

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
        line-height: 1.7;
        word-break: break-word;
        white-space: normal;
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
        background: #ffffff;
        overflow: hidden;
    }

    html[data-theme="dark"] .attachment-preview-box-clean,
    body.dark .attachment-preview-box-clean {
        background: #0f172a;
    }

    .attachment-preview-frame-clean {
        display: block;
        width: 100%;
        height: 72vh;
        min-height: 560px;
        border: 0;
        background: #ffffff;
    }

    html[data-theme="dark"] .attachment-preview-frame-clean,
    body.dark .attachment-preview-frame-clean {
        background: #0f172a;
    }

    .attachment-preview-image-wrap-clean {
        min-height: 72vh;
        display: grid;
        place-items: center;
        padding: 16px;
        overflow: auto;
        background: #f8fafc;
    }

    html[data-theme="dark"] .attachment-preview-image-wrap-clean,
    body.dark .attachment-preview-image-wrap-clean {
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

    .attachment-preview-loading-clean {
        min-height: 72vh;
        display: grid;
        place-items: center;
        color: var(--muted);
        text-align: center;
        padding: 24px;
        line-height: 1.9;
    }

    .attachment-preview-hidden {
        display: none !important;
    }

    @media print {
        .sidebar,
        .topbar,
        .attachment-preview-toolbar-clean,
        .page-header,
        .no-print {
            display: none !important;
        }

        .main-area {
            margin: 0 !important;
        }

        .content-area {
            padding: 0 !important;
        }

        .attachment-preview-shell {
            border: 0 !important;
            box-shadow: none !important;
            padding: 0 !important;
        }

        .attachment-preview-box-clean,
        .attachment-preview-frame-clean {
            min-height: 100vh !important;
            height: 100vh !important;
            border: 0 !important;
            border-radius: 0 !important;
        }
    }
</style>

<div class="attachment-preview-page">
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

                @if($canPreview)
                    <button type="button" class="btn btn-success" onclick="window.print()">طباعة المعاينة</button>
                @endif
            </div>
        </div>

        @if($canPreview)
            <div class="attachment-preview-box-clean" id="attachmentPreviewBox" data-preview-type="{{ $isPdf ? 'pdf' : 'image' }}" data-data-url="{{ $dataUrl }}">
                <div class="attachment-preview-loading-clean" id="attachmentPreviewLoading">
                    جارٍ تجهيز المعاينة داخل الصفحة...<br>
                    لن يتم تنزيل الملف تلقائياً.
                </div>
            </div>

            <div class="attachment-preview-message-clean attachment-preview-hidden" id="attachmentPreviewFallback" style="margin-top:12px;">
                <h3>تعذرت معاينة الملف داخل الصفحة</h3>
                <p>لم يتم تنزيل الملف تلقائياً. يمكنك فتحه في تبويب جديد أو تنزيله إذا رغبت فقط.</p>
                <div class="attachment-preview-toolbar-clean" style="justify-content:center;margin-top:12px;">
                    <a href="{{ $inlineUrl }}" target="_blank" rel="noopener" class="btn btn-warning">فتح في تبويب جديد</a>
                    @if(auth()->user()?->hasPermission('attachments.download'))
                        <a href="{{ $downloadUrl }}" class="btn btn-primary">تنزيل المرفق</a>
                    @endif
                </div>
            </div>
        @else
            <div class="attachment-preview-message-clean">
                <h3>لا يمكن معاينة هذا النوع مباشرة داخل المتصفح</h3>
                <p>لم يتم تنزيل الملف تلقائياً. يمكنك فتحه في تبويب جديد أو تنزيله إذا رغبت فقط.</p>
                <div class="attachment-preview-toolbar-clean" style="justify-content:center;margin-top:12px;">
                    <a href="{{ $inlineUrl }}" target="_blank" rel="noopener" class="btn btn-warning">فتح في تبويب جديد</a>
                    @if(auth()->user()?->hasPermission('attachments.download'))
                        <a href="{{ $downloadUrl }}" class="btn btn-primary">تنزيل المرفق</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@if($canPreview)
<script>
(function () {
    const box = document.getElementById('attachmentPreviewBox');
    const loading = document.getElementById('attachmentPreviewLoading');
    const fallback = document.getElementById('attachmentPreviewFallback');

    if (!box) return;

    const previewType = box.dataset.previewType;
    const dataUrl = box.dataset.dataUrl;

    function showFallback() {
        if (loading) loading.remove();
        if (fallback) fallback.classList.remove('attachment-preview-hidden');
    }

    fetch(dataUrl, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
        .then(function (response) {
            if (!response.ok) throw new Error('Preview request failed');
            return response.json();
        })
        .then(function (payload) {
            if (!payload || !payload.base64 || !payload.mime_type) {
                throw new Error('Invalid preview payload');
            }

            const byteCharacters = atob(payload.base64);
            const byteArrays = [];
            const sliceSize = 1024;

            for (let offset = 0; offset < byteCharacters.length; offset += sliceSize) {
                const slice = byteCharacters.slice(offset, offset + sliceSize);
                const byteNumbers = new Array(slice.length);

                for (let i = 0; i < slice.length; i++) {
                    byteNumbers[i] = slice.charCodeAt(i);
                }

                byteArrays.push(new Uint8Array(byteNumbers));
            }

            const blob = new Blob(byteArrays, { type: payload.mime_type });
            const objectUrl = URL.createObjectURL(blob);

            if (loading) loading.remove();

            if (previewType === 'image') {
                const wrap = document.createElement('div');
                wrap.className = 'attachment-preview-image-wrap-clean';

                const image = document.createElement('img');
                image.className = 'attachment-preview-image-clean';
                image.alt = payload.file_name || 'معاينة المرفق';
                image.src = objectUrl;

                wrap.appendChild(image);
                box.appendChild(wrap);
                return;
            }

            const frame = document.createElement('iframe');
            frame.className = 'attachment-preview-frame-clean';
            frame.title = payload.file_name || 'معاينة المرفق';
            frame.src = objectUrl + '#toolbar=1&navpanes=0&scrollbar=1';
            box.appendChild(frame);
        })
        .catch(function () {
            showFallback();
        });
})();
</script>
@endif
@endsection
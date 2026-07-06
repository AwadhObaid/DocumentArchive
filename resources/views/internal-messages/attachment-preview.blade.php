@extends('layouts.app')

@php
    $fileName = $attachment->original_name ?: ($attachment->file_name ?: 'مرفق المراسلة');
    $extension = strtolower($attachment->extension ?: pathinfo($fileName, PATHINFO_EXTENSION));
    $mimeType = strtolower($attachment->mime_type ?: '');
    $isPdf = $extension === 'pdf' || str_contains($mimeType, 'pdf');
    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true) || str_starts_with($mimeType, 'image/');
    $canPreview = $isPdf || $isImage;
    $dataUrl = route('internal-messages.attachments.data', [$message, $attachment]);
    $inlineUrl = route('internal-messages.attachments.inline', [$message, $attachment]);
    $downloadUrl = route('internal-messages.attachments.download', [$message, $attachment]);
@endphp

@section('title', 'عرض مرفق مراسلة داخلية')
@section('page_title', 'عرض مرفق مراسلة داخلية')
@section('page_subtitle', 'معاينة مرفقات المراسلات داخل النظام بدون تنزيل إجباري')

@section('content')
<style>
    .im-attachment-preview-page {
        max-width: 1120px;
        margin-inline: auto;
    }

    .im-attachment-preview-shell {
        background: var(--card);
        color: var(--text);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: var(--shadow);
        padding: 18px;
    }

    .im-attachment-preview-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }

    .im-attachment-preview-title {
        margin: 0 0 6px;
        font-size: 20px;
        font-weight: 800;
        line-height: 1.7;
        word-break: break-word;
        white-space: normal;
    }

    .im-attachment-preview-meta {
        color: var(--muted);
        font-size: 13px;
        line-height: 1.8;
    }

    .im-attachment-preview-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }

    .im-attachment-preview-toolbar .btn[disabled] {
        opacity: .55;
        cursor: not-allowed;
    }

    .im-attachment-preview-box {
        width: 100%;
        min-height: 72vh;
        border: 1px solid var(--border);
        border-radius: 14px;
        background: #ffffff;
        overflow: hidden;
    }

    html[data-theme="dark"] .im-attachment-preview-box,
    body.dark .im-attachment-preview-box {
        background: #0f172a;
    }

    .im-attachment-preview-frame {
        display: block;
        width: 100%;
        height: 72vh;
        min-height: 560px;
        border: 0;
        background: #ffffff;
    }

    html[data-theme="dark"] .im-attachment-preview-frame,
    body.dark .im-attachment-preview-frame {
        background: #0f172a;
    }

    .im-attachment-preview-image-wrap {
        min-height: 72vh;
        display: grid;
        place-items: center;
        padding: 16px;
        overflow: auto;
        background: #f8fafc;
    }

    html[data-theme="dark"] .im-attachment-preview-image-wrap,
    body.dark .im-attachment-preview-image-wrap {
        background: #0f172a;
    }

    .im-attachment-preview-image {
        max-width: 100%;
        height: auto;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(15, 23, 42, .18);
        background: #fff;
    }

    .im-attachment-preview-message {
        border: 1px dashed var(--border);
        border-radius: 14px;
        padding: 28px;
        background: rgba(148, 163, 184, .08);
        color: var(--text);
        line-height: 1.9;
        text-align: center;
    }

    .im-attachment-preview-loading {
        min-height: 72vh;
        display: grid;
        place-items: center;
        color: var(--muted);
        text-align: center;
        padding: 24px;
        line-height: 1.9;
    }

    .im-attachment-preview-hidden {
        display: none !important;
    }

    .im-attachment-print-frame-hidden {
        position: fixed;
        inset-inline-start: -10000px;
        top: 0;
        width: 1px;
        height: 1px;
        border: 0;
        opacity: 0;
        pointer-events: none;
    }

    @media print {
        .sidebar,
        .topbar,
        .im-attachment-preview-toolbar,
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

        .im-attachment-preview-shell {
            border: 0 !important;
            box-shadow: none !important;
            padding: 0 !important;
        }

        .im-attachment-preview-box,
        .im-attachment-preview-frame {
            min-height: 100vh !important;
            height: 100vh !important;
            border: 0 !important;
            border-radius: 0 !important;
        }
    }
</style>

<div class="im-attachment-preview-page">
    <div class="page-header">
        <div>
            <h1>عرض مرفق مراسلة داخلية</h1>
            <p>الرسالة: <strong>{{ $message->subject }}</strong> — يتم العرض داخل النظام بدون تنزيل تلقائي.</p>
        </div>
    </div>

    <div class="im-attachment-preview-shell">
        <div class="im-attachment-preview-header">
            <div>
                <h2 class="im-attachment-preview-title">{{ $fileName }}</h2>
                <div class="im-attachment-preview-meta">
                    النوع: {{ strtoupper($extension ?: 'FILE') }}
                    <span class="mx-1">|</span>
                    الحجم: {{ $attachment->file_size_for_humans }}
                    <span class="mx-1">|</span>
                    المرسل: {{ $message->sender?->name ?: '-' }}
                </div>
            </div>

            <div class="im-attachment-preview-toolbar no-print">
                <a href="{{ route('internal-messages.show', $message) }}" class="btn btn-secondary">رجوع للرسالة</a>

                @if($canPreview)
                    <button type="button" class="btn btn-warning" id="imAttachmentOpenOriginalBtn" disabled>فتح في تبويب جديد</button>
                @else
                    <a href="{{ $inlineUrl }}" target="_blank" rel="noopener" class="btn btn-warning">فتح في تبويب جديد</a>
                @endif

                <a href="{{ $downloadUrl }}" class="btn btn-primary">تنزيل المرفق</a>

                @if($canPreview)
                    <button type="button" class="btn btn-success" id="imAttachmentPrintOriginalBtn" disabled>طباعة المرفق</button>
                @endif
            </div>
        </div>

        @if($canPreview)
            <div class="im-attachment-preview-box" id="imAttachmentPreviewBox" data-preview-type="{{ $isPdf ? 'pdf' : 'image' }}" data-data-url="{{ $dataUrl }}">
                <div class="im-attachment-preview-loading" id="imAttachmentPreviewLoading">
                    جارٍ تجهيز المعاينة داخل الصفحة...<br>
                    لن يتم تنزيل الملف تلقائياً.
                </div>
            </div>

            <div class="im-attachment-preview-message im-attachment-preview-hidden" id="imAttachmentPreviewFallback" style="margin-top:12px;">
                <h3>تعذرت معاينة الملف داخل الصفحة</h3>
                <p>لم يتم تنزيل الملف تلقائياً. يمكنك فتحه في تبويب جديد أو تنزيله إذا رغبت فقط.</p>
                <div class="im-attachment-preview-toolbar" style="justify-content:center;margin-top:12px;">
                    <a href="{{ $inlineUrl }}" target="_blank" rel="noopener" class="btn btn-warning">فتح في تبويب جديد</a>
                    <a href="{{ $downloadUrl }}" class="btn btn-primary">تنزيل المرفق</a>
                </div>
            </div>
            <iframe id="imAttachmentPrintFrame" class="im-attachment-print-frame-hidden" title="طباعة مرفق المراسلة"></iframe>
        @else
            <div class="im-attachment-preview-message">
                <h3>لا يمكن معاينة هذا النوع مباشرة داخل المتصفح</h3>
                <p>لم يتم تنزيل الملف تلقائياً. يمكنك فتحه في تبويب جديد أو تنزيله إذا رغبت فقط.</p>
                <div class="im-attachment-preview-toolbar" style="justify-content:center;margin-top:12px;">
                    <a href="{{ $inlineUrl }}" target="_blank" rel="noopener" class="btn btn-warning">فتح في تبويب جديد</a>
                    <a href="{{ $downloadUrl }}" class="btn btn-primary">تنزيل المرفق</a>
                </div>
            </div>
        @endif
    </div>
</div>

@if($canPreview)
<script>
(function () {
    const box = document.getElementById('imAttachmentPreviewBox');
    const loading = document.getElementById('imAttachmentPreviewLoading');
    const fallback = document.getElementById('imAttachmentPreviewFallback');
    const printButton = document.getElementById('imAttachmentPrintOriginalBtn');
    const openButton = document.getElementById('imAttachmentOpenOriginalBtn');
    const printFrame = document.getElementById('imAttachmentPrintFrame');

    if (!box) return;

    const previewType = box.dataset.previewType;
    const dataUrl = box.dataset.dataUrl;

    let previewObjectUrl = null;
    let previewPayload = null;

    function showFallback() {
        if (loading) loading.remove();
        if (fallback) fallback.classList.remove('im-attachment-preview-hidden');
    }

    function enableActions() {
        if (openButton) openButton.disabled = false;
        if (printButton) printButton.disabled = false;
    }

    function blobFromBase64(base64, mimeType) {
        const byteCharacters = atob(base64);
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

        return new Blob(byteArrays, { type: mimeType });
    }

    function renderImage(objectUrl, fileName) {
        const wrap = document.createElement('div');
        wrap.className = 'im-attachment-preview-image-wrap';

        const image = document.createElement('img');
        image.className = 'im-attachment-preview-image';
        image.alt = fileName || 'معاينة مرفق المراسلة';
        image.src = objectUrl;

        wrap.appendChild(image);
        box.appendChild(wrap);
    }

    function renderPdf(objectUrl, fileName) {
        const frame = document.createElement('iframe');
        frame.className = 'im-attachment-preview-frame';
        frame.title = fileName || 'معاينة مرفق المراسلة';
        frame.src = objectUrl + '#toolbar=1&navpanes=0&scrollbar=1';
        box.appendChild(frame);
    }

    function printImageOriginal() {
        if (!printFrame || !previewObjectUrl) return false;

        const frameWindow = printFrame.contentWindow;
        const frameDocument = frameWindow.document;
        const safeTitle = (previewPayload && (previewPayload.file_name || previewPayload.name)) ? (previewPayload.file_name || previewPayload.name) : 'طباعة مرفق المراسلة';

        frameDocument.open();
        frameDocument.write('<!doctype html><html><head><meta charset="utf-8"><title>' + safeTitle.replace(/[<>&"]/g, '') + '</title><style>@page{margin:10mm;}html,body{margin:0;padding:0;background:#fff;}body{min-height:100vh;display:flex;align-items:center;justify-content:center;}img{max-width:100%;max-height:100vh;object-fit:contain;}</style></head><body><img id="printImage" src="' + previewObjectUrl + '" alt=""></body></html>');
        frameDocument.close();

        const image = frameDocument.getElementById('printImage');
        const doPrint = function () {
            try {
                frameWindow.focus();
                frameWindow.print();
            } catch (error) {
                alert('تعذرت الطباعة التلقائية. يمكنك فتح الملف في تبويب جديد ثم طباعته.');
            }
        };

        if (image.complete) {
            setTimeout(doPrint, 250);
        } else {
            image.onload = function () { setTimeout(doPrint, 250); };
            image.onerror = function () { alert('تعذرت طباعة الصورة. يمكنك فتح الملف في تبويب جديد ثم طباعته.'); };
        }

        return true;
    }

    function printPdfOriginal() {
        if (!printFrame || !previewObjectUrl) return false;

        let printed = false;
        printFrame.onload = function () {
            if (printed) return;
            printed = true;

            setTimeout(function () {
                try {
                    printFrame.contentWindow.focus();
                    printFrame.contentWindow.print();
                } catch (error) {
                    alert('تعذرت الطباعة التلقائية. يمكنك فتح الملف في تبويب جديد ثم طباعته.');
                }
            }, 800);
        };

        printFrame.src = previewObjectUrl;
        return true;
    }

    function printOriginalAttachment() {
        if (!previewObjectUrl || !previewPayload) {
            alert('لم تكتمل المعاينة بعد. انتظر لحظة ثم حاول مرة أخرى.');
            return;
        }

        if (previewType === 'image') {
            printImageOriginal();
            return;
        }

        printPdfOriginal();
    }

    function openOriginalAttachment() {
        if (!previewObjectUrl) {
            alert('لم تكتمل المعاينة بعد. انتظر لحظة ثم حاول مرة أخرى.');
            return;
        }

        const opened = window.open(previewObjectUrl, '_blank', 'noopener');
        if (!opened) {
            alert('تعذر فتح التبويب الجديد. تحقق من إعدادات منع النوافذ المنبثقة في المتصفح.');
        }
    }

    if (printButton) {
        printButton.addEventListener('click', printOriginalAttachment);
    }

    if (openButton) {
        openButton.addEventListener('click', openOriginalAttachment);
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

            previewPayload = payload;
            const blob = blobFromBase64(payload.base64, payload.mime_type);
            previewObjectUrl = URL.createObjectURL(blob);

            if (loading) loading.remove();

            if (previewType === 'image') {
                renderImage(previewObjectUrl, payload.file_name || payload.name);
            } else {
                renderPdf(previewObjectUrl, payload.file_name || payload.name);
            }

            enableActions();
        })
        .catch(function () {
            showFallback();
        });

    window.addEventListener('beforeunload', function () {
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
        }
    });
})();
</script>
@endif
@endsection

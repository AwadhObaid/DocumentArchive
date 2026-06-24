@extends('layouts.app')

@section('title', 'معاينة المرفق')

@section('content')
@php
    $document = $attachment->document ?? null;
    $fileName = $attachment->original_name ?: ($attachment->file_name ?: 'المرفق');
    $extension = strtolower($attachment->extension ?: pathinfo($fileName, PATHINFO_EXTENSION));
    $mimeType = strtolower($attachment->mime_type ?: '');
@endphp

<style>
    .attachment-preview-shell {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        box-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
        padding: 18px;
    }

    .attachment-preview-header {
        text-align: center;
        margin-bottom: 14px;
    }

    .attachment-preview-title {
        font-weight: 800;
        color: #0f172a;
        font-size: 21px;
        margin-bottom: 5px;
        word-break: break-word;
    }

    .attachment-preview-meta {
        color: #64748b;
        font-size: 13px;
    }

    .attachment-preview-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin: 14px 0 10px;
    }

    .attachment-preview-toolbar .btn,
    .attachment-preview-toolbar button,
    .attachment-preview-toolbar a {
        border: 0;
        border-radius: 10px;
        padding: 8px 13px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: 0.15s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-soft-primary {
        background: #e0ecff;
        color: #1d4ed8;
    }

    .btn-soft-primary:hover {
        background: #c7ddff;
        color: #1d4ed8;
    }

    .btn-soft-success {
        background: #dcfce7;
        color: #15803d;
    }

    .btn-soft-success:hover {
        background: #bbf7d0;
        color: #166534;
    }

    .btn-soft-muted {
        background: #f1f5f9;
        color: #334155;
    }

    .btn-soft-muted:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .attachment-viewer-box {
        min-height: 520px;
        background: #f8fafc;
        border: 1px solid #dbe3ef;
        border-radius: 14px;
        padding: 12px;
        overflow: auto;
        position: relative;
    }

    .preview-status {
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        color: #3730a3;
        border-radius: 12px;
        padding: 13px 15px;
        text-align: center;
        font-weight: 700;
        margin-bottom: 12px;
    }

    .preview-status.error {
        background: #fee2e2;
        border-color: #fecaca;
        color: #b91c1c;
    }

    .preview-status.success {
        background: #dcfce7;
        border-color: #bbf7d0;
        color: #166534;
    }

    .pdf-pages-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 18px;
    }

    .pdf-page-canvas {
        background: #fff;
        max-width: 100%;
        height: auto;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18);
        border-radius: 4px;
    }

    .image-preview {
        max-width: 100%;
        height: auto;
        display: block;
        margin: 0 auto;
        border-radius: 12px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.14);
        background: #fff;
    }

    #attachmentPrintArea {
        display: none;
    }

    @media print {
        @page {
            size: A4;
            margin: 0;
        }

        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
        }

        body * {
            visibility: hidden !important;
        }

        #attachmentPrintArea,
        #attachmentPrintArea * {
            visibility: visible !important;
        }

        #attachmentPrintArea {
            display: block !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            direction: ltr !important;
            text-align: center !important;
        }

        #attachmentPrintArea .print-page {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            height: auto !important;
            margin: 0 auto !important;
            padding: 0 !important;
            border: 0 !important;
            page-break-after: always;
            break-after: page;
        }

        #attachmentPrintArea .print-page:last-child {
            page-break-after: auto;
            break-after: auto;
        }
    }
</style>

<div class="page-header mb-3">
    <div>
        <h1 class="page-title">معاينة المرفق</h1>
        <p class="page-subtitle">استعراض PDF والصور داخل النظام بدون كشف مسار التخزين الحقيقي.</p>
    </div>
</div>

<div class="mb-3 d-flex flex-wrap gap-2">
    @if($document)
        <a href="{{ route('documents.show', $document) }}" class="btn btn-light">رجوع للكتاب</a>
    @else
        <a href="{{ url()->previous() }}" class="btn btn-light">رجوع</a>
    @endif

    <a href="{{ route('attachments.download', $attachment) }}" class="btn btn-primary">تنزيل المرفق</a>
</div>

<div class="attachment-preview-shell">
    <div class="attachment-preview-header">
        <div class="attachment-preview-title">{{ $fileName }}</div>
        <div class="attachment-preview-meta">
            النوع: {{ strtoupper($extension ?: 'FILE') }}
            <span class="mx-1">|</span>
            الحجم: {{ number_format(($attachment->file_size ?? 0) / 1024 / 1024, 2) }} MB
            <span class="mx-1">|</span>
            النسخة: {{ $attachment->version_no ?? 1 }}
        </div>
    </div>

    <div class="attachment-preview-toolbar">
        <button type="button" class="btn-soft-success" id="printAttachmentBtn">🖨️ طباعة المرفق</button>
        <button type="button" class="btn-soft-muted" id="zoomOutBtn">- تصغير</button>
        <button type="button" class="btn-soft-muted" id="zoomInBtn">+ تكبير</button>
        <button type="button" class="btn-soft-muted" id="reloadPreviewBtn">↻ تحديث</button>
        <a class="btn-soft-primary" href="{{ route('attachments.download', $attachment) }}">⬇ تنزيل</a>
    </div>

    <div class="attachment-viewer-box" id="attachmentViewerBox">
        <div class="preview-status" id="previewStatus">جاري تحميل المرفق للمعاينة...</div>
        <div class="pdf-pages-wrapper" id="pdf-pages-wrapper"></div>
    </div>
</div>

<div id="attachmentPrintArea" aria-hidden="true"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" referrerpolicy="no-referrer"></script>
<script>
(function () {
    'use strict';

    const DATA_URL = @json(route('attachments.data', $attachment));
    const FILE_NAME = @json($fileName);
    const FALLBACK_MIME = @json($attachment->mime_type ?: '');
    const FALLBACK_EXTENSION = @json($extension ?: '');

    const statusBox = document.getElementById('previewStatus');
    const pagesWrapper = document.getElementById('pdf-pages-wrapper');
    const printArea = document.getElementById('attachmentPrintArea');
    const printButton = document.getElementById('printAttachmentBtn');
    const zoomInButton = document.getElementById('zoomInBtn');
    const zoomOutButton = document.getElementById('zoomOutBtn');
    const reloadButton = document.getElementById('reloadPreviewBtn');

    let payload = null;
    let binaryBytes = null;
    let pdfDocument = null;
    let currentScale = 1.35;
    let isRendering = false;
    let isPrinting = false;

    if (window.pdfjsLib) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    function setStatus(message, type = 'info') {
        statusBox.textContent = message;
        statusBox.classList.remove('error', 'success');
        if (type === 'error') statusBox.classList.add('error');
        if (type === 'success') statusBox.classList.add('success');
        statusBox.style.display = message ? 'block' : 'none';
    }

    function normalizeBase64(value) {
        if (!value || typeof value !== 'string') return '';
        const commaIndex = value.indexOf(',');
        return commaIndex >= 0 ? value.substring(commaIndex + 1) : value;
    }

    function base64ToUint8Array(base64) {
        const clean = normalizeBase64(base64).replace(/\s/g, '');
        const raw = atob(clean);
        const bytes = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) {
            bytes[i] = raw.charCodeAt(i);
        }
        return bytes;
    }

    function getPayloadBase64(data) {
        return data.base64 || data.content || data.file || data.data || data.file_base64 || '';
    }

    function getMimeType(data) {
        return (data.mime_type || data.mimeType || data.type || FALLBACK_MIME || '').toLowerCase();
    }

    function getExtension(data) {
        return (data.extension || FALLBACK_EXTENSION || '').toLowerCase();
    }

    function isPdfFile(data) {
        const mime = getMimeType(data);
        const ext = getExtension(data);
        return mime.includes('pdf') || ext === 'pdf';
    }

    function isImageFile(data) {
        const mime = getMimeType(data);
        const ext = getExtension(data);
        return mime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext);
    }

    async function loadPayload() {
        const response = await fetch(DATA_URL, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('تعذر تحميل بيانات المرفق. رمز الخطأ: ' + response.status);
        }

        const data = await response.json();
        const base64 = getPayloadBase64(data);

        if (!base64) {
            throw new Error('استجابة المرفق لا تحتوي على بيانات Base64.');
        }

        payload = data;
        binaryBytes = base64ToUint8Array(base64);
        return data;
    }

    async function renderPdf(scale = currentScale) {
        if (!window.pdfjsLib) {
            throw new Error('تعذر تحميل PDF.js. تحقق من اتصال الإنترنت أو أضف PDF.js محلياً.');
        }

        if (!payload || !binaryBytes) {
            await loadPayload();
        }

        isRendering = true;
        pagesWrapper.innerHTML = '';
        setStatus('جاري تجهيز صفحات PDF...', 'info');

        pdfDocument = await pdfjsLib.getDocument({ data: binaryBytes.slice(0) }).promise;

        for (let pageNumber = 1; pageNumber <= pdfDocument.numPages; pageNumber++) {
            const page = await pdfDocument.getPage(pageNumber);
            const viewport = page.getViewport({ scale });
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d', { alpha: false });

            canvas.className = 'pdf-page-canvas';
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.dataset.page = pageNumber;

            pagesWrapper.appendChild(canvas);

            await page.render({
                canvasContext: context,
                viewport: viewport
            }).promise;
        }

        setStatus('تم تحميل المعاينة بنجاح.', 'success');
        setTimeout(() => setStatus('', 'success'), 900);
        isRendering = false;
    }

    async function renderImage() {
        if (!payload || !binaryBytes) {
            await loadPayload();
        }

        pagesWrapper.innerHTML = '';
        const base64 = normalizeBase64(getPayloadBase64(payload));
        const mime = getMimeType(payload) || 'image/png';
        const image = document.createElement('img');
        image.className = 'image-preview';
        image.alt = FILE_NAME;
        image.src = `data:${mime};base64,${base64}`;
        pagesWrapper.appendChild(image);
        setStatus('تم تحميل المعاينة بنجاح.', 'success');
        setTimeout(() => setStatus('', 'success'), 900);
    }

    async function renderPreview() {
        try {
            setStatus('جاري تحميل المرفق للمعاينة...', 'info');
            pagesWrapper.innerHTML = '';
            await loadPayload();

            if (isPdfFile(payload)) {
                await renderPdf(currentScale);
                return;
            }

            if (isImageFile(payload)) {
                await renderImage();
                return;
            }

            setStatus('هذا النوع من الملفات لا يدعم المعاينة المباشرة. يمكنك تنزيله من زر تنزيل المرفق.', 'error');
        } catch (error) {
            console.error(error);
            setStatus(error.message || 'حدث خطأ أثناء تحميل المعاينة.', 'error');
        } finally {
            isRendering = false;
        }
    }

    function waitForImage(image) {
        return new Promise((resolve, reject) => {
            if (image.complete && image.naturalWidth > 0) {
                resolve();
                return;
            }

            image.onload = () => resolve();
            image.onerror = () => reject(new Error('تعذر تجهيز إحدى صفحات الطباعة.'));
        });
    }

    async function addPdfPagesToPrintArea() {
        if (!window.pdfjsLib) {
            throw new Error('PDF.js غير متاح حالياً.');
        }

        if (!payload || !binaryBytes) {
            await loadPayload();
        }

        const printDoc = await pdfjsLib.getDocument({ data: binaryBytes.slice(0) }).promise;
        printArea.innerHTML = '';

        for (let pageNumber = 1; pageNumber <= printDoc.numPages; pageNumber++) {
            setStatus('جاري تجهيز صفحة الطباعة ' + pageNumber + ' من ' + printDoc.numPages + '...', 'info');

            const page = await printDoc.getPage(pageNumber);
            const viewport = page.getViewport({ scale: 2.25 });
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d', { alpha: false });

            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);

            await page.render({
                canvasContext: context,
                viewport: viewport
            }).promise;

            const image = new Image();
            image.className = 'print-page';
            image.alt = 'صفحة ' + pageNumber;
            image.src = canvas.toDataURL('image/png');
            printArea.appendChild(image);
            await waitForImage(image);
        }
    }

    async function addImageToPrintArea() {
        if (!payload || !binaryBytes) {
            await loadPayload();
        }

        const base64 = normalizeBase64(getPayloadBase64(payload));
        const mime = getMimeType(payload) || 'image/png';
        printArea.innerHTML = '';

        const image = new Image();
        image.className = 'print-page';
        image.alt = FILE_NAME;
        image.src = `data:${mime};base64,${base64}`;
        printArea.appendChild(image);
        await waitForImage(image);
    }

    function cleanupPrintArea() {
        isPrinting = false;
        printButton.disabled = false;
        printButton.textContent = '🖨️ طباعة المرفق';
        printArea.innerHTML = '';
        printArea.setAttribute('aria-hidden', 'true');
        setStatus('', 'success');
        window.removeEventListener('afterprint', cleanupPrintArea);
    }

    async function printAttachment() {
        if (isRendering || isPrinting) return;

        try {
            isPrinting = true;
            printButton.disabled = true;
            printButton.textContent = 'جاري تجهيز الطباعة...';
            setStatus('جاري تجهيز المرفق للطباعة...', 'info');
            printArea.innerHTML = '';
            printArea.setAttribute('aria-hidden', 'false');

            if (!payload) {
                await loadPayload();
            }

            if (isPdfFile(payload)) {
                await addPdfPagesToPrintArea();
            } else if (isImageFile(payload)) {
                await addImageToPrintArea();
            } else {
                throw new Error('هذا النوع من الملفات لا يدعم الطباعة المباشرة.');
            }

            setStatus('تم تجهيز الطباعة. ستظهر نافذة الطابعة الآن.', 'success');
            window.addEventListener('afterprint', cleanupPrintArea);

            requestAnimationFrame(() => {
                setTimeout(() => {
                    window.focus();
                    window.print();
                }, 350);
            });
        } catch (error) {
            console.error(error);
            isPrinting = false;
            printButton.disabled = false;
            printButton.textContent = '🖨️ طباعة المرفق';
            printArea.innerHTML = '';
            printArea.setAttribute('aria-hidden', 'true');
            setStatus(error.message || 'تعذر تجهيز الملف للطباعة.', 'error');
        }
    }

    zoomInButton.addEventListener('click', async () => {
        if (!payload || !isPdfFile(payload) || isRendering) return;
        currentScale = Math.min(currentScale + 0.2, 3);
        await renderPdf(currentScale);
    });

    zoomOutButton.addEventListener('click', async () => {
        if (!payload || !isPdfFile(payload) || isRendering) return;
        currentScale = Math.max(currentScale - 0.2, 0.7);
        await renderPdf(currentScale);
    });

    reloadButton.addEventListener('click', () => {
        payload = null;
        binaryBytes = null;
        pdfDocument = null;
        renderPreview();
    });

    printButton.addEventListener('click', printAttachment);

    renderPreview();
})();
</script>
@endsection

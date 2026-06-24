@extends('layouts.app')

@section('title', 'معاينة المرفق')

@section('content')
    <div class="page-header">
        <div>
            <h1>معاينة المرفق</h1>
            <p>استعراض PDF والصور داخل النظام بدون كشف مسار التخزين الحقيقي.</p>
        </div>

        <div class="actions">
            <a href="{{ route('documents.show', $attachment->document) }}" class="btn btn-secondary">رجوع للكتاب</a>
            <a href="{{ route('attachments.download', $attachment) }}" class="btn btn-primary">تنزيل المرفق</a>
        </div>
    </div>

    <div class="card">
        <h2 style="text-align:center; margin-bottom:8px;">
            {{ $attachment->original_name }}
        </h2>

        <p style="text-align:center; color:#64748b; margin-top:0;">
            النوع: {{ strtoupper($attachment->extension ?? '-') }} |
            الحجم: {{ $attachment->file_size_for_humans ?? number_format(($attachment->file_size ?? 0) / 1024, 2) . ' KB' }} |
            النسخة: {{ $attachment->version_no }}
        </p>

        <div style="display:flex; justify-content:center; gap:8px; margin:12px 0;">
            <button type="button" class="btn btn-secondary" onclick="zoomOut()">- تصغير</button>
            <button type="button" class="btn btn-secondary" onclick="zoomIn()">+ تكبير</button>
        </div>

        <div id="previewStatus" style="background:#eff6ff; color:#1d4ed8; padding:16px; border-radius:10px; text-align:center; margin-bottom:16px;">
            جار تحميل المرفق...
        </div>

        <div id="previewArea" style="min-height:700px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:12px; overflow:auto; padding:18px; text-align:center;"></div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs" type="module"></script>

    <script type="module">
        const dataUrl = @json(route('attachments.data', $attachment));
        const previewArea = document.getElementById('previewArea');
        const previewStatus = document.getElementById('previewStatus');

        let currentPdf = null;
        let scale = 1.25;
        let fileData = null;

        function base64ToUint8Array(base64) {
            const raw = window.atob(base64);
            const array = new Uint8Array(raw.length);

            for (let i = 0; i < raw.length; i++) {
                array[i] = raw.charCodeAt(i);
            }

            return array;
        }

        async function renderPdf() {
            previewArea.innerHTML = '';

            for (let pageNumber = 1; pageNumber <= currentPdf.numPages; pageNumber++) {
                const page = await currentPdf.getPage(pageNumber);
                const viewport = page.getViewport({ scale });

                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');

                canvas.width = viewport.width;
                canvas.height = viewport.height;
                canvas.style.display = 'block';
                canvas.style.margin = '0 auto 18px auto';
                canvas.style.background = '#fff';
                canvas.style.boxShadow = '0 4px 14px rgba(15, 23, 42, .12)';

                previewArea.appendChild(canvas);

                await page.render({
                    canvasContext: context,
                    viewport: viewport
                }).promise;
            }
        }

        async function loadPreview() {
            try {
                const response = await fetch(dataUrl, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error('تعذر تحميل بيانات المرفق.');
                }

                fileData = await response.json();
                const extension = (fileData.extension || '').toLowerCase();

                if (extension === 'pdf') {
                    const pdfjsLib = await import('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs');
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';

                    const bytes = base64ToUint8Array(fileData.base64);
                    currentPdf = await pdfjsLib.getDocument({ data: bytes }).promise;

                    previewStatus.style.display = 'none';
                    await renderPdf();
                    return;
                }

                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
                    previewStatus.style.display = 'none';
                    previewArea.innerHTML = `<img src="data:${fileData.mime_type};base64,${fileData.base64}" style="max-width:100%; height:auto; border-radius:10px; box-shadow:0 4px 14px rgba(15,23,42,.12);">`;
                    return;
                }

                previewStatus.style.background = '#fee2e2';
                previewStatus.style.color = '#991b1b';
                previewStatus.textContent = 'هذا النوع من الملفات لا يدعم المعاينة داخل النظام. يمكنك تنزيل الملف.';
            } catch (error) {
                previewStatus.style.background = '#fee2e2';
                previewStatus.style.color = '#991b1b';
                previewStatus.textContent = error.message || 'فشل عرض الملف.';
            }
        }

        window.zoomIn = async function () {
            if (!currentPdf) return;
            scale += 0.15;
            await renderPdf();
        };

        window.zoomOut = async function () {
            if (!currentPdf) return;
            scale = Math.max(0.5, scale - 0.15);
            await renderPdf();
        };

        loadPreview();
    </script>
@endsection

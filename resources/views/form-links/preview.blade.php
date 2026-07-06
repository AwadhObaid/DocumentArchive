@extends('layouts.app')

@section('title', 'معاينة نموذج')
@section('page_title', 'معاينة نموذج')
@section('page_subtitle', 'استعراض نماذج PDF داخل النظام بنفس طريقة مرفقات الكتب والمذكرات')

@section('content')
<style>
    .form-preview-shell {
        background: var(--card);
        color: var(--text);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: var(--shadow);
        padding: 18px;
    }
    .form-preview-header {
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:14px;
        flex-wrap:wrap;
        margin-bottom:14px;
    }
    .form-preview-title { margin:0 0 6px; font-size:20px; font-weight:800; line-height:1.6; word-break:break-word; }
    .form-preview-meta { color:var(--muted); font-size:13px; line-height:1.8; }
    .form-preview-toolbar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
    .form-preview-box { width:100%; min-height:72vh; border:1px solid var(--border); border-radius:14px; background:#fff; overflow:hidden; }
    html[data-theme="dark"] .form-preview-box, body.dark .form-preview-box { background:#0f172a; }
    .form-preview-frame, .form-preview-object, .form-preview-embed { display:block; width:100%; min-height:72vh; height:72vh; border:0; background:#fff; }
    .form-preview-message { border:1px dashed var(--border); border-radius:14px; padding:28px; background:rgba(148,163,184,.08); color:var(--text); line-height:1.9; text-align:center; }
    .form-preview-warning { margin-top:12px; padding:12px 14px; border:1px solid rgba(245,158,11,.35); border-radius:14px; background:rgba(255,251,235,.92); color:#92400e; line-height:1.9; }
    html[data-theme="dark"] .form-preview-warning, body.dark .form-preview-warning { background:rgba(120,53,15,.22); color:#fde68a; }
    @media print {
        .sidebar, .topbar, .form-preview-toolbar, .page-header, .no-print { display:none !important; }
        .main-area { margin:0 !important; }
        .content-area { padding:0 !important; }
        .form-preview-shell { border:0 !important; box-shadow:none !important; padding:0 !important; }
        .form-preview-box, .form-preview-frame { min-height:100vh !important; border:0 !important; border-radius:0 !important; }
    }
</style>

<div class="page-header">
    <div>
        <h1>معاينة نموذج</h1>
        <p>فتح نموذج PDF داخل النظام بدون إجبار المستخدم على التنزيل.</p>
    </div>
</div>

<div class="form-preview-shell">
    <div class="form-preview-header">
        <div>
            <h2 class="form-preview-title">{{ $formLink->title }}</h2>
            <div class="form-preview-meta">
                الملف: {{ $fileName ?: 'النموذج' }}
                <span class="mx-1">|</span>
                النوع: {{ strtoupper($extension ?: 'LINK') }}
                <span class="mx-1">|</span>
                التصنيف: {{ $formLink->category ?: 'بدون تصنيف' }}
            </div>
        </div>

        <div class="form-preview-toolbar no-print">
            <a href="{{ route('form-links.index') }}" class="btn btn-secondary">رجوع للنماذج</a>

            @if($isLocalFile)
                <a href="{{ $inlineUrl }}" target="_blank" rel="noopener" class="btn btn-warning">فتح في تبويب جديد</a>
                @if(!empty($publicUrl) && $publicUrl !== $inlineUrl)
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="btn btn-secondary">فتح كرابط مباشر</a>
                @endif
                <a href="{{ $downloadUrl }}" class="btn btn-primary">تحميل النموذج</a>
                @if($isPdf || $isImage)
                    <button type="button" class="btn btn-success" onclick="window.print()">طباعة المعاينة</button>
                @endif
            @else
                <a href="{{ $sourceUrl }}" target="_blank" rel="noopener" class="btn btn-success">فتح الرابط الأصلي</a>
            @endif
        </div>
    </div>

    @if($isLocalFile && $isPdf)
        <div class="form-preview-box">
            <object
                class="form-preview-object"
                data="{{ $inlineUrl }}#toolbar=1&navpanes=0&scrollbar=1"
                type="application/pdf"
                aria-label="معاينة النموذج: {{ $fileName }}"
            >
                <embed
                    class="form-preview-embed"
                    src="{{ $inlineUrl }}#toolbar=1&navpanes=0&scrollbar=1"
                    type="application/pdf"
                >
                <iframe
                    class="form-preview-frame"
                    src="{{ $inlineUrl }}#toolbar=1&navpanes=0&scrollbar=1"
                    title="معاينة النموذج: {{ $fileName }}"
                ></iframe>
            </object>
        </div>
        <div class="form-preview-warning no-print">
            تم ضبط المعاينة لتستخدم رابطاً ينتهي باسم ملف PDF حتى يتعامل معه Chrome مثل فتح الملف المباشر. إذا ظهر زر التنزيل من المتصفح، جرّب زر <strong>فتح كرابط مباشر</strong> للتأكد من أن عارض Chrome يستقبل الملف من مساره الأصلي داخل public/forms.
        </div>
    @elseif($isLocalFile && $isImage)
        <div class="form-preview-box" style="display:grid;place-items:center;padding:16px;overflow:auto;">
            <img src="{{ $inlineUrl }}" alt="{{ $fileName }}" style="max-width:100%;height:auto;border-radius:12px;">
        </div>
    @elseif($isLocalFile)
        <div class="form-preview-message">
            <h3>لا يمكن معاينة هذا النوع داخل النظام</h3>
            <p>لم يتم تنزيل الملف تلقائياً. يمكنك فتحه في تبويب جديد أو تحميله إذا رغبت.</p>
            <div class="form-preview-toolbar" style="justify-content:center;margin-top:12px;">
                <a href="{{ $inlineUrl }}" target="_blank" rel="noopener" class="btn btn-warning">فتح في تبويب جديد</a>
                @if(!empty($publicUrl) && $publicUrl !== $inlineUrl)
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="btn btn-secondary">فتح كرابط مباشر</a>
                @endif
                <a href="{{ $downloadUrl }}" class="btn btn-primary">تحميل النموذج</a>
            </div>
        </div>
    @else
        <div class="form-preview-message">
            <h3>هذا النموذج رابط خارجي أو ملف غير محلي</h3>
            <p>المعاينة الداخلية مخصصة لملفات PDF الموضوعة داخل مجلد <strong>public/forms</strong>.</p>
            <a href="{{ $sourceUrl }}" target="_blank" rel="noopener" class="btn btn-success">فتح الرابط الأصلي</a>
        </div>
    @endif
</div>
@endsection

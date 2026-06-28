<?php
/**
 * DocumentArchive reviewed stability fix
 * - Repairs Arabic words split by previous mojibake repairs: "م\n..." => "م..."
 * - Rebuilds attachment preview with inline, optional preview/download only
 * - Rebuilds QR/print settings page and controller
 * - Rebuilds print reference page to read settings and use compact layout
 * - Adds dark mode fixes for attachment/empty-state cards
 */

$root = dirname(__DIR__);
chdir($root);

$stamp = date('Ymd_His');
$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'reviewed-stability-' . $stamp;
if (!is_dir($backupRoot)) {
    mkdir($backupRoot, 0777, true);
}

function rr_path(string $path): string
{
    global $root;
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function rr_backup(string $relative): void
{
    global $backupRoot;
    $source = rr_path($relative);
    if (!is_file($source)) {
        return;
    }
    $dest = $backupRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    copy($source, $dest);
}

function rr_write(string $relative, string $content): void
{
    rr_backup($relative);
    $path = rr_path($relative);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $content);
}

function rr_append_once(string $relative, string $marker, string $content): bool
{
    $path = rr_path($relative);
    if (!is_file($path)) {
        return false;
    }
    $old = file_get_contents($path);
    if (strpos($old, $marker) !== false) {
        return false;
    }
    rr_backup($relative);
    file_put_contents($path, rtrim($old) . PHP_EOL . PHP_EOL . $content . PHP_EOL);
    return true;
}

function rr_repair_arabic_split_words(): array
{
    $roots = [
        'resources/views',
        'app/Http/Controllers',
        'app/Models',
        'app/Providers',
        'routes',
        'public/js',
        'public/css',
    ];
    $changed = [];
    $scanned = 0;

    foreach ($roots as $folder) {
        $base = rr_path($folder);
        if (!is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $name = basename($path);
            $isBlade = str_ends_with($name, '.blade.php');
            if (!$isBlade && !in_array($ext, ['php', 'js', 'css'], true)) {
                continue;
            }
            $scanned++;
            $old = file_get_contents($path);
            $new = preg_replace('/م(?:\r\n|\r|\n)[ \t]*(?=[\x{0600}-\x{06FF}])/u', 'م', $old);
            if ($new === null) {
                continue;
            }
            if ($new !== $old) {
                $relative = str_replace($GLOBALS['root'] . DIRECTORY_SEPARATOR, '', $path);
                rr_backup($relative);
                file_put_contents($path, $new);
                $changed[] = $relative;
            }
        }
    }

    return [$scanned, $changed];
}

$attachmentPreviewBlade = <<<'BLADE'
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
BLADE;

$printReferenceBlade = <<<'BLADE'
@php
    $doc = $document ?? ($daQrDocument ?? null);
    $docId = data_get($doc, 'id');
    $referenceNumber = data_get($doc, 'reference_number', data_get($doc, 'document_no', ''));
    $departmentName = data_get($doc, 'department.name') ?: data_get($doc, 'department_name') ?: data_get($doc, 'department') ?: 'الشحن والتأمين';
    $referenceDateRaw = data_get($doc, 'reference_date', data_get($doc, 'date', null));

    try {
        $referenceDate = $referenceDateRaw ? \Carbon\Carbon::parse($referenceDateRaw)->format('d/m/Y') : '';
    } catch (\Throwable $e) {
        $referenceDate = (string) $referenceDateRaw;
    }

    $settings = [];
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('qr_print_settings')) {
            $settings = \Illuminate\Support\Facades\DB::table('qr_print_settings')->pluck('value', 'key')->toArray();
        }
    } catch (\Throwable $e) {
        $settings = [];
    }

    $setting = function (string $key, $default = null) use ($settings) {
        return array_key_exists($key, $settings) && $settings[$key] !== '' && $settings[$key] !== null
            ? $settings[$key]
            : $default;
    };

    $boolSetting = function (string $key, bool $default = true) use ($setting) {
        $value = $setting($key, $default ? '1' : '0');
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $bool === null ? ((string) $value !== '0') : $bool;
    };

    $num = function (string $key, $default) use ($setting) {
        return (float) $setting($key, $default);
    };

    $showQr = $boolSetting('qr_enabled', true);
    $showLabel = $boolSetting('qr_label_enabled', true);
    $qrX = $num('qr_x_mm', 70);
    $qrY = $num('qr_y_mm', 28);
    $qrSize = $num('qr_size_mm', 16);
    $qrPadding = $num('qr_card_padding_mm', 1);
    $qrLabelText = trim((string) $setting('qr_label_text', 'رمز الوصول الإلكتروني'));
    $qrLabelFont = $num('qr_label_font_size_mm', 2.1);
    $qrBackground = $boolSetting('qr_background_enabled', true);
    $qrBorder = $boolSetting('qr_show_border', true);

    $blockX = $num('print_block_x_mm', 106);
    $blockY = $num('print_block_y_mm', 28);
    $fontSize = $num('print_font_size_pt', 10.5);
    $departmentFontSize = $num('print_department_font_size_pt', 11);
    $lineHeight = $num('print_line_height', 1.35);
    $labelWidth = $num('print_label_width_mm', 27);
    $colonWidth = $num('print_colon_width_mm', 4);
    $valueWidth = $num('print_value_width_mm', 36);
    $blockWidth = $labelWidth + $colonWidth + $valueWidth + 4;

    $qrUrl = $docId ? url('/documents/' . $docId . '/qr.svg') : '';
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة رقم الكتاب {{ $referenceNumber }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #eef0f4;
            font-family: Tahoma, Arial, sans-serif;
            color: #111827;
        }
        .da-toolbar {
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 10;
            display: flex;
            gap: 8px;
        }
        .da-btn {
            border: 0;
            border-radius: 8px;
            padding: 8px 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
            font-size: 13px;
        }
        .da-btn-print { background: #2563eb; }
        .da-btn-back { background: #6b7280; }
        .da-a4-page {
            width: 210mm;
            height: 297mm;
            margin: 10mm auto;
            background: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 0 0 1px #d1d5db;
        }
        .da-reference-block {
            position: absolute;
            left: {{ $blockX }}mm;
            top: {{ $blockY }}mm;
            width: {{ $blockWidth }}mm;
            font-size: {{ $fontSize }}pt;
            font-weight: 700;
            line-height: {{ $lineHeight }};
            text-align: right;
            direction: rtl;
            color: #111827;
        }
        .da-dept {
            text-align: center;
            margin: 0 0 1.8mm;
            font-size: {{ $departmentFontSize }}pt;
            font-weight: 800;
            line-height: 1.25;
        }
        .da-reference-table {
            border-collapse: collapse;
            width: 100%;
        }
        .da-reference-table th,
        .da-reference-table td {
            padding: 0.6mm 0;
            border: 0;
            background: transparent;
            color: #111827;
            font-size: {{ $fontSize }}pt;
            font-weight: 800;
            line-height: {{ $lineHeight }};
            white-space: nowrap;
        }
        .da-reference-table th {
            width: {{ $labelWidth }}mm;
            text-align: right;
        }
        .da-reference-table .da-colon {
            width: {{ $colonWidth }}mm;
            text-align: center;
        }
        .da-reference-table .da-value {
            width: {{ $valueWidth }}mm;
            text-align: left;
            direction: ltr;
        }
        .da-print-qr-only {
            position: absolute;
            left: {{ $qrX }}mm;
            top: {{ $qrY }}mm;
            width: {{ $qrSize + ($qrPadding * 2) }}mm;
            min-height: {{ $qrSize + ($qrPadding * 2) + 5 }}mm;
            padding: {{ $qrPadding }}mm;
            background: {{ $qrBackground ? '#fff' : 'transparent' }};
            border: {{ $qrBorder ? '0.25mm solid #d1d5db' : '0' }};
            border-radius: 2mm;
            text-align: center;
            direction: rtl;
        }
        .da-print-qr-only img {
            display: block;
            width: {{ $qrSize }}mm;
            height: {{ $qrSize }}mm;
            margin: 0 auto;
            object-fit: contain;
        }
        .da-print-qr-label {
            margin-top: 0.8mm;
            font-size: {{ $qrLabelFont }}mm;
            color: #555;
            line-height: 1.15;
            white-space: nowrap;
            font-weight: 400;
        }
        @media print {
            html, body { background: #fff; }
            .da-toolbar { display: none !important; }
            .da-a4-page { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body class="da-print-reference-page">
    <div class="da-toolbar">
        <button class="da-btn da-btn-print" onclick="window.print()">طباعة</button>
        <a class="da-btn da-btn-back" href="{{ $docId ? url('/documents/' . $docId) : url('/documents') }}">رجوع</a>
    </div>

    <main class="da-a4-page">
        <section class="da-reference-block">
            <div class="da-dept">{{ $departmentName }}</div>
            <table class="da-reference-table">
                <tr>
                    <th>رقم الكتاب</th>
                    <td class="da-colon">:</td>
                    <td class="da-value">{{ $referenceNumber }}</td>
                </tr>
                <tr>
                    <th>تاريخ الكتاب</th>
                    <td class="da-colon">:</td>
                    <td class="da-value">{{ $referenceDate }}</td>
                </tr>
            </table>
        </section>

        @if($showQr && $qrUrl)
            <section class="da-print-qr-only" data-da-print-qr="1">
                <img src="{{ $qrUrl }}" alt="رمز الوصول الإلكتروني">
                @if($showLabel && $qrLabelText !== '')
                    <div class="da-print-qr-label">{{ $qrLabelText }}</div>
                @endif
            </section>
        @endif
    </main>
</body>
</html>
BLADE;

$qrSettingsBlade = <<<'BLADE'
@extends('layouts.app')

@section('title', 'إعدادات الباركود والطباعة')
@section('page_title', 'إعدادات الباركود والطباعة')
@section('page_subtitle', 'ضبط موضع QR وبيانات رقم الكتاب على ورقة A4')

@section('content')
@php
    $s = $settings ?? [];
    $val = fn($key, $default) => old($key, $s[$key] ?? $default);
    $checked = fn($key, $default = '1') => ((string) old($key, $s[$key] ?? $default) === '1');
@endphp

<style>
.print-settings-grid {
    display: grid;
    grid-template-columns: minmax(300px, 440px) 1fr;
    gap: 18px;
    align-items: start;
}
.print-settings-card {
    background: var(--card);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 18px;
    box-shadow: var(--shadow);
}
.print-settings-card h2,
.print-settings-card h3 {
    margin-top: 0;
}
.print-settings-section {
    border-top: 1px solid var(--border);
    padding-top: 14px;
    margin-top: 16px;
}
.print-field {
    margin-bottom: 12px;
}
.print-field label {
    display: block;
    font-weight: 800;
    margin-bottom: 6px;
}
.print-field small {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    line-height: 1.7;
}
.print-checks {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
.print-checks label {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px;
    background: rgba(148, 163, 184, .08);
}
.print-preview-wrap {
    overflow: auto;
}
.print-preview-paper {
    position: relative;
    width: 210mm;
    height: 297mm;
    max-width: 100%;
    aspect-ratio: 210 / 297;
    background: #fff;
    color: #111827;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 10px 35px rgba(0,0,0,.18);
    transform-origin: top right;
}
.print-preview-reference {
    position: absolute;
    left: calc(var(--print-block-x, 106) * 1mm);
    top: calc(var(--print-block-y, 28) * 1mm);
    width: calc((var(--print-label-width, 27) + var(--print-colon-width, 4) + var(--print-value-width, 36) + 4) * 1mm);
    font-weight: 800;
    font-size: calc(var(--print-font-size, 10.5) * 1pt);
    line-height: var(--print-line-height, 1.35);
    direction: rtl;
    text-align: right;
}
.print-preview-reference .dept {
    text-align: center;
    font-size: calc(var(--print-dept-font-size, 11) * 1pt);
    margin-bottom: 1.8mm;
}
.print-preview-reference table { width: 100%; border-collapse: collapse; }
.print-preview-reference th,
.print-preview-reference td {
    border: 0;
    padding: .6mm 0;
    background: transparent;
    color: #111827;
    font-size: inherit;
    white-space: nowrap;
}
.print-preview-reference th { width: calc(var(--print-label-width, 27) * 1mm); text-align: right; }
.print-preview-reference .colon { width: calc(var(--print-colon-width, 4) * 1mm); text-align: center; }
.print-preview-reference .value { width: calc(var(--print-value-width, 36) * 1mm); text-align: left; direction: ltr; }
.print-preview-qr {
    position: absolute;
    left: calc(var(--qr-x, 70) * 1mm);
    top: calc(var(--qr-y, 28) * 1mm);
    width: calc((var(--qr-size, 16) + (var(--qr-padding, 1) * 2)) * 1mm);
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: calc(var(--qr-padding, 1) * 1mm);
    text-align: center;
    box-sizing: border-box;
}
.print-preview-code {
    width: calc(var(--qr-size, 16) * 1mm);
    height: calc(var(--qr-size, 16) * 1mm);
    margin: auto;
    background:
        linear-gradient(90deg,#111 50%,transparent 50%) 0 0 / 4px 4px,
        linear-gradient(#111 50%,transparent 50%) 0 0 / 6px 6px,
        #fff;
    image-rendering: pixelated;
}
.print-preview-label {
    font-size: calc(var(--qr-label-size, 2.1) * 1mm);
    margin-top: 0.8mm;
    color: #64748b;
    white-space: nowrap;
}
@media (max-width: 1000px) {
    .print-settings-grid { grid-template-columns: 1fr; }
}
</style>

<div class="page-header">
    <div>
        <h1>إعدادات الباركود والطباعة</h1>
        <p>هذه القيم تتحكم في صفحة طباعة رقم الكتاب فقط.</p>
    </div>
</div>

<div class="print-settings-grid">
    <form class="print-settings-card" method="POST" action="{{ route('settings.qr-print-position.update') }}">
        @csrf

        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif

        <h2>إعدادات QR</h2>
        <div class="print-checks">
            <label><input type="checkbox" name="qr_enabled" value="1" @checked($checked('qr_enabled'))> إظهار QR</label>
            <label><input type="checkbox" name="qr_label_enabled" value="1" @checked($checked('qr_label_enabled'))> إظهار النص</label>
            <label><input type="checkbox" name="qr_background_enabled" value="1" @checked($checked('qr_background_enabled'))> خلفية بيضاء</label>
            <label><input type="checkbox" name="qr_show_border" value="1" @checked($checked('qr_show_border'))> إطار حول QR</label>
        </div>

        <div class="print-field">
            <label>QR - المسافة من يسار الورقة / mm</label>
            <input class="js-print-preview" data-var="qr-x" type="number" step="0.5" min="0" max="210" name="qr_x_mm" value="{{ $val('qr_x_mm', 70) }}">
        </div>
        <div class="print-field">
            <label>QR - المسافة من أعلى الورقة / mm</label>
            <input class="js-print-preview" data-var="qr-y" type="number" step="0.5" min="0" max="297" name="qr_y_mm" value="{{ $val('qr_y_mm', 28) }}">
        </div>
        <div class="print-field">
            <label>حجم QR / mm</label>
            <input class="js-print-preview" data-var="qr-size" type="number" step="0.5" min="8" max="45" name="qr_size_mm" value="{{ $val('qr_size_mm', 16) }}">
        </div>
        <div class="print-field">
            <label>هامش بطاقة QR / mm</label>
            <input class="js-print-preview" data-var="qr-padding" type="number" step="0.5" min="0" max="8" name="qr_card_padding_mm" value="{{ $val('qr_card_padding_mm', 1) }}">
        </div>
        <div class="print-field">
            <label>نص أسفل QR</label>
            <input type="text" name="qr_label_text" value="{{ $val('qr_label_text', 'رمز الوصول الإلكتروني') }}">
        </div>
        <div class="print-field">
            <label>حجم نص QR / mm</label>
            <input class="js-print-preview" data-var="qr-label-size" type="number" step="0.1" min="1.5" max="6" name="qr_label_font_size_mm" value="{{ $val('qr_label_font_size_mm', 2.1) }}">
        </div>

        <div class="print-settings-section">
            <h2>إعدادات بيانات رقم الكتاب</h2>
            <div class="print-field">
                <label>موضع البيانات من يسار الورقة / mm</label>
                <input class="js-print-preview" data-var="print-block-x" type="number" step="0.5" min="0" max="210" name="print_block_x_mm" value="{{ $val('print_block_x_mm', 106) }}">
                <small>زيادة الرقم تحرك بيانات رقم الكتاب إلى اليمين، وتقليله يحركها إلى اليسار.</small>
            </div>
            <div class="print-field">
                <label>موضع البيانات من أعلى الورقة / mm</label>
                <input class="js-print-preview" data-var="print-block-y" type="number" step="0.5" min="0" max="297" name="print_block_y_mm" value="{{ $val('print_block_y_mm', 28) }}">
            </div>
            <div class="print-field">
                <label>حجم خط رقم الكتاب والتاريخ / pt</label>
                <input class="js-print-preview" data-var="print-font-size" type="number" step="0.5" min="7" max="24" name="print_font_size_pt" value="{{ $val('print_font_size_pt', 10.5) }}">
            </div>
            <div class="print-field">
                <label>حجم خط اسم الإدارة / pt</label>
                <input class="js-print-preview" data-var="print-dept-font-size" type="number" step="0.5" min="7" max="24" name="print_department_font_size_pt" value="{{ $val('print_department_font_size_pt', 11) }}">
            </div>
            <div class="print-field">
                <label>المسافة بين السطور</label>
                <input class="js-print-preview" data-var="print-line-height" type="number" step="0.05" min="1" max="2.5" name="print_line_height" value="{{ $val('print_line_height', 1.35) }}">
            </div>
            <div class="print-field">
                <label>عرض خانة العنوان / mm</label>
                <input class="js-print-preview" data-var="print-label-width" type="number" step="0.5" min="15" max="60" name="print_label_width_mm" value="{{ $val('print_label_width_mm', 27) }}">
            </div>
            <div class="print-field">
                <label>عرض خانة النقطتين / mm</label>
                <input class="js-print-preview" data-var="print-colon-width" type="number" step="0.5" min="2" max="12" name="print_colon_width_mm" value="{{ $val('print_colon_width_mm', 4) }}">
            </div>
            <div class="print-field">
                <label>عرض خانة القيمة / mm</label>
                <input class="js-print-preview" data-var="print-value-width" type="number" step="0.5" min="20" max="80" name="print_value_width_mm" value="{{ $val('print_value_width_mm', 36) }}">
            </div>
        </div>

        <div class="actions" style="margin-top:14px;">
            <button type="submit" class="btn btn-primary">حفظ الإعدادات</button>
            <a href="{{ url('/settings') }}" class="btn btn-secondary">رجوع للإعدادات</a>
        </div>
    </form>

    <div class="print-settings-card print-preview-wrap">
        <h3>معاينة تقريبية على ورقة A4</h3>
        <div id="printPreviewPaper" class="print-preview-paper"
             style="--qr-x: {{ $val('qr_x_mm', 70) }}; --qr-y: {{ $val('qr_y_mm', 28) }}; --qr-size: {{ $val('qr_size_mm', 16) }}; --qr-padding: {{ $val('qr_card_padding_mm', 1) }}; --qr-label-size: {{ $val('qr_label_font_size_mm', 2.1) }}; --print-block-x: {{ $val('print_block_x_mm', 106) }}; --print-block-y: {{ $val('print_block_y_mm', 28) }}; --print-font-size: {{ $val('print_font_size_pt', 10.5) }}; --print-dept-font-size: {{ $val('print_department_font_size_pt', 11) }}; --print-line-height: {{ $val('print_line_height', 1.35) }}; --print-label-width: {{ $val('print_label_width_mm', 27) }}; --print-colon-width: {{ $val('print_colon_width_mm', 4) }}; --print-value-width: {{ $val('print_value_width_mm', 36) }};">
            <div class="print-preview-reference">
                <div class="dept">الجمرك الجوي</div>
                <table>
                    <tr><th>رقم الكتاب</th><td class="colon">:</td><td class="value">251230004</td></tr>
                    <tr><th>تاريخ الكتاب</th><td class="colon">:</td><td class="value">28/06/2026</td></tr>
                </table>
            </div>
            <div class="print-preview-qr">
                <div class="print-preview-code"></div>
                <div class="print-preview-label">رمز الوصول الإلكتروني</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const paper = document.getElementById('printPreviewPaper');
    if (!paper) return;
    document.querySelectorAll('.js-print-preview').forEach(function (input) {
        const update = function () {
            paper.style.setProperty('--' + input.dataset.var, input.value || 0);
        };
        input.addEventListener('input', update);
        update();
    });
})();
</script>
@endsection
BLADE;

$qrController = <<<'PHPCTRL'
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QrPrintSettingsController extends Controller
{
    private array $defaults = [
        'qr_enabled' => '1',
        'qr_x_mm' => '70',
        'qr_y_mm' => '28',
        'qr_size_mm' => '16',
        'qr_label_enabled' => '1',
        'qr_label_text' => 'رمز الوصول الإلكتروني',
        'qr_label_font_size_mm' => '2.1',
        'qr_card_padding_mm' => '1',
        'qr_background_enabled' => '1',
        'qr_show_border' => '1',
        'print_block_x_mm' => '106',
        'print_block_y_mm' => '28',
        'print_font_size_pt' => '10.5',
        'print_department_font_size_pt' => '11',
        'print_line_height' => '1.35',
        'print_label_width_mm' => '27',
        'print_colon_width_mm' => '4',
        'print_value_width_mm' => '36',
    ];

    public function edit()
    {
        return view('settings.qr-print-position', [
            'settings' => $this->settings(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'qr_enabled' => ['nullable', 'boolean'],
            'qr_x_mm' => ['required', 'numeric', 'min:0', 'max:210'],
            'qr_y_mm' => ['required', 'numeric', 'min:0', 'max:297'],
            'qr_size_mm' => ['required', 'numeric', 'min:8', 'max:45'],
            'qr_label_enabled' => ['nullable', 'boolean'],
            'qr_label_text' => ['nullable', 'string', 'max:100'],
            'qr_label_font_size_mm' => ['required', 'numeric', 'min:1.5', 'max:6'],
            'qr_card_padding_mm' => ['required', 'numeric', 'min:0', 'max:8'],
            'qr_background_enabled' => ['nullable', 'boolean'],
            'qr_show_border' => ['nullable', 'boolean'],
            'print_block_x_mm' => ['required', 'numeric', 'min:0', 'max:210'],
            'print_block_y_mm' => ['required', 'numeric', 'min:0', 'max:297'],
            'print_font_size_pt' => ['required', 'numeric', 'min:7', 'max:24'],
            'print_department_font_size_pt' => ['required', 'numeric', 'min:7', 'max:24'],
            'print_line_height' => ['required', 'numeric', 'min:1', 'max:2.5'],
            'print_label_width_mm' => ['required', 'numeric', 'min:15', 'max:60'],
            'print_colon_width_mm' => ['required', 'numeric', 'min:2', 'max:12'],
            'print_value_width_mm' => ['required', 'numeric', 'min:20', 'max:80'],
        ], [], [
            'qr_x_mm' => 'موضع QR من يسار الورقة',
            'qr_y_mm' => 'موضع QR من أعلى الورقة',
            'qr_size_mm' => 'حجم QR',
            'print_block_x_mm' => 'موضع بيانات رقم الكتاب من يسار الورقة',
            'print_block_y_mm' => 'موضع بيانات رقم الكتاب من أعلى الورقة',
            'print_font_size_pt' => 'حجم خط رقم الكتاب والتاريخ',
        ]);

        $data['qr_enabled'] = $request->boolean('qr_enabled') ? '1' : '0';
        $data['qr_label_enabled'] = $request->boolean('qr_label_enabled') ? '1' : '0';
        $data['qr_background_enabled'] = $request->boolean('qr_background_enabled') ? '1' : '0';
        $data['qr_show_border'] = $request->boolean('qr_show_border') ? '1' : '0';
        $data['qr_label_text'] = trim((string) ($data['qr_label_text'] ?? '')) ?: 'رمز الوصول الإلكتروني';

        $this->saveMany($data);

        return redirect()
            ->route('settings.qr-print-position')
            ->with('success', 'تم حفظ إعدادات الباركود والطباعة بنجاح.');
    }

    private function settings(): array
    {
        $settings = $this->defaults;

        if (!Schema::hasTable('qr_print_settings')) {
            return $settings;
        }

        try {
            $rows = DB::table('qr_print_settings')->pluck('value', 'key')->toArray();
            foreach ($rows as $key => $value) {
                if (array_key_exists($key, $settings)) {
                    $settings[$key] = $value;
                }
            }
        } catch (\Throwable $e) {
            return $settings;
        }

        return $settings;
    }

    private function saveMany(array $data): void
    {
        if (!Schema::hasTable('qr_print_settings')) {
            return;
        }

        foreach (array_merge($this->defaults, $data) as $key => $value) {
            DB::table('qr_print_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
PHPCTRL;

$darkCss = <<<'CSS'
/* reviewed-stability-fix:start */
html[data-theme="dark"] .empty-state,
html[data-theme="dark"] .image-preview-wrap,
html[data-theme="dark"] .attachment-preview-shell,
html[data-theme="dark"] .attachment-preview-message-clean {
    background: rgba(15, 23, 42, .86) !important;
    color: var(--text) !important;
    border-color: var(--border) !important;
}

html[data-theme="dark"] .empty-state,
html[data-theme="dark"] .empty-state * {
    color: var(--text) !important;
}

html[data-theme="dark"] .preview-frame,
html[data-theme="dark"] .attachment-preview-frame-clean,
html[data-theme="dark"] .attachment-preview-box-clean {
    border-color: var(--border) !important;
}
/* reviewed-stability-fix:end */
CSS;

[$scanned, $splitChanged] = rr_repair_arabic_split_words();
rr_write('resources/views/attachments/preview.blade.php', $attachmentPreviewBlade);
rr_write('resources/views/documents/preview.blade.php', $attachmentPreviewBlade);
rr_write('resources/views/documents/print-reference.blade.php', $printReferenceBlade);
rr_write('resources/views/settings/qr-print-position.blade.php', $qrSettingsBlade);
rr_write('app/Http/Controllers/QrPrintSettingsController.php', $qrController);
$cssAppended = rr_append_once('public/css/app.css', 'reviewed-stability-fix:start', $darkCss);

// Insert/refresh default print settings directly in the database when available.
$dbUpdated = false;
try {
    $autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $bootstrap = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
    if (!is_file($autoload) || !is_file($bootstrap)) {
        throw new RuntimeException('Laravel bootstrap files are not available.');
    }

    require_once $autoload;
    $app = require_once $bootstrap;
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    if (Illuminate\Support\Facades\Schema::hasTable('qr_print_settings')) {
        $defaults = [
            'qr_enabled' => '1',
            'qr_x_mm' => '70',
            'qr_y_mm' => '28',
            'qr_size_mm' => '16',
            'qr_label_enabled' => '1',
            'qr_label_text' => 'رمز الوصول الإلكتروني',
            'qr_label_font_size_mm' => '2.1',
            'qr_card_padding_mm' => '1',
            'qr_background_enabled' => '1',
            'qr_show_border' => '1',
            'print_block_x_mm' => '106',
            'print_block_y_mm' => '28',
            'print_font_size_pt' => '10.5',
            'print_department_font_size_pt' => '11',
            'print_line_height' => '1.35',
            'print_label_width_mm' => '27',
            'print_colon_width_mm' => '4',
            'print_value_width_mm' => '36',
        ];
        foreach ($defaults as $key => $value) {
            Illuminate\Support\Facades\DB::table('qr_print_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }
        $dbUpdated = true;
    }
} catch (Throwable $e) {
    $dbUpdated = false;
}

echo "DONE: تم تطبيق إصلاح المراجعة المستقر.\n";
echo "Backup: {$backupRoot}\n";
echo "Arabic split scan files: {$scanned}\n";
echo "Arabic split changed files: " . count($splitChanged) . "\n";
echo "Attachment preview rebuilt: yes\n";
echo "Print reference rebuilt: yes\n";
echo "QR/print settings rebuilt: yes\n";
echo "Dark attachment CSS appended: " . ($cssAppended ? 'yes' : 'already exists') . "\n";
echo "Database print defaults updated: " . ($dbUpdated ? 'yes' : 'not available/skipped') . "\n";
echo "NEXT: composer dump-autoload && php artisan view:clear && php artisan optimize:clear\n";

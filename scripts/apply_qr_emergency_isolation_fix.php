<?php
/**
 * Emergency QR isolation fix for DocumentArchive.
 * Goal:
 * - Remove leaked/floating QR from normal pages: dashboard, reports, documents index/show.
 * - Keep QR only inside the print reference page.
 * - Rebuild documents/show and documents/print-reference views safely.
 * - Backup touched files outside app/resources/routes to avoid Composer PSR-4 warnings.
 */

$root = realpath(__DIR__ . '/..');
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    // If script is copied to scripts folder, __DIR__/.. should be root.
    $root = getcwd();
}
if (!file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: لم يتم العثور على ملف artisan. شغّل السكربت من جذر مشروع Laravel.\n");
    exit(1);
}

function path_join(...$parts) {
    return preg_replace('#[\\/]+#', DIRECTORY_SEPARATOR, implode(DIRECTORY_SEPARATOR, $parts));
}

$stamp = date('Ymd_His');
$backupDir = path_join($root, 'storage', 'app', 'private', 'patch-backups', 'qr-emergency-isolation-' . $stamp);
if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true)) {
    fwrite(STDERR, "ERROR: تعذر إنشاء مجلد النسخ الاحتياطي: {$backupDir}\n");
    exit(1);
}

function backup_file($file, $root, $backupDir) {
    if (!file_exists($file)) {
        return;
    }
    $relative = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);
    $dest = path_join($backupDir, $relative);
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    copy($file, $dest);
}

function write_file($file, $content, $root, $backupDir) {
    backup_file($file, $root, $backupDir);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($file, $content);
}

function remove_backup_dirs($base) {
    if (!is_dir($base)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir() && strpos($item->getFilename(), '_backup') === 0) {
            $dir = $item->getPathname();
            $sub = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($sub as $child) {
                $child->isDir() ? @rmdir($child->getPathname()) : @unlink($child->getPathname());
            }
            @rmdir($dir);
        }
    }
}

// Clean old active backup folders that cause Composer warnings.
remove_backup_dirs(path_join($root, 'app'));
remove_backup_dirs(path_join($root, 'resources'));
remove_backup_dirs(path_join($root, 'routes'));

$touched = [];

// 1) Remove QR asset/includes from the global layout, then add a safe leak guard.
$layoutFile = path_join($root, 'resources', 'views', 'layouts', 'app.blade.php');
if (file_exists($layoutFile)) {
    backup_file($layoutFile, $root, $backupDir);
    $layout = file_get_contents($layoutFile);
    $lines = preg_split('/\R/u', $layout);
    $newLines = [];
    foreach ($lines as $line) {
        $lower = mb_strtolower($line, 'UTF-8');
        $isQrAssetLine = (strpos($lower, 'qr') !== false || strpos($lower, 'document-qr') !== false || strpos($lower, 'da-qr') !== false)
            && (strpos($lower, '.css') !== false || strpos($lower, '.js') !== false || strpos($lower, 'asset(') !== false);
        $isQrFloatingLine = strpos($lower, 'document-qr-card') !== false
            || strpos($lower, 'document-print-qr') !== false
            || strpos($lower, 'qr-print-position') !== false
            || strpos($lower, 'da-qr') !== false;
        if ($isQrAssetLine || $isQrFloatingLine) {
            continue;
        }
        $newLines[] = $line;
    }
    $layout = implode("\n", $newLines);

    // Add a minimal guard CSS only once before </head> or after first style area.
    $guardLink = "<link rel=\"stylesheet\" href=\"{{ asset('css/qr-leak-guard.css') }}?v=qr-emergency-v1\">";
    if (strpos($layout, 'qr-leak-guard.css') === false) {
        if (stripos($layout, '</head>') !== false) {
            $layout = preg_replace('/<\/head>/i', "    {$guardLink}\n</head>", $layout, 1);
        } else {
            $layout = $guardLink . "\n" . $layout;
        }
    }
    file_put_contents($layoutFile, $layout);
    $touched[] = 'resources/views/layouts/app.blade.php';
}

// 2) Global QR leak guard: hide only document QR leaks outside print reference page.
$guardCss = <<<CSS
/* Emergency QR leak guard. QR must only appear inside .da-print-reference-page. */
body:not(.da-print-reference-page) .document-qr-card,
body:not(.da-print-reference-page) .document-print-qr,
body:not(.da-print-reference-page) .da-document-print-qr,
body:not(.da-print-reference-page) .da-qr-print-box,
body:not(.da-print-reference-page) .da-qr-print-wrapper,
body:not(.da-print-reference-page) img[src*="/qr.svg"],
body:not(.da-print-reference-page) iframe[src*="/qr.svg"],
body:not(.da-print-reference-page) object[data*="/qr.svg"] {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    width: 0 !important;
    height: 0 !important;
    overflow: hidden !important;
    pointer-events: none !important;
}
@media print {
    body:not(.da-print-reference-page) .document-qr-card,
    body:not(.da-print-reference-page) .document-print-qr,
    body:not(.da-print-reference-page) .da-document-print-qr,
    body:not(.da-print-reference-page) img[src*="/qr.svg"] {
        display: none !important;
    }
}
CSS;
write_file(path_join($root, 'public', 'css', 'qr-leak-guard.css'), $guardCss, $root, $backupDir);
$touched[] = 'public/css/qr-leak-guard.css';

// 3) Replace document show page with clean safe view; no QR at all.
$showFile = path_join($root, 'resources', 'views', 'documents', 'show.blade.php');
if (file_exists(path_join($root, 'resources', 'views', 'documents'))) {
$showView = <<<'BLADE'
@extends('layouts.app')

@section('title', 'عرض الكتاب')

@section('content')
@php
    $value = function ($row, string $key, $default = '-') {
        $v = data_get($row, $key);
        return ($v === null || $v === '') ? $default : $v;
    };
    $docId = $value($document ?? null, 'id', null);
    $attachmentsList = collect(data_get($document ?? null, 'attachments', $attachments ?? []));
@endphp

<div class="page-header">
    <div>
        <h1>عرض الكتاب</h1>
        <p>بيانات الكتاب، البوالص، المرفقات، وسجل الحركة.</p>
    </div>
    <div class="page-actions no-print" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <a href="{{ url('/documents') }}" class="btn btn-light">رجوع</a>
        @if($docId)
            <a href="{{ url('/documents/'.$docId.'/edit') }}" class="btn btn-primary">تعديل</a>
            <a href="{{ url('/documents/'.$docId.'/activity') }}" class="btn btn-info">سجل الحركة</a>
            <a href="{{ url('/documents/'.$docId.'/print-reference') }}" class="btn btn-warning">طباعة رقم الكتاب</a>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>رقم الكتاب: {{ $value($document ?? null, 'reference_number') }}</h2>
    </div>
    <div class="table-responsive">
        <table class="table details-table">
            <tbody>
                <tr><th>تاريخ الكتاب</th><td>{{ optional($value($document ?? null, 'reference_date', null))->format('Y-m-d') ?? $value($document ?? null, 'reference_date') }}</td></tr>
                <tr><th>موضوع الكتاب</th><td>{{ $value($document ?? null, 'subject') }}</td></tr>
                <tr><th>عنوان الكتاب</th><td>{{ $value($document ?? null, 'title') }}</td></tr>
                <tr><th>البوليصة الرئيسية</th><td>{{ $value($document ?? null, 'main_policy_number') }}</td></tr>
                <tr><th>البوليصة الفرعية</th><td>{{ $value($document ?? null, 'sub_policy_number') }}</td></tr>
                <tr><th>الإدارة</th><td>{{ $value($document ?? null, 'department.name', $value($document ?? null, 'department_name')) }}</td></tr>
                <tr><th>نوع الكتاب</th><td>{{ $value($document ?? null, 'documentType.name', $value($document ?? null, 'document_type_name')) }}</td></tr>
                <tr><th>المرسل</th><td>{{ $value($document ?? null, 'sender') }}</td></tr>
                <tr><th>المستلم</th><td>{{ $value($document ?? null, 'recipient') }}</td></tr>
                <tr><th>الحالة</th><td>{{ $value($document ?? null, 'status') }}</td></tr>
                <tr><th>درجة السرية</th><td>{{ $value($document ?? null, 'confidentiality') }}</td></tr>
                <tr><th>الأولوية</th><td>{{ $value($document ?? null, 'priority') }}</td></tr>
                <tr><th>الوصف</th><td>{{ $value($document ?? null, 'description') }}</td></tr>
                <tr><th>الملاحظات</th><td>{{ $value($document ?? null, 'notes') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><h2>المرفقات</h2></div>
    @if($attachmentsList->count())
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>اسم الملف</th>
                        <th>النوع</th>
                        <th>الحجم</th>
                        <th class="no-print">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attachmentsList as $attachment)
                        @php $attId = data_get($attachment, 'id'); @endphp
                        <tr>
                            <td>{{ data_get($attachment, 'original_name', data_get($attachment, 'file_name', 'مرفق')) }}</td>
                            <td>{{ data_get($attachment, 'mime_type', '-') }}</td>
                            <td>{{ data_get($attachment, 'size_human', data_get($attachment, 'file_size', '-')) }}</td>
                            <td class="no-print">
                                @if($attId)
                                    <a class="btn btn-sm btn-light" href="{{ url('/attachments/'.$attId.'/preview') }}">معاينة</a>
                                    <a class="btn btn-sm btn-primary" href="{{ url('/attachments/'.$attId.'/download') }}">تنزيل</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="empty-state">لا توجد مرفقات.</p>
    @endif
</div>
@endsection
BLADE;
write_file($showFile, $showView, $root, $backupDir);
$touched[] = 'resources/views/documents/show.blade.php';
}

// 4) Replace print-reference page with a clean isolated page and QR settings binding.
$printFile = path_join($root, 'resources', 'views', 'documents', 'print-reference.blade.php');
$printView = <<<'BLADE'
@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $value = function ($row, string $key, $default = '-') {
        $v = data_get($row, $key);
        return ($v === null || $v === '') ? $default : $v;
    };

    $settings = [];
    try {
        if (Schema::hasTable('qr_print_settings')) {
            $settings = DB::table('qr_print_settings')->pluck('value', 'key')->toArray();
        }
    } catch (Throwable $e) {
        $settings = [];
    }

    $boolValue = function ($key, $default = true) use ($settings) {
        if (!array_key_exists($key, $settings)) return $default;
        $v = $settings[$key];
        if (is_bool($v)) return $v;
        return in_array(strtolower((string)$v), ['1','true','yes','on','نعم'], true);
    };

    $numValue = function ($key, $default) use ($settings) {
        return is_numeric($settings[$key] ?? null) ? (float)$settings[$key] : (float)$default;
    };

    $docId = $value($document ?? null, 'id', null);
    $referenceNumber = $value($document ?? null, 'reference_number');
    $referenceDateRaw = $value($document ?? null, 'reference_date', null);
    try {
        $referenceDate = $referenceDateRaw ? \Carbon\Carbon::parse($referenceDateRaw)->format('d/m/Y') : '-';
    } catch (Throwable $e) {
        $referenceDate = $referenceDateRaw ?: '-';
    }

    $showQr = $boolValue('show_qr', true);
    $showQrLabel = $boolValue('show_label', true);
    $qrX = $numValue('x', 55);
    $qrY = $numValue('y', 48);
    $qrSize = $numValue('size', 16);
    $qrPadding = $numValue('padding', 1);
    $labelSize = $numValue('label_size', 7);
    $qrLabel = trim((string)($settings['label'] ?? 'رمز الوصول الإلكتروني')) ?: 'رمز الوصول الإلكتروني';
    $qrUrl = $docId ? url('/documents/'.$docId.'/qr.svg') : null;
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة رقم الكتاب {{ $referenceNumber }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        html, body {
            margin: 0;
            padding: 0;
            background: #f3f4f6;
            font-family: Tahoma, Arial, sans-serif;
            color: #000;
        }
        body.da-print-reference-page { direction: rtl; }
        .print-toolbar {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 50;
            display: flex;
            gap: 8px;
        }
        .print-toolbar a,
        .print-toolbar button {
            border: 0;
            border-radius: 8px;
            padding: 8px 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-print { background: #2563eb; color: #fff; }
        .btn-back { background: #6b7280; color: #fff; }
        .a4-sheet {
            position: relative;
            width: 210mm;
            height: 297mm;
            margin: 8mm auto;
            background: #fff;
            box-shadow: 0 0 0 1px #d1d5db;
            overflow: hidden;
        }
        .reference-block {
            position: absolute;
            top: 48mm;
            right: 58mm;
            font-size: 14px;
            line-height: 1.9;
            font-weight: 700;
            white-space: nowrap;
        }
        .reference-title {
            text-align: center;
            margin-bottom: 5px;
            font-size: 13px;
        }
        .reference-row {
            display: grid;
            grid-template-columns: 30mm 7mm 36mm;
            column-gap: 2mm;
            align-items: baseline;
        }
        .reference-row .label { text-align: right; }
        .reference-row .colon { text-align: center; }
        .reference-row .value { text-align: left; direction: ltr; }
        .qr-box {
            position: absolute;
            left: {{ $qrX }}mm;
            top: {{ $qrY }}mm;
            width: {{ $qrSize + ($qrPadding * 2) }}mm;
            text-align: center;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 2mm;
            padding: {{ $qrPadding }}mm;
            box-sizing: border-box;
        }
        .qr-box img {
            display: block;
            width: {{ $qrSize }}mm;
            height: {{ $qrSize }}mm;
            margin: 0 auto;
        }
        .qr-label {
            margin-top: 1mm;
            font-size: {{ $labelSize }}pt;
            line-height: 1.2;
            color: #374151;
            white-space: nowrap;
        }
        @media print {
            html, body { background: #fff; }
            .print-toolbar { display: none !important; }
            .a4-sheet {
                margin: 0;
                box-shadow: none;
                width: 210mm;
                height: 297mm;
            }
        }
    </style>
</head>
<body class="da-print-reference-page">
    <div class="print-toolbar no-print">
        <button class="btn-print" onclick="window.print()">طباعة</button>
        @if($docId)
            <a class="btn-back" href="{{ url('/documents/'.$docId) }}">رجوع</a>
        @else
            <a class="btn-back" href="{{ url('/documents') }}">رجوع</a>
        @endif
    </div>

    <div class="a4-sheet">
        <div class="reference-block">
            <div class="reference-title">الشحن والتأمين</div>
            <div class="reference-row">
                <span class="label">رقم الكتاب</span><span class="colon">:</span><span class="value">{{ $referenceNumber }}</span>
            </div>
            <div class="reference-row">
                <span class="label">تاريخ الكتاب</span><span class="colon">:</span><span class="value">{{ $referenceDate }}</span>
            </div>
        </div>

        @if($showQr && $qrUrl)
            <div class="qr-box">
                <img src="{{ $qrUrl }}" alt="QR">
                @if($showQrLabel)
                    <div class="qr-label">{{ $qrLabel }}</div>
                @endif
            </div>
        @endif
    </div>
</body>
</html>
BLADE;
write_file($printFile, $printView, $root, $backupDir);
$touched[] = 'resources/views/documents/print-reference.blade.php';

// 5) Remove QR leak lines from common pages that should never have floating QR.
$commonFiles = [
    path_join($root, 'resources', 'views', 'dashboard', 'index.blade.php'),
    path_join($root, 'resources', 'views', 'reports', 'index.blade.php'),
    path_join($root, 'resources', 'views', 'documents', 'index.blade.php'),
];
foreach ($commonFiles as $file) {
    if (!file_exists($file)) continue;
    backup_file($file, $root, $backupDir);
    $content = file_get_contents($file);
    $lines = preg_split('/\R/u', $content);
    $newLines = [];
    foreach ($lines as $line) {
        $lower = mb_strtolower($line, 'UTF-8');
        if (
            strpos($lower, 'document-qr-card') !== false ||
            strpos($lower, 'document-print-qr') !== false ||
            strpos($lower, 'da-qr') !== false ||
            strpos($lower, '/qr.svg') !== false ||
            strpos($lower, 'qr-print-position') !== false
        ) {
            continue;
        }
        $newLines[] = $line;
    }
    file_put_contents($file, implode("\n", $newLines));
    $touched[] = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);
}

// 6) Remove old QR CSS/JS assets except guard; not required but prevents accidental includes.
foreach ([path_join($root, 'public', 'css'), path_join($root, 'public', 'js')] as $assetDir) {
    if (!is_dir($assetDir)) continue;
    foreach (glob($assetDir . DIRECTORY_SEPARATOR . '*qr*') ?: [] as $asset) {
        if (basename($asset) === 'qr-leak-guard.css') continue;
        if (is_file($asset)) {
            backup_file($asset, $root, $backupDir);
            @unlink($asset);
            $touched[] = ltrim(str_replace($root, '', $asset), DIRECTORY_SEPARATOR) . ' (deleted)';
        }
    }
}

// Deduplicate touched list.
$touched = array_values(array_unique($touched));

echo "DONE: تم عزل QR وإصلاح صفحة عرض الكتاب وصفحة طباعة رقم الكتاب.\n";
echo "Backup: {$backupDir}\n";
echo "Files touched:\n";
foreach ($touched as $item) {
    echo "- {$item}\n";
}
echo "NEXT: php artisan view:clear && php artisan optimize:clear && php artisan serve\n";

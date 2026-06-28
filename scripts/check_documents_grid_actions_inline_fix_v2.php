<?php

$root = realpath(__DIR__ . '/..');
$errors = [];

if (!$root) {
    fwrite(STDERR, "ERROR: تعذر تحديد مسار المشروع.\n");
    exit(1);
}

$css = $root . '/public/css/documents-grid-actions-fix.css';
$js = $root . '/public/js/documents-grid-actions-fix.js';

if (!is_file($css)) {
    $errors[] = 'ملف CSS غير موجود: public/css/documents-grid-actions-fix.css';
}
if (!is_file($js)) {
    $errors[] = 'ملف JS غير موجود: public/js/documents-grid-actions-fix.js';
}

$layoutDir = $root . '/resources/views/layouts';
$layouts = is_dir($layoutDir) ? (glob($layoutDir . '/*.blade.php') ?: []) : [];
$activeLayouts = array_values(array_filter($layouts, static function ($file) {
    return strpos($file, DIRECTORY_SEPARATOR . '_backup') === false;
}));

$hasCssLink = false;
$hasJsLink = false;
foreach ($activeLayouts as $file) {
    $content = file_get_contents($file) ?: '';
    if (strpos($content, "documents-grid-actions-fix.css") !== false) {
        $hasCssLink = true;
    }
    if (strpos($content, "documents-grid-actions-fix.js") !== false) {
        $hasJsLink = true;
    }
}

if (!$hasCssLink) {
    $errors[] = 'رابط CSS غير موجود داخل ملفات layout.';
}
if (!$hasJsLink) {
    $errors[] = 'رابط JS غير موجود داخل ملفات layout.';
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح أزرار جدول الكتب V2.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: إصلاح أزرار جدول الكتب V2 مكتمل.\n";
echo "- CSS موجود.\n";
echo "- JS موجود.\n";
echo "- روابط layout موجودة.\n";

<?php

$root = dirname(__DIR__);
$errors = [];

$css = $root . '/public/css/documents-grid-actions-fix.css';
$js = $root . '/public/js/documents-grid-actions-fix.js';

if (!is_file($css)) {
    $errors[] = 'ملف CSS غير موجود: public/css/documents-grid-actions-fix.css';
}

if (!is_file($js)) {
    $errors[] = 'ملف JS غير موجود: public/js/documents-grid-actions-fix.js';
}

$layoutsDir = $root . '/resources/views/layouts';
$hasCssLink = false;
$hasJsLink = false;

if (is_dir($layoutsDir)) {
    foreach (glob($layoutsDir . '/*.blade.php') ?: [] as $file) {
        $content = file_get_contents($file) ?: '';
        if (str_contains($content, 'documents-grid-actions-fix.css')) {
            $hasCssLink = true;
        }
        if (str_contains($content, 'documents-grid-actions-fix.js')) {
            $hasJsLink = true;
        }
    }
}

if (!$hasCssLink) {
    $errors[] = 'رابط CSS غير موجود داخل ملفات layout.';
}

if (!$hasJsLink) {
    $errors[] = 'رابط JS غير موجود داخل ملفات layout.';
}

if ($errors) {
    echo 'ERROR: لم يكتمل إصلاح أزرار جدول الكتب.' . PHP_EOL;
    foreach ($errors as $error) {
        echo '- ' . $error . PHP_EOL;
    }
    exit(1);
}

echo 'OK: إصلاح أزرار إجراءات جدول الكتب مركب بنجاح.' . PHP_EOL;

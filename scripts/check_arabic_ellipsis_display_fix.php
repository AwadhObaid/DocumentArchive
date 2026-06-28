<?php
$root = dirname(__DIR__);
$layout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
$cssPath = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'arabic-ellipsis-display-fix.css';
$jsPath = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'arabic-ellipsis-display-fix.js';

$errors = [];
if (!is_file($layout)) $errors[] = 'ملف layout غير موجود.';
if (!is_file($cssPath)) $errors[] = 'ملف CSS غير موجود: public/css/arabic-ellipsis-display-fix.css';
if (!is_file($jsPath)) $errors[] = 'ملف JS غير موجود: public/js/arabic-ellipsis-display-fix.js';

$layoutContent = is_file($layout) ? file_get_contents($layout) : '';
if (!str_contains($layoutContent, 'arabic-ellipsis-display-fix.css')) $errors[] = 'رابط CSS غير مضاف داخل layout.';
if (!str_contains($layoutContent, 'arabic-ellipsis-display-fix.js')) $errors[] = 'رابط JS غير مضاف داخل layout.';

$css = is_file($cssPath) ? file_get_contents($cssPath) : '';
if (!str_contains($css, 'text-overflow: clip !important')) $errors[] = 'قاعدة منع text-overflow غير موجودة في CSS.';
if (!str_contains($css, 'white-space: normal !important')) $errors[] = 'قاعدة white-space غير موجودة في CSS.';

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح عرض الكلمات العربية.\n";
    foreach ($errors as $e) echo "- {$e}\n";
    exit(1);
}

echo "OK: تم تفعيل إصلاح منع اختصار الكلمات العربية بالنقاط.\n";
echo "افتح المتصفح واضغط Ctrl + F5.\n";

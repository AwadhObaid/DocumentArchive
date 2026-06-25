<?php
$root = dirname(__DIR__);
$css = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'reports-dark-mode-fix.css';
$js  = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'reports-dark-mode-fix.js';
$layouts = [
    $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php',
    $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'admin.blade.php',
];
$errors = [];
if (!is_file($css)) $errors[] = 'ملف CSS غير موجود: public/css/reports-dark-mode-fix.css';
if (!is_file($js)) $errors[] = 'ملف JS غير موجود: public/js/reports-dark-mode-fix.js';
$layoutFound = false;
$linked = false;
foreach ($layouts as $layout) {
    if (is_file($layout)) {
        $layoutFound = true;
        $content = file_get_contents($layout);
        if (strpos($content, 'reports-dark-mode-fix.css') !== false && strpos($content, 'reports-dark-mode-fix.js') !== false) {
            $linked = true;
        }
    }
}
if (!$layoutFound) $errors[] = 'لم يتم العثور على layout داخل resources/views/layouts';
if ($layoutFound && !$linked) $errors[] = 'ملفات الإصلاح غير مربوطة داخل layout';

if ($errors) {
    echo "ERROR: إصلاح التقارير في الوضع الليلي غير مكتمل.\n";
    foreach ($errors as $error) echo "- {$error}\n";
    exit(1);
}
echo "OK: إصلاح بطاقات التقارير في الوضع الليلي مركب بنجاح.\n";

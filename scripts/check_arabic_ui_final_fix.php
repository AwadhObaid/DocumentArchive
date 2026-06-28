<?php
$root = realpath(__DIR__ . '/..');
$required = [
    'public/css/arabic-ui-final-fix.css',
    'public/js/arabic-ui-final-fix.js',
    'resources/views/layouts/app.blade.php',
];
$errors = [];
foreach ($required as $rel) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) $errors[] = "Missing: $rel";
}
$layout = $root . DIRECTORY_SEPARATOR . 'resources/views/layouts/app.blade.php';
$layout = str_replace('/', DIRECTORY_SEPARATOR, $layout);
if (is_file($layout)) {
    $content = file_get_contents($layout);
    foreach (['arabic-ui-final-fix.css', 'arabic-ui-final-fix.js'] as $needle) {
        if (strpos($content, $needle) === false) $errors[] = "Layout does not include $needle";
    }
}
if ($errors) {
    echo "ERROR: لم يكتمل إصلاح عرض النصوص العربية.\n";
    foreach ($errors as $e) echo "- $e\n";
    exit(1);
}
echo "OK: تم تثبيت CSS/JS الخاص بإصلاح عرض النصوص العربية.\n";

<?php

$root = realpath(__DIR__ . '/..');
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    $root = getcwd();
}
if (!file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: شغّل السكربت من داخل جذر مشروع Laravel.\n");
    exit(1);
}

$view = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'qr-print-position.blade.php';
$errors = [];
if (!file_exists($view)) {
    $errors[] = 'الملف غير موجود: resources/views/settings/qr-print-position.blade.php';
} else {
    $content = file_get_contents($view);
    if (strpos($content, "route('settings.index')") !== false && strpos($content, "Route::has('settings.index')") === false) {
        $errors[] = "ما زال الملف يستدعي route('settings.index') بدون تحقق من وجود المسار.";
    }
    if (strpos($content, "Route::has('settings.index')") === false && strpos($content, "url('/settings')") === false) {
        $errors[] = "لم يتم العثور على رابط رجوع آمن إلى الإعدادات.";
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح رابط إعدادات QR.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: إصلاح رابط صفحة إعدادات QR مكتمل.\n";

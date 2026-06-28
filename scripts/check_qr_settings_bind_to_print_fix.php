<?php
$root = getcwd();
$viewsRoot = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
$errors = [];
$found = false;
if (is_dir($viewsRoot)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fileInfo) {
        if (!$fileInfo->isFile() || !str_ends_with($fileInfo->getFilename(), '.blade.php')) continue;
        $content = file_get_contents($fileInfo->getPathname());
        if (str_contains($content, 'QR_PRINT_SETTINGS_BIND_START') && str_contains($content, 'QR_PRINT_SETTINGS_MARKUP_START')) {
            $found = true;
            if (!str_contains($content, 'qr_print_settings')) $errors[] = 'ملف الطباعة لا يقرأ جدول qr_print_settings.';
            if (!str_contains($content, 'da-qr-from-settings')) $errors[] = 'كلاس QR الجديد غير موجود.';
            if (!str_contains($content, "url('/documents/' . $daQrDocument->id . '/qr.svg')")) $errors[] = 'رابط QR الديناميكي غير موجود.';
        }
    }
}
if (!$found) $errors[] = 'لم يتم العثور على ربط إعدادات QR داخل صفحة طباعة الكتاب.';
$qrSettingsView = $viewsRoot . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'qr-print-position.blade.php';
if (file_exists($qrSettingsView) && str_contains(file_get_contents($qrSettingsView), "route('settings.index')")) {
    $errors[] = 'ما زال route(settings.index) موجوداً داخل صفحة إعدادات QR.';
}
if ($errors) {
    echo "ERROR: لم يكتمل ربط إعدادات QR بالطباعة.\n";
    foreach ($errors as $e) echo "- {$e}\n";
    exit(1);
}
echo "OK: إعدادات موضع QR مرتبطة الآن بصفحة طباعة رقم الكتاب.\n";

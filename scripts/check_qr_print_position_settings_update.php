<?php
declare(strict_types=1);

$root = getcwd();
$errors = [];

$required = [
    'app/Services/QrPrintLayoutSettings.php',
    'app/Http/Controllers/QrPrintSettingsController.php',
    'resources/views/settings/qr-print-position.blade.php',
    'public/css/document-qr-print-calibration.css',
    'database/migrations/2026_06_28_000060_create_qr_print_settings_table.php',
];

foreach ($required as $file) {
    if (!file_exists($root . DIRECTORY_SEPARATOR . $file)) {
        $errors[] = "الملف غير موجود: {$file}";
    }
}

$web = $root . '/routes/web.php';
if (!file_exists($web) || !str_contains((string) file_get_contents($web), 'settings.qr-print-position')) {
    $errors[] = 'مسار إعدادات موضع QR غير موجود داخل routes/web.php';
}

$printFiles = [
    'resources/views/documents/print-reference.blade.php',
    'resources/views/documents/print_reference.blade.php',
    'resources/views/documents/print.blade.php',
];

$foundPrintHook = false;
foreach ($printFiles as $file) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    if (file_exists($path) && str_contains((string) file_get_contents($path), 'document-qr-print-calibration.css')) {
        $foundPrintHook = true;
        break;
    }
}
if (!$foundPrintHook) {
    $errors[] = 'لم يتم العثور على ربط CSS داخل ملف طباعة رقم الكتاب.';
}

if ($errors) {
    echo "ERROR: لم يكتمل تحديث إعدادات موضع QR.\n";
    foreach ($errors as $e) {
        echo "- {$e}\n";
    }
    exit(1);
}

echo "OK: تحديث إعدادات موضع QR مكتمل.\n";
echo "افتح: http://127.0.0.1:8000/settings/qr-print-position\n";

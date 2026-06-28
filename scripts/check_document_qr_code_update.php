<?php

declare(strict_types=1);

$root = getcwd();
$errors = [];

$required = [
    'app/Services/SimpleQrCodeSvg.php',
    'app/Http/Controllers/DocumentQrController.php',
    'routes/web.php',
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = "الملف غير موجود: {$file}";
    }
}

$routes = is_file($root . '/routes/web.php') ? file_get_contents($root . '/routes/web.php') : '';
if (!str_contains($routes, 'documents.qr')) {
    $errors[] = 'مسار documents.qr غير موجود داخل routes/web.php';
}
if (!str_contains($routes, 'DocumentQrController')) {
    $errors[] = 'DocumentQrController غير مسجل داخل routes/web.php';
}

$show = is_file($root . '/resources/views/documents/show.blade.php') ? file_get_contents($root . '/resources/views/documents/show.blade.php') : '';
if (!str_contains($show, 'documents.qr')) {
    $errors[] = 'QR Code غير مضاف داخل صفحة عرض الكتاب show.blade.php';
}

$printHasQr = false;
foreach ([
    'resources/views/documents/print-reference.blade.php',
    'resources/views/documents/print_number.blade.php',
    'resources/views/documents/print-number.blade.php',
    'resources/views/documents/print.blade.php',
] as $view) {
    if (is_file($root . '/' . $view) && str_contains(file_get_contents($root . '/' . $view), 'documents.qr')) {
        $printHasQr = true;
    }
}
if (!$printHasQr) {
    $errors[] = 'لم يتم العثور على QR Code داخل أي صفحة طباعة للكتاب. إذا لم تكن صفحة الطباعة موجودة فهذا تنبيه يحتاج مراجعة اسم الملف.';
}

if ($errors) {
    echo "ERROR: لم يكتمل تحديث QR Code.\n";
    foreach ($errors as $e) {
        echo "- {$e}\n";
    }
    exit(1);
}

echo "OK: تحديث QR Code للكتب مكتمل.\n";

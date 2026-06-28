<?php
$root = realpath(__DIR__ . '/..');
$errors = [];
if (!$root || !file_exists($root . '/artisan')) {
    $errors[] = 'شغّل الفحص من جذر مشروع Laravel.';
}

$required = [
    'public/css/document-qr-print-only-cleanup.css',
    'public/js/document-qr-print-only-cleanup.js',
    'resources/views/documents/partials/qr-print-position.blade.php',
];
foreach ($required as $rel) {
    if (!file_exists($root . '/' . $rel)) {
        $errors[] = "الملف غير موجود: {$rel}";
    }
}

$layoutOk = false;
foreach (['resources/views/layouts/app.blade.php','resources/views/layouts/admin.blade.php','resources/views/layouts/main.blade.php'] as $rel) {
    $p = $root . '/' . $rel;
    if (file_exists($p)) {
        $c = file_get_contents($p);
        if (str_contains($c, 'document-qr-print-only-cleanup.css') && str_contains($c, 'document-qr-print-only-cleanup.js')) {
            $layoutOk = true;
        }
    }
}
if (!$layoutOk) {
    $errors[] = 'روابط CSS/JS الخاصة بتنظيف QR غير موجودة في layout.';
}

$printIncludeOk = false;
$dir = $root . '/resources/views/documents';
if (is_dir($dir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $rel = str_replace($root . '/', '', str_replace('\\', '/', $file->getPathname()));
        if (str_contains($rel, '_backup')) continue;
        $name = strtolower($file->getFilename());
        if (str_ends_with($name, '.blade.php') && (str_contains($name, 'print') || str_contains($name, 'reference'))) {
            $c = file_get_contents($file->getPathname());
            if (str_contains($c, 'documents.partials.qr-print-position')) {
                $printIncludeOk = true;
                break;
            }
        }
    }
}
if (!$printIncludeOk) {
    $errors[] = 'ملف الطباعة لا يحتوي include الخاص بموضع QR.';
}

foreach (['app','resources','routes'] as $base) {
    $path = $root . '/' . $base;
    if (!is_dir($path)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $item) {
        if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
            $errors[] = 'ما زالت توجد مجلدات backup داخل مسارات Laravel: ' . str_replace($root . '/', '', str_replace('\\', '/', $item->getPathname()));
            break 2;
        }
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح QR print-only.\n";
    foreach ($errors as $e) echo "- {$e}\n";
    exit(1);
}

echo "OK: إصلاح QR print-only مكتمل. افتح صفحة عرض الكتاب للتأكد من اختفاء التداخل، ثم جرّب صفحة طباعة الرقم.\n";

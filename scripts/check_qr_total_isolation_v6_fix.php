<?php
function root_path_v6_check(): string
{
    $dir = realpath(__DIR__ . '/..');
    if (!$dir || !file_exists($dir . DIRECTORY_SEPARATOR . 'artisan')) {
        fwrite(STDERR, "ERROR: شغّل الفحص من جذر مشروع Laravel.\n");
        exit(1);
    }
    return $dir;
}
$root = root_path_v6_check();
$errors = [];

$layout = $root . '/resources/views/layouts/app.blade.php';
if (!file_exists($layout) || !str_contains(file_get_contents($layout), 'qr-total-isolation-v6.css') || !str_contains(file_get_contents($layout), 'qr-total-isolation-v6.js')) {
    $errors[] = 'روابط CSS/JS الخاصة بعزل QR غير موجودة في layout.';
}

$provider = $root . '/app/Providers/AppServiceProvider.php';
if (!file_exists($provider) || !str_contains(file_get_contents($provider), "View::share('daQrUrl'")) {
    $errors[] = 'متغيرات QR الاحتياطية غير مضافة إلى AppServiceProvider.';
}

$print = $root . '/resources/views/documents/print-reference.blade.php';
if (!file_exists($print)) {
    $errors[] = 'ملف طباعة رقم الكتاب غير موجود.';
} else {
    $p = file_get_contents($print);
    foreach (['da-print-reference-page', 'qr_print_settings', 'data-da-print-qr', '/qr.svg'] as $needle) {
        if (!str_contains($p, $needle)) $errors[] = "ملف الطباعة لا يحتوي: {$needle}";
    }
}

foreach (['resources/views/reports/index.blade.php', 'resources/views/documents/show.blade.php', 'resources/views/documents/index.blade.php', 'resources/views/dashboard/index.blade.php'] as $rel) {
    $file = $root . '/' . $rel;
    if (!file_exists($file)) continue;
    $c = file_get_contents($file);
    foreach (['$daQrUrl', '$daQrDocument', 'document-qr-card', 'data-da-print-qr'] as $bad) {
        if (str_contains($c, $bad)) {
            $errors[] = "ما زال يوجد أثر QR داخل {$rel}: {$bad}";
        }
    }
}

foreach (['app','resources','routes'] as $folder) {
    $base = $root . '/' . $folder;
    if (!is_dir($base)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isDir() && str_starts_with($f->getFilename(), '_backup')) {
            $errors[] = 'ما زال يوجد مجلد backup داخل مسارات Laravel: ' . $f->getPathname();
        }
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل عزل QR V6.\n";
    foreach ($errors as $e) echo "- {$e}\n";
    exit(1);
}

echo "OK: تم عزل QR. يجب أن يظهر فقط في /documents/{id}/print-reference.\n";

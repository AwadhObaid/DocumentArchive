<?php
function failCheck(array $errors): void {
    echo "ERROR: لم يكتمل تنظيف QR من صفحة عرض الكتاب V5.\n";
    foreach ($errors as $e) echo "- {$e}\n";
    exit(1);
}
function projectRoot(): string {
    $dir = getcwd();
    for ($i = 0; $i < 8; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan')) return $dir;
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    return getcwd();
}
$root = projectRoot();
$errors = [];
$show = $root . DIRECTORY_SEPARATOR . 'resources/views/documents/show.blade.php';
$layout = $root . DIRECTORY_SEPARATOR . 'resources/views/layouts/app.blade.php';
if (!is_file($show)) $errors[] = 'show.blade.php غير موجود.';
if (!is_file($layout)) $errors[] = 'layouts/app.blade.php غير موجود.';

if (!$errors) {
    $showContent = file_get_contents($show) ?: '';
    foreach (['document-qr-card', 'data-document-qr', 'da-document-qr', 'da-qr-print', 'document-print-qr', 'qr-print-position', '/qr.svg', 'qr.svg'] as $marker) {
        if (str_contains($showContent, $marker)) {
            $errors[] = "ما زال يوجد أثر QR داخل show.blade.php: {$marker}";
        }
    }
    $layoutContent = file_get_contents($layout) ?: '';
    if (!str_contains($layoutContent, 'document-show-qr-cleanup-v5.css')) $errors[] = 'رابط CSS الحارس V5 غير موجود في layout.';
    if (!str_contains($layoutContent, 'document-show-qr-cleanup-v5.js')) $errors[] = 'رابط JS الحارس V5 غير موجود في layout.';
}

foreach (['app','resources','routes'] as $rel) {
    $base = $root . DIRECTORY_SEPARATOR . $rel;
    if (!is_dir($base)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isDir() && str_starts_with($file->getFilename(), '_backup')) {
            $errors[] = 'مجلد backup داخل مسارات Laravel: ' . $file->getPathname();
        }
    }
}

if ($errors) failCheck($errors);
echo "OK: تم تنظيف QR من صفحة عرض الكتاب، وسيبقى QR مخصصاً للطباعة فقط.\n";

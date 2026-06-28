<?php
function projectRoot(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 6; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan') && is_dir($dir . DIRECTORY_SEPARATOR . 'resources')) return $dir;
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    fwrite(STDERR, "ERROR: لم يتم العثور على جذر مشروع Laravel.\n");
    exit(1);
}

$root = projectRoot();
$errors = [];
$css = $root . '/public/css/document-show-qr-cleanup-v4.css';
$js = $root . '/public/js/document-show-qr-cleanup-v4.js';
$show = $root . '/resources/views/documents/show.blade.php';
$layouts = [$root.'/resources/views/layouts/app.blade.php', $root.'/resources/views/layouts/admin.blade.php'];

if (!is_file($css)) $errors[] = 'ملف CSS الخاص بتنظيف صفحة العرض غير موجود.';
if (!is_file($js)) $errors[] = 'ملف JS الخاص بتنظيف صفحة العرض غير موجود.';

$linked = false;
foreach ($layouts as $layout) {
    if (is_file($layout)) {
        $c = file_get_contents($layout);
        if (str_contains($c, 'document-show-qr-cleanup-v4.css') && str_contains($c, 'document-show-qr-cleanup-v4.js')) {
            $linked = true;
            break;
        }
    }
}
if (!$linked) $errors[] = 'روابط CSS/JS الخاصة بتنظيف صفحة العرض غير موجودة داخل layout.';

if (is_file($show)) {
    $c = file_get_contents($show);
    $bad = ['da-print-qr','da-document-qr','document-qr-card','qr-print-position','رمز الوصول الإلكتروني','qr.svg'];
    foreach ($bad as $needle) {
        if (str_contains($c, $needle)) {
            $errors[] = "ما زال يوجد أثر QR داخل show.blade.php: {$needle}";
        }
    }
}

$backupDirs = [];
foreach (['app','resources','routes'] as $top) {
    $base = $root . DIRECTORY_SEPARATOR . $top;
    if (!is_dir($base)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isDir() && str_starts_with($file->getFilename(), '_backup')) {
            $backupDirs[] = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }
}
if ($backupDirs) $errors[] = 'ما زالت توجد مجلدات _backup داخل مسارات Laravel: ' . implode(', ', $backupDirs);

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح تنظيف QR من صفحة عرض الكتاب.\n";
    foreach ($errors as $e) echo "- {$e}\n";
    exit(1);
}

echo "OK: إصلاح تنظيف QR من صفحة عرض الكتاب مكتمل.\n";

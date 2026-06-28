<?php
$root = realpath(__DIR__ . '/..');
$errors = [];
if (!$root || !file_exists($root . '/artisan')) {
    $errors[] = 'جذر Laravel غير صحيح.';
}
if (!file_exists($root . '/public/css/document-qr-final-guard.css')) {
    $errors[] = 'ملف CSS النهائي غير موجود.';
}
if (!file_exists($root . '/public/js/document-qr-final-guard.js')) {
    $errors[] = 'ملف JS النهائي غير موجود.';
}
$layoutFound = false;
foreach (['app','admin','main'] as $name) {
    $p = $root . "/resources/views/layouts/{$name}.blade.php";
    if (is_file($p)) {
        $c = file_get_contents($p);
        if (str_contains($c, 'document-qr-final-guard.css') && str_contains($c, 'document-qr-final-guard.js')) {
            $layoutFound = true;
        }
    }
}
if (!$layoutFound) {
    $errors[] = 'روابط CSS/JS النهائية غير موجودة داخل ملفات layout المعروفة.';
}
$backupDirs = [];
foreach (['app','resources','routes'] as $rel) {
    $dir = $root . DIRECTORY_SEPARATOR . $rel;
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isDir() && str_starts_with($file->getFilename(), '_backup')) {
            $backupDirs[] = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }
}
if ($backupDirs) {
    $errors[] = 'ما زالت توجد مجلدات backup: ' . implode(', ', array_slice($backupDirs, 0, 5));
}
if ($errors) {
    echo "ERROR: لم يكتمل تنظيف QR.\n- " . implode("\n- ", $errors) . "\n";
    exit(1);
}
echo "OK: تم تطبيق تنظيف QR للطباعة فقط بنجاح.\n";

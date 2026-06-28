<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if (!$root || !is_file($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: تأكد أنك داخل جذر مشروع Laravel.\n");
    exit(1);
}

$documentsDir = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'documents';
if (!is_dir($documentsDir)) {
    fwrite(STDERR, "ERROR: مجلد documents views غير موجود.\n");
    exit(1);
}

$okFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($documentsDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || !str_ends_with($fileInfo->getPathname(), '.blade.php')) {
        continue;
    }
    $path = $fileInfo->getPathname();
    $content = file_get_contents($path) ?: '';
    if (strpos($content, 'official-document-qr-inside-page') !== false && strpos($content, 'document-qr-print-position-fix-v10') !== false) {
        $okFiles[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    }
}

if (empty($okFiles)) {
    fwrite(STDERR, "ERROR: لم يكتمل إصلاح موضع QR داخل صفحة الطباعة.\n- لم يتم العثور على official-document-qr-inside-page داخل ملفات documents.\n");
    exit(1);
}

echo "OK: إصلاح موضع QR داخل صفحة الطباعة موجود.\n";
foreach ($okFiles as $file) {
    echo "- {$file}\n";
}

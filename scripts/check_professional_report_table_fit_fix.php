<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$cssPath = $projectRoot . '/public/css/professional-report-table-fit-fix.css';
$viewsRoot = $projectRoot . '/resources/views';
$errors = [];

if (!is_file($cssPath)) {
    $errors[] = 'ملف CSS غير موجود: public/css/professional-report-table-fit-fix.css';
}

$foundLink = false;
$foundPrintView = false;
if (is_dir($viewsRoot)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (str_contains($path, DIRECTORY_SEPARATOR . '_backup')) {
            continue;
        }
        $content = file_get_contents($path) ?: '';
        if (str_contains($content, 'تقرير الكتب والمرفقات') || str_contains($content, 'تفاصيل الكتب')) {
            $foundPrintView = true;
        }
        if (str_contains($content, 'professional-report-table-fit-fix.css')) {
            $foundLink = true;
        }
    }
}

if (!$foundPrintView) {
    $errors[] = 'لم يتم العثور على View تقرير الكتب والمرفقات.';
}
if (!$foundLink) {
    $errors[] = 'رابط CSS غير موجود داخل ملف تقرير الطباعة.';
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح جدول التقرير الرسمي.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: إصلاح جدول تفاصيل الكتب في التقرير الرسمي مكتمل.\n";

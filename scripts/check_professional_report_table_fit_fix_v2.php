<?php

declare(strict_types=1);

function failWith(array $errors): void
{
    echo "ERROR: لم يكتمل إصلاح جدول التقرير الرسمي V2.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

$projectRoot = realpath(getcwd()) ?: getcwd();
$errors = [];

if (!is_file($projectRoot . DIRECTORY_SEPARATOR . 'artisan')) {
    $errors[] = 'أنت لست داخل جذر مشروع Laravel الذي يحتوي ملف artisan.';
}

$cssPath = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'professional-report-table-fit-fix.css';
if (!is_file($cssPath)) {
    $errors[] = 'ملف CSS غير موجود: public/css/professional-report-table-fit-fix.css';
} else {
    $css = file_get_contents($cssPath) ?: '';
    if (!str_contains($css, 'A4 landscape')) {
        $errors[] = 'ملف CSS موجود لكنه لا يحتوي إعداد A4 landscape.';
    }
}

$viewPath = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'print.blade.php';
if (!is_file($viewPath)) {
    $errors[] = 'ملف تقرير الطباعة غير موجود: resources/views/reports/print.blade.php';
} else {
    $content = file_get_contents($viewPath) ?: '';
    if (!str_contains($content, 'professional-report-table-fit-fix.css')) {
        $errors[] = 'رابط CSS غير موجود داخل ملف تقرير الطباعة.';
    }
    if (!str_contains($content, 'professional-report-page')) {
        $errors[] = 'كلاس professional-report-page غير موجود داخل تقرير الطباعة.';
    }
    if (!str_contains($content, 'professional-report-details-table')) {
        $errors[] = 'كلاس professional-report-details-table غير موجود على جدول تفاصيل الكتب.';
    }
}

if ($errors) {
    failWith($errors);
}

echo "OK: إصلاح جدول تفاصيل الكتب في التقرير الرسمي V2 مكتمل.\n";

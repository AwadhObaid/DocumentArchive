<?php

$root = dirname(__DIR__);
$errors = [];

$requiredFiles = [
    'app/Http/Controllers/DataQualityPrintController.php',
    'resources/views/data-quality/print.blade.php',
    'resources/views/data-quality/partials/document-table.blade.php',
    'resources/views/data-quality/partials/duplicate-policy-table.blade.php',
];

foreach ($requiredFiles as $file) {
    if (! file_exists($root . '/' . $file)) {
        $errors[] = "ملف مفقود: {$file}";
    }
}

$routesPath = $root . '/routes/web.php';
if (! file_exists($routesPath) || strpos(file_get_contents($routesPath), "data-quality.print") === false) {
    $errors[] = 'مسار data-quality.print غير موجود في routes/web.php';
}

if (file_exists($root . '/resources/views/data-quality/print.blade.php')) {
    $view = file_get_contents($root . '/resources/views/data-quality/print.blade.php');
    foreach (['@page', 'summary-grid', 'ملخص نتيجة المراجعة', 'window.print'] as $needle) {
        if (strpos($view, $needle) === false) {
            $errors[] = "عنصر التقرير المنسق غير موجود في print.blade.php: {$needle}";
        }
    }
}

if (file_exists($root . '/app/Http/Controllers/DataQualityPrintController.php')) {
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($root . '/app/Http/Controllers/DataQualityPrintController.php') . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $errors[] = "يوجد خطأ syntax في DataQualityPrintController.php:\n" . implode("\n", $output);
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل تحديث تقرير جودة البيانات المنسق.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: تقرير جودة البيانات المنسق مركب وجاهز. افتح: http://127.0.0.1:8000/data-quality/print\n";

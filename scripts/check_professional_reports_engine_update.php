<?php

$root = dirname(__DIR__);
$errors = [];

$required = [
    'app/Http/Controllers/ReportPrintController.php',
    'resources/views/reports/print.blade.php',
    'resources/views/reports/partials/print-summary-table.blade.php',
];

foreach ($required as $file) {
    if (! file_exists($root . '/' . $file)) {
        $errors[] = "ملف مفقود: {$file}";
    }
}

$routesPath = $root . '/routes/web.php';
if (! file_exists($routesPath) || strpos(file_get_contents($routesPath), 'reports.print') === false) {
    $errors[] = 'مسار reports.print غير موجود في routes/web.php';
}

$indexPath = $root . '/resources/views/reports/index.blade.php';
if (file_exists($indexPath) && strpos(file_get_contents($indexPath), 'reports.print') === false) {
    $errors[] = 'زر التقرير الرسمي غير موجود في صفحة التقارير.';
}

$controllerPath = $root . '/app/Http/Controllers/ReportPrintController.php';
if (file_exists($controllerPath)) {
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($controllerPath) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $errors[] = "يوجد خطأ syntax في ReportPrintController.php:\n" . implode("\n", $output);
    }
}

if (file_exists($root . '/resources/views/reports/print.blade.php')) {
    $view = file_get_contents($root . '/resources/views/reports/print.blade.php');
    foreach (['@page', 'تقرير الكتب والمرفقات', 'طباعة / حفظ PDF', 'نطاق التقرير والفلاتر'] as $needle) {
        if (strpos($view, $needle) === false) {
            $errors[] = "عنصر ناقص في صفحة التقرير الرسمي: {$needle}";
        }
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل تحديث التقرير الرسمي للكتب والمرفقات.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: التقرير الرسمي للكتب والمرفقات مركب وجاهز. افتح: http://127.0.0.1:8000/reports/print\n";

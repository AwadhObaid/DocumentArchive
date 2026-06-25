<?php
$root = dirname(__DIR__);
$errors = [];

$controller = $root . '/app/Http/Controllers/DashboardController.php';
$view = $root . '/resources/views/dashboard/index.blade.php';

if (!file_exists($controller)) {
    $errors[] = 'DashboardController.php غير موجود.';
} else {
    $content = file_get_contents($controller);
    foreach (['latestBackup', 'healthSummary', 'latestDocuments'] as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = "DashboardController لا يحتوي على {$needle}.";
        }
    }
}

if (!file_exists($view)) {
    $errors[] = 'resources/views/dashboard/index.blade.php غير موجود.';
} else {
    $content = file_get_contents($view);
    foreach (['إجمالي الكتب', 'آخر الكتب المضافة', 'آخر نسخة احتياطية', 'حالة النظام'] as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = "واجهة لوحة التحكم لا تحتوي على: {$needle}.";
        }
    }
}

if ($errors) {
    echo "Dashboard enhancement check: FAILED\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "Dashboard enhancement check: OK\n";
echo "لوحة التحكم المحسنة جاهزة.\n";

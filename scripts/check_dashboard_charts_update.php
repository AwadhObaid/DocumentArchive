<?php
$root = dirname(__DIR__);
$errors = [];

$controller = $root . '/app/Http/Controllers/DashboardController.php';
$view = $root . '/resources/views/dashboard/index.blade.php';

if (!file_exists($controller)) {
    $errors[] = 'DashboardController.php غير موجود.';
} else {
    $content = file_get_contents($controller);
    foreach (['documentsByMonth', 'documentsByLookup', 'documents_by_department', 'documents_by_type'] as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = "DashboardController لا يحتوي على {$needle}.";
        }
    }
}

if (!file_exists($view)) {
    $errors[] = 'resources/views/dashboard/index.blade.php غير موجود.';
} else {
    $content = file_get_contents($view);
    foreach (['حركة الكتب خلال آخر 6 أشهر', 'توزيع الكتب حسب الإدارة', 'توزيع الكتب حسب النوع', 'da-chart-bars'] as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = "واجهة لوحة التحكم لا تحتوي على: {$needle}.";
        }
    }
}

if ($errors) {
    echo "Dashboard charts check: FAILED\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "Dashboard charts check: OK\n";
echo "رسوم لوحة التحكم جاهزة.\n";

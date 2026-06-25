<?php
/**
 * DocumentArchive - Check Dashboard Admin Alerts Update
 */
$root = dirname(__DIR__);
$controller = $root . '/app/Http/Controllers/DashboardController.php';
$view = $root . '/resources/views/dashboard/index.blade.php';
$errors = [];

if (!file_exists($controller)) {
    $errors[] = 'ملف DashboardController.php غير موجود.';
} else {
    $content = file_get_contents($controller);
    foreach (['dashboardAlerts', 'countDocumentsWithoutAttachments', 'countDuplicatePolicy', 'documents_without_attachments'] as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = 'لم يتم العثور على ' . $needle . ' داخل DashboardController.';
        }
    }
}

if (!file_exists($view)) {
    $errors[] = 'ملف resources/views/dashboard/index.blade.php غير موجود.';
} else {
    $content = file_get_contents($view);
    foreach (['التنبيهات الإدارية', 'da-admin-alerts', 'dashboardAlerts'] as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = 'لم يتم العثور على ' . $needle . ' داخل واجهة لوحة التحكم.';
        }
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل تحديث التنبيهات الإدارية.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: تم تثبيت تنبيهات لوحة التحكم الإدارية بنجاح.\n";

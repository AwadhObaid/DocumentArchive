<?php
$root = dirname(__DIR__);
$errors = [];
$controllerPath = $root . '/app/Http/Controllers/NotificationCenterController.php';
$routesPath = $root . '/routes/web.php';
$migrationPath = $root . '/database/migrations/2026_06_28_000020_add_hidden_columns_to_system_notifications_table.php';

if (!file_exists($controllerPath)) {
    $errors[] = 'الملف غير موجود: app/Http/Controllers/NotificationCenterController.php';
} else {
    $c = file_get_contents($controllerPath);
    foreach (['use Illuminate\\Support\\Facades\\Schema;', 'use Illuminate\\Support\\Facades\\DB;', 'function hideRead(', 'function deleteHidden(', 'function notificationQueryForCurrentUser('] as $needle) {
        if (!str_contains($c, $needle)) {
            $errors[] = "ناقص داخل NotificationCenterController.php: {$needle}";
        }
    }
}

if (!file_exists($routesPath)) {
    $errors[] = 'الملف غير موجود: routes/web.php';
} else {
    $r = file_get_contents($routesPath);
    foreach (['/notifications/hide-read', '/notifications/delete-hidden', 'notifications.delete-hidden', 'notifications.hide-read'] as $needle) {
        if (!str_contains($r, $needle)) {
            $errors[] = "المسار غير موجود: {$needle}";
        }
    }
}

if (!file_exists($migrationPath)) {
    $errors[] = 'Migration حقول الإخفاء غير موجود.';
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح إدارة الإشعارات.\n";
    foreach ($errors as $e) echo "- {$e}\n";
    exit(1);
}

echo "OK: إصلاح إدارة الإشعارات مكتمل.\n";

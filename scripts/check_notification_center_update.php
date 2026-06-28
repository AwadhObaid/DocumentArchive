<?php

declare(strict_types=1);

$root = getcwd();
$errors = [];

$required = [
    'app/Models/SystemNotification.php',
    'app/Services/SystemNotificationService.php',
    'app/Http/Controllers/NotificationCenterController.php',
    'resources/views/partials/notification-center.blade.php',
    'resources/views/notifications/index.blade.php',
];

foreach ($required as $file) {
    if (! is_file($root . DIRECTORY_SEPARATOR . $file)) {
        $errors[] = "ملف مفقود: {$file}";
    }
}

$migrations = glob($root . '/database/migrations/*create_system_notifications_table.php');
if (! $migrations) {
    $errors[] = 'Migration إنشاء جدول system_notifications غير موجود.';
}

$routes = is_file($root . '/routes/web.php') ? file_get_contents($root . '/routes/web.php') : '';
if (! str_contains($routes, 'notifications.index')) {
    $errors[] = 'مسارات مركز الإشعارات غير موجودة في routes/web.php.';
}

$layout = is_file($root . '/resources/views/layouts/app.blade.php') ? file_get_contents($root . '/resources/views/layouts/app.blade.php') : '';
if (! str_contains($layout, 'partials.notification-center')) {
    $errors[] = 'تضمين مركز الإشعارات غير موجود داخل layout.';
}

if ($errors) {
    echo "ERROR: لم يكتمل تحديث مركز الإشعارات.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: تحديث مركز الإشعارات مكتمل.\n";
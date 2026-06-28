<?php

declare(strict_types=1);

function project_root_check(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 10; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
    throw new RuntimeException('تعذر تحديد جذر مشروع Laravel.');
}

$root = project_root_check();
$errors = [];

$files = [
    'app/Models/NotificationPreference.php',
    'app/Http/Controllers/NotificationSettingsController.php',
    'app/Services/SystemNotificationService.php',
    'resources/views/notifications/settings.blade.php',
    'database/migrations/2026_06_28_000013_create_notification_preferences_table.php',
];

foreach ($files as $relative) {
    if (! is_file($root . '/' . $relative)) {
        $errors[] = 'الملف غير موجود: ' . $relative;
    }
}

foreach (['app/Models/NotificationPreference.php', 'app/Http/Controllers/NotificationSettingsController.php', 'app/Services/SystemNotificationService.php'] as $relative) {
    $file = $root . '/' . $relative;
    if (is_file($file)) {
        exec('php -l ' . escapeshellarg($file), $out, $code);
        if ($code !== 0) {
            $errors[] = 'خطأ syntax في ' . $relative . ': ' . implode(' ', $out);
        }
    }
}

$service = $root . '/app/Services/SystemNotificationService.php';
if (is_file($service)) {
    $content = file_get_contents($service);
    foreach (['availableNotificationTypes', 'notificationEnabled', 'preferenceKeyFrom', 'syncForCurrentUser', 'notifyAdmins', 'notifyCurrentUser'] as $method) {
        if (! str_contains($content, 'function ' . $method)) {
            $errors[] = 'الدالة غير موجودة في SystemNotificationService: ' . $method;
        }
    }
}

$routes = $root . '/routes/web.php';
if (! is_file($routes) || ! str_contains(file_get_contents($routes), 'notification-settings')) {
    $errors[] = 'مسارات notification-settings غير موجودة في routes/web.php';
}

try {
    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    if (! Illuminate\Support\Facades\Schema::hasTable('notification_preferences')) {
        $errors[] = 'جدول notification_preferences غير موجود. نفّذ: php artisan migrate';
    }

    if (! method_exists(App\Services\SystemNotificationService::class, 'availableNotificationTypes')) {
        $errors[] = 'Laravel لا يرى availableNotificationTypes بعد التحميل.';
    }
} catch (Throwable $e) {
    $errors[] = 'تعذر فحص Laravel runtime: ' . $e->getMessage();
}

if ($errors) {
    echo "ERROR: لم يكتمل تحديث إعدادات الإشعارات.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: تحديث إعدادات الإشعارات مكتمل.\n";
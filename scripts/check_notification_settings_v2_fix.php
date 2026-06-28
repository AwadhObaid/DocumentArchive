<?php

declare(strict_types=1);

function project_root_check(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 12; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan')) { return $dir; }
        $parent = dirname($dir);
        if ($parent === $dir) { break; }
        $dir = $parent;
    }
    throw new RuntimeException('تعذر تحديد جذر مشروع Laravel.');
}

$root = project_root_check();
$errors = [];

$files = [
    'app/Models/NotificationPreference.php',
    'app/Models/SystemNotification.php',
    'app/Http/Controllers/NotificationSettingsController.php',
    'app/Services/SystemNotificationService.php',
    'resources/views/notifications/settings.blade.php',
    'database/migrations/2026_06_28_000014_create_notification_preferences_table_if_missing.php',
];

foreach ($files as $relative) {
    if (! is_file($root . '/' . $relative)) { $errors[] = 'الملف غير موجود: ' . $relative; }
}

foreach (['app/Models/NotificationPreference.php', 'app/Models/SystemNotification.php', 'app/Http/Controllers/NotificationSettingsController.php', 'app/Services/SystemNotificationService.php'] as $relative) {
    $file = $root . '/' . $relative;
    if (is_file($file)) {
        exec('php -l ' . escapeshellarg($file), $out, $code);
        if ($code !== 0) { $errors[] = 'خطأ syntax في ' . $relative . ': ' . implode(' ', $out); }
    }
}

$service = $root . '/app/Services/SystemNotificationService.php';
if (is_file($service)) {
    $content = file_get_contents($service);
    foreach (['availableNotificationTypes', 'notificationEnabled', 'preferenceKeyFrom', 'syncForCurrentUser', 'notifyAdmins', 'notifyCurrentUser', 'createForUser'] as $method) {
        if (! str_contains($content, 'function ' . $method)) { $errors[] = 'الدالة غير موجودة في SystemNotificationService: ' . $method; }
    }
    foreach (['events.document_created', 'events.attachment_uploaded', 'flash.success'] as $key) {
        if (! str_contains($content, $key)) { $errors[] = 'مفتاح الإشعار غير موجود داخل الخدمة: ' . $key; }
    }
}

$routes = $root . '/routes/web.php';
if (! is_file($routes) || ! str_contains(file_get_contents($routes), 'notification-settings')) { $errors[] = 'مسارات notification-settings غير موجودة في routes/web.php'; }

$hasButton = false;
foreach (glob($root . '/resources/views/**/*.blade.php') ?: [] as $view) {
    if (str_contains(file_get_contents($view), "notification-settings.edit")) { $hasButton = true; break; }
}
if (! $hasButton) { $errors[] = 'لا يوجد زر أو رابط لإعدادات الإشعارات داخل ملفات الواجهة.'; }

try {
    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    if (! Illuminate\Support\Facades\Schema::hasTable('notification_preferences')) { $errors[] = 'جدول notification_preferences غير موجود. نفّذ: php artisan migrate'; }
    if (! method_exists(App\Services\SystemNotificationService::class, 'syncForCurrentUser')) { $errors[] = 'Laravel لا يرى syncForCurrentUser بعد التحميل.'; }
} catch (Throwable $e) {
    $errors[] = 'تعذر فحص Laravel runtime: ' . $e->getMessage();
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح إعدادات الإشعارات V2.\n";
    foreach ($errors as $error) { echo "- {$error}\n"; }
    exit(1);
}

echo "OK: إصلاح إعدادات الإشعارات V2 مكتمل.\n";
<?php

declare(strict_types=1);

function root(): string
{
    $cwd = getcwd() ?: __DIR__;
    if (is_file($cwd . DIRECTORY_SEPARATOR . 'artisan')) {
        return $cwd;
    }
    $parent = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    if ($parent && is_file($parent . DIRECTORY_SEPARATOR . 'artisan')) {
        return $parent;
    }
    throw new RuntimeException('تعذر تحديد جذر المشروع.');
}

function file_contains(string $file, string $needle): bool
{
    return is_file($file) && strpos((string) file_get_contents($file), $needle) !== false;
}

$root = root();
$errors = [];

$routes = $root . '/routes/web.php';
$controller = $root . '/app/Http/Controllers/NotificationCenterController.php';
$view = $root . '/resources/views/notifications/index.blade.php';
$css = $root . '/public/css/notification-management.css';

if (!is_file($css)) $errors[] = 'ملف CSS غير موجود: public/css/notification-management.css';
if (!file_contains($routes, 'notifications.mark-all-read')) $errors[] = 'مسار تعليم الكل كمقروء غير موجود.';
if (!file_contains($routes, 'notifications.hide-read')) $errors[] = 'مسار إخفاء المقروءة غير موجود.';
if (!file_contains($routes, 'notifications.clear-hidden')) $errors[] = 'مسار حذف المخفية غير موجود.';
if (!file_contains($controller, 'public function markAllRead(')) $errors[] = 'دالة markAllRead غير موجودة في NotificationCenterController.';
if (!file_contains($controller, 'public function hideRead(')) $errors[] = 'دالة hideRead غير موجودة في NotificationCenterController.';
if (!file_contains($controller, 'public function clearHidden(')) $errors[] = 'دالة clearHidden غير موجودة في NotificationCenterController.';
if (!file_contains($view, 'notification-management-panel')) $errors[] = 'لوحة إدارة الإشعارات غير موجودة داخل صفحة الإشعارات.';
if (!file_contains($view, 'notification-management.css')) $errors[] = 'رابط CSS غير موجود داخل صفحة الإشعارات.';

if ($errors) {
    echo "ERROR: لم يكتمل تحديث إدارة الإشعارات.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: تحديث إدارة الإشعارات مكتمل.\n";

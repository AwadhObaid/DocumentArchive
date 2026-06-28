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

$service = $root . '/app/Services/SystemNotificationService.php';
$model = $root . '/app/Models/SystemNotification.php';
$migration = $root . '/database/migrations/2026_06_28_000012_patch_system_notifications_compatibility.php';

foreach ([$service, $model, $migration] as $file) {
    if (! is_file($file)) {
        $errors[] = 'الملف غير موجود: ' . str_replace($root . '/', '', $file);
    }
}

if (is_file($service)) {
    $content = file_get_contents($service);
    foreach (['syncForCurrentUser', 'notifyAdmins', 'notifyCurrentUser', 'buildSystemAlerts', 'createForUser'] as $method) {
        if (! str_contains($content, 'function ' . $method)) {
            $errors[] = "الدالة غير موجودة في SystemNotificationService: {$method}";
        }
    }
    exec('php -l ' . escapeshellarg($service), $out, $code);
    if ($code !== 0) {
        $errors[] = 'يوجد خطأ syntax في SystemNotificationService.php: ' . implode(' ', $out);
    }
}

if (is_file($model)) {
    $content = file_get_contents($model);
    foreach (['scopeVisible', 'scopeUnread', 'getLinkAttribute', 'getBodyAttribute'] as $method) {
        if (! str_contains($content, 'function ' . $method)) {
            $errors[] = "الدالة غير موجودة في SystemNotification model: {$method}";
        }
    }
    exec('php -l ' . escapeshellarg($model), $out2, $code2);
    if ($code2 !== 0) {
        $errors[] = 'يوجد خطأ syntax في SystemNotification.php: ' . implode(' ', $out2);
    }
}

try {
    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    if (! Illuminate\Support\Facades\Schema::hasTable('system_notifications')) {
        $errors[] = 'جدول system_notifications غير موجود. نفّذ: php artisan migrate';
    } else {
        foreach (['user_id', 'type', 'title', 'body', 'link', 'url', 'message', 'read_at'] as $column) {
            if (! Illuminate\Support\Facades\Schema::hasColumn('system_notifications', $column)) {
                $errors[] = "العمود غير موجود في system_notifications: {$column}. نفّذ: php artisan migrate";
            }
        }
    }

    if (! method_exists(App\Services\SystemNotificationService::class, 'syncForCurrentUser')) {
        $errors[] = 'Laravel لا يرى الدالة syncForCurrentUser بعد التحميل.';
    }
} catch (Throwable $e) {
    $errors[] = 'تعذر فحص Laravel runtime: ' . $e->getMessage();
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح توافق الإشعارات V3.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: إصلاح توافق الإشعارات V3 مكتمل.\n";
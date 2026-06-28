<?php

function root_path(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 8; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan')) return $dir;
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    throw new RuntimeException('تعذر تحديد جذر مشروع Laravel.');
}

$root = root_path();
$errors = [];

$requiredFiles = [
    'app/Observers/DocumentObserver.php',
    'app/Observers/DocumentAttachmentObserver.php',
    'app/Http/Middleware/PersistImportantFlashNotifications.php',
    'app/Services/SystemNotificationService.php',
    'app/Models/SystemNotification.php',
    'database/migrations/2026_06_28_000010_create_system_notifications_table_if_not_exists.php',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = "الملف غير موجود: {$file}";
    }
}

$provider = $root . '/app/Providers/AppServiceProvider.php';
if (!is_file($provider)) {
    $errors[] = 'الملف غير موجود: app/Providers/AppServiceProvider.php';
} else {
    $content = file_get_contents($provider);
    if (!str_contains($content, 'Document::observe(DocumentObserver::class)')) {
        $errors[] = 'تسجيل Observer غير موجود في AppServiceProvider: Document::observe(DocumentObserver::class)';
    }
    if (!str_contains($content, 'DocumentAttachment::observe(DocumentAttachmentObserver::class)')) {
        $errors[] = 'تسجيل Observer غير موجود في AppServiceProvider: DocumentAttachment::observe(DocumentAttachmentObserver::class)';
    }
}

$registered = false;
foreach (['bootstrap/app.php', 'app/Http/Kernel.php'] as $file) {
    $path = $root . '/' . $file;
    if (is_file($path) && str_contains(file_get_contents($path), 'PersistImportantFlashNotifications::class')) {
        $registered = true;
    }
}
if (!$registered) {
    $errors[] = 'Middleware الخاص بحفظ رسائل النظام المهمة غير مسجل في bootstrap/app.php أو Kernel.php.';
}

// Optional runtime table check if Laravel can bootstrap.
try {
    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    if (!Illuminate\Support\Facades\Schema::hasTable('system_notifications')) {
        $errors[] = 'جدول system_notifications غير موجود. نفّذ: php artisan migrate';
    } else {
        foreach (['user_id', 'type', 'title', 'read_at', 'hidden_at'] as $column) {
            if (!Illuminate\Support\Facades\Schema::hasColumn('system_notifications', $column)) {
                $errors[] = "العمود غير موجود في system_notifications: {$column}";
            }
        }
        if (!Illuminate\Support\Facades\Schema::hasColumn('system_notifications', 'body') && !Illuminate\Support\Facades\Schema::hasColumn('system_notifications', 'message')) {
            $errors[] = 'لا يوجد عمود body أو message في system_notifications.';
        }
    }
} catch (Throwable $e) {
    $errors[] = 'تعذر فحص قاعدة البيانات تلقائياً: ' . $e->getMessage();
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح ربط الإشعارات V2.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: تحديث ربط الإشعارات V2 مكتمل.\n";

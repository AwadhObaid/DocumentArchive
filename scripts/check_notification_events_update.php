<?php
$root = realpath(__DIR__ . '/..');
$errors = [];

if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    $errors[] = 'لم يتم العثور على ملف artisan. شغل السكربت من جذر مشروع Laravel.';
}

$requiredFiles = [
    'app/Services/SystemNotificationService.php',
    'app/Observers/DocumentObserver.php',
    'app/Observers/DocumentAttachmentObserver.php',
    'app/Http/Middleware/PersistImportantFlashNotifications.php',
    'database/migrations/2026_06_28_000010_create_system_notifications_table_if_not_exists.php',
];

foreach ($requiredFiles as $file) {
    if (!file_exists($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $errors[] = "الملف غير موجود: {$file}";
    }
}

$appServiceProvider = $root . '/app/Providers/AppServiceProvider.php';
if (file_exists($appServiceProvider)) {
    $content = file_get_contents($appServiceProvider);
    foreach ([
        'Document::observe(DocumentObserver::class)',
        'DocumentAttachment::observe(DocumentAttachmentObserver::class)',
    ] as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = "تسجيل Observer غير موجود في AppServiceProvider: {$needle}";
        }
    }
} else {
    $errors[] = 'AppServiceProvider.php غير موجود.';
}

$middlewareRegistered = false;
$bootstrapApp = $root . '/bootstrap/app.php';
if (file_exists($bootstrapApp) && str_contains(file_get_contents($bootstrapApp), 'PersistImportantFlashNotifications::class')) {
    $middlewareRegistered = true;
}
$kernel = $root . '/app/Http/Kernel.php';
if (file_exists($kernel) && str_contains(file_get_contents($kernel), 'PersistImportantFlashNotifications::class')) {
    $middlewareRegistered = true;
}

if (!$middlewareRegistered) {
    $errors[] = 'Middleware الخاص بحفظ رسائل النظام المهمة غير مسجل في bootstrap/app.php أو Kernel.php.';
}

if ($errors) {
    echo "ERROR: لم يكتمل تحديث ربط الإشعارات.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: تحديث ربط الإشعارات مكتمل.\n";
echo "تأكد بعد تشغيل migrate من تجربة: إضافة كتاب، تعديل كتاب، رفع مرفق، وإنشاء نسخة احتياطية.\n";

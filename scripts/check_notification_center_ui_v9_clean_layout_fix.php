<?php

declare(strict_types=1);

$root = getcwd();
$viewPath = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'notifications' . DIRECTORY_SEPARATOR . 'index.blade.php';
$errors = [];

if (!is_file($viewPath)) {
    $errors[] = 'الملف غير موجود: resources/views/notifications/index.blade.php';
} else {
    $content = file_get_contents($viewPath);
    foreach ([
        'notifications-hero-v9',
        'notifications-hero-main-v9',
        'notifications-actions-v9',
        'notification-item-v9',
        'notification-item-actions-v9',
        'routeOrUrl',
    ] as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = "ناقص داخل صفحة الإشعارات: {$needle}";
        }
    }

    if (str_contains($content, "route('notifications") && !str_contains($content, 'Route::has')) {
        $errors[] = 'يوجد استخدام مباشر لـ route بدون فحص Route::has.';
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح واجهة مركز الإشعارات V9.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: إصلاح واجهة مركز الإشعارات V9 مكتمل.\n";

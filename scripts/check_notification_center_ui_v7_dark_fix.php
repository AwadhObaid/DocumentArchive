<?php

declare(strict_types=1);

$root = getcwd();
$errors = [];

if (!file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    $errors[] = 'يجب تشغيل الفحص من جذر مشروع Laravel.';
}

$viewPath = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'notifications' . DIRECTORY_SEPARATOR . 'index.blade.php';
if (!file_exists($viewPath)) {
    $errors[] = 'ملف صفحة الإشعارات غير موجود: resources/views/notifications/index.blade.php';
} else {
    $content = file_get_contents($viewPath);
    foreach ([
        'notification-center-page' => 'كلاس الصفحة الرئيسي غير موجود.',
        'nc-hero' => 'قسم رأس الصفحة المنظم غير موجود.',
        'nc-stat-card' => 'بطاقات الإحصائيات غير موجودة.',
        'nc-item-actions' => 'تنسيق أزرار الإشعار غير موجود.',
        '--nc-panel: #111827' => 'ألوان الوضع الليلي غير موجودة.',
        'body.light .notification-center-page' => 'دعم الوضع الفاتح غير موجود.',
        'data_get($notification, \'id\')' => 'استخراج id من الإشعار غير موجود.',
        'لا توجد تفاصيل إضافية لهذا الإشعار' => 'معالجة الإشعارات بدون تفاصيل غير موجودة.',
    ] as $needle => $message) {
        if (!str_contains($content, $needle)) {
            $errors[] = $message;
        }
    }

    if (str_contains($content, 'route(\'notifications.read\', $notification)') || str_contains($content, 'route("notifications.read", $notification)')) {
        $errors[] = 'ما زال الملف يمرر كائن الإشعار كاملاً إلى route().';
    }
}

foreach (['app', 'resources', 'routes'] as $relative) {
    $base = $root . DIRECTORY_SEPARATOR . $relative;
    if (!is_dir($base)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
            $errors[] = 'ما زال يوجد مجلد backup داخل مسارات Laravel: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $item->getPathname());
        }
    }
}

if ($errors) {
    fwrite(STDERR, "ERROR: لم يكتمل إصلاح واجهة مركز الإشعارات V7.\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "OK: واجهة مركز الإشعارات V7 مرتبة وتدعم الوضع الليلي.\n");

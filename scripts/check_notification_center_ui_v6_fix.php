<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false || !is_file($root . DIRECTORY_SEPARATOR . 'artisan')) {
    $candidate = getcwd();
    if (is_file($candidate . DIRECTORY_SEPARATOR . 'artisan')) {
        $root = $candidate;
    }
}

$errors = [];
if ($root === false || !is_file($root . DIRECTORY_SEPARATOR . 'artisan')) {
    $errors[] = 'تأكد أنك تشغل الفحص من جذر مشروع Laravel.';
} else {
    $viewPath = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'notifications' . DIRECTORY_SEPARATOR . 'index.blade.php';
    if (!is_file($viewPath)) {
        $errors[] = 'ملف صفحة الإشعارات غير موجود.';
    } else {
        $content = file_get_contents($viewPath) ?: '';
        foreach (['notification-center-ui-v6', 'notification-actions-toolbar', 'notification-item-card', 'data_get($notification, \'id\')'] as $needle) {
            if (!str_contains($content, $needle)) {
                $errors[] = 'ناقص داخل صفحة الإشعارات: ' . $needle;
            }
        }
        if (str_contains($content, "route('notifications.hide', ['notification' => \$notification])") || str_contains($content, 'route(\'notifications.hide\', $notification)')) {
            $errors[] = 'ما زال يتم تمرير كائن الإشعار كاملاً إلى route بدلاً من id.';
        }
    }

    foreach (['app', 'resources', 'routes'] as $dir) {
        $base = $root . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($base)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
                $errors[] = 'ما زال يوجد مجلد backup داخل: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $item->getPathname());
            }
        }
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل ترتيب مركز الإشعارات V6.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: مركز الإشعارات مرتب ومستقر V6.\n";

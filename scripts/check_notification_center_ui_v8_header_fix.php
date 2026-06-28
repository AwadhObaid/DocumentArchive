<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
$viewPath = $root . DIRECTORY_SEPARATOR . 'resources/views/notifications/index.blade.php';
$errors = [];

if (! file_exists($viewPath)) {
    $errors[] = 'ملف صفحة الإشعارات غير موجود.';
} else {
    $content = file_get_contents($viewPath);
    foreach (['notifications-center-v8', 'nc-hero', 'grid-template-columns: minmax(0, 1fr) auto', 'nc-title-block p', 'word-break: normal'] as $needle) {
        if (! str_contains($content, $needle)) {
            $errors[] = 'ناقص داخل صفحة الإشعارات: ' . $needle;
        }
    }

    if (str_contains($content, "route('notifications.read_all', $") || str_contains($content, 'route("notifications.read_all", $')) {
        $errors[] = 'ما زال يوجد تمرير مباشر لكائن داخل route notifications.read_all.';
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح واجهة مركز الإشعارات V8.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: واجهة مركز الإشعارات V8 مثبتة، والبطاقة أصبحت بتنسيق أفقي واضح.\n";

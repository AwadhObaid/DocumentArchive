<?php

$root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: شغّل السكربت من داخل جذر مشروع Laravel.\n");
    exit(1);
}

$errors = [];

$controllerPath = $root . DIRECTORY_SEPARATOR . 'app/Http/Controllers/NotificationCenterController.php';
$routesPath = $root . DIRECTORY_SEPARATOR . 'routes/web.php';
$migrationPath = $root . DIRECTORY_SEPARATOR . 'database/migrations/2026_06_28_000030_add_hidden_columns_to_system_notifications_table.php';

if (!file_exists($controllerPath)) {
    $errors[] = 'الملف غير موجود: app/Http/Controllers/NotificationCenterController.php';
} else {
    $controller = file_get_contents($controllerPath);
    foreach ([
        'use Illuminate\Support\Facades\Schema;',
        'use Illuminate\Support\Facades\DB;',
        'function hideRead(',
        'function deleteHidden(',
        'function purgeHidden(',
        'function clearHidden(',
        'function notificationQueryForCurrentUser(',
    ] as $needle) {
        if (!str_contains($controller, $needle)) {
            $errors[] = "ناقص داخل NotificationCenterController.php: {$needle}";
        }
    }

    if (str_contains($controller, 'App\Http\Controllers\Schema')) {
        $errors[] = 'ما زال هناك استخدام خاطئ لـ Schema داخل namespace الكنترولر.';
    }
}

if (!file_exists($routesPath)) {
    $errors[] = 'الملف غير موجود: routes/web.php';
} else {
    $routes = file_get_contents($routesPath);
    foreach ([
        "/notifications/hide-read",
        "/notifications/delete-hidden",
        "notifications.hide-read",
        "notifications.delete-hidden",
    ] as $needle) {
        if (!str_contains($routes, $needle)) {
            $errors[] = "المسار غير موجود: {$needle}";
        }
    }
}

if (!file_exists($migrationPath)) {
    $errors[] = 'Migration حقول الإخفاء غير موجود.';
}

// فحص بقايا _backup داخل app/resources/routes
foreach (['app', 'resources', 'routes'] as $dir) {
    $full = $root . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($full)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
            $errors[] = 'يوجد مجلد backup قديم يسبب تحذيرات Composer: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $item->getPathname());
            break;
        }
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح إدارة الإشعارات V3.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: إصلاح إدارة الإشعارات V3 مكتمل.\n";
echo "جرّب الآن:\n";
echo "- http://127.0.0.1:8000/notifications\n";
echo "- إخفاء المقروء\n";
echo "- حذف المخفية\n";

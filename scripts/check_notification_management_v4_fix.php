<?php
/**
 * Check Notification routes/actions V4 compatibility fix.
 */

function root_path_v4_check(): string
{
    $cwd = getcwd();
    if (is_file($cwd . DIRECTORY_SEPARATOR . 'artisan')) {
        return $cwd;
    }

    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
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

$root = root_path_v4_check();
$errors = [];

$controller = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'NotificationCenterController.php';
$routes = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';

if (!is_file($controller)) {
    $errors[] = 'الملف غير موجود: app/Http/Controllers/NotificationCenterController.php';
} else {
    $c = file_get_contents($controller);
    foreach ([
        'use Illuminate\Support\Facades\Schema;',
        'use Illuminate\Support\Facades\DB;',
        'function notificationQueryForCurrentUser(',
        'function markAllRead(',
        'function readAll(',
        'function hideRead(',
        'function deleteHidden(',
        'function purgeHidden(',
        'function clearHidden(',
    ] as $needle) {
        if (!str_contains($c, $needle)) {
            $errors[] = 'ناقص داخل NotificationCenterController.php: ' . $needle;
        }
    }
}

if (!is_file($routes)) {
    $errors[] = 'الملف غير موجود: routes/web.php';
} else {
    $r = file_get_contents($routes);
    foreach ([
        "notifications.read_all",
        "notifications.mark-all-read",
        "notifications.hide-read",
        "notifications.delete-hidden",
        "/notifications/read-all",
        "/notifications/delete-hidden",
    ] as $needle) {
        if (!str_contains($r, $needle)) {
            $errors[] = 'ناقص داخل routes/web.php: ' . $needle;
        }
    }
}

$migrationFound = false;
$migrationDir = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
if (is_dir($migrationDir)) {
    foreach (glob($migrationDir . DIRECTORY_SEPARATOR . '*system_notifications*.php') ?: [] as $file) {
        $content = file_get_contents($file);
        if (str_contains($content, 'is_hidden') && str_contains($content, 'hidden_at')) {
            $migrationFound = true;
            break;
        }
    }
}
if (!$migrationFound) {
    $errors[] = 'Migration حقول الإخفاء غير موجود.';
}

$backupFound = [];
foreach ([
    $root . DIRECTORY_SEPARATOR . 'app',
    $root . DIRECTORY_SEPARATOR . 'resources',
    $root . DIRECTORY_SEPARATOR . 'routes',
] as $target) {
    if (!is_dir($target)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $item) {
        if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
            $backupFound[] = str_replace($root . DIRECTORY_SEPARATOR, '', $item->getPathname());
        }
    }
}
if ($backupFound) {
    $errors[] = 'ما زالت توجد مجلدات backup داخل مسارات Laravel: ' . implode(', ', $backupFound);
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح إدارة الإشعارات V4.\n";
    foreach ($errors as $e) {
        echo "- {$e}\n";
    }
    exit(1);
}

echo "OK: اكتمل إصلاح إدارة الإشعارات V4.\n";

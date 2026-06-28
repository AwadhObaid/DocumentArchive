<?php
function root(): string {
    $dir = getcwd();
    while ($dir && $dir !== dirname($dir)) {
        if (file_exists($dir . DIRECTORY_SEPARATOR . 'artisan')) return $dir;
        $dir = dirname($dir);
    }
    fwrite(STDERR, "ERROR: شغل الفحص من جذر المشروع.\n"); exit(1);
}
$r = root();
$errors = [];
$controller = $r . '/app/Http/Controllers/NotificationCenterController.php';
$view = $r . '/resources/views/notifications/index.blade.php';
$routes = $r . '/routes/web.php';
$migration = $r . '/database/migrations/2026_06_28_000050_stabilize_system_notifications_v5.php';

foreach ([$controller, $view, $routes, $migration] as $f) if (!is_file($f)) $errors[] = "الملف غير موجود: " . str_replace($r . '/', '', $f);
if (is_file($controller)) {
    $c = file_get_contents($controller);
    foreach (['use Illuminate\\Support\\Facades\\Schema;', 'function deleteHidden(', 'function hideRead(', 'function readAll(', 'function extractId('] as $needle) {
        if (!str_contains($c, $needle)) $errors[] = "ناقص داخل NotificationCenterController.php: {$needle}";
    }
}
if (is_file($view)) {
    $v = file_get_contents($view);
    foreach (['$notificationId = data_get($notification, \'id\')', "route('notifications.read', ['notification' => \$notificationId])", '$safeLink'] as $needle) {
        if (!str_contains($v, $needle)) $errors[] = "ناقص داخل notifications/index.blade.php: {$needle}";
    }
    if (preg_match("/route\([^\)]*,\s*\\$notification\s*\)/", $v)) $errors[] = "ما زال يوجد route() يمرر كائن notification كاملاً بدل id.";
}
if (is_file($routes)) {
    $rw = file_get_contents($routes);
    foreach (["notifications.read_all", "notifications.delete-hidden", "notifications.hide-read", "notifications.read"] as $needle) {
        if (!str_contains($rw, $needle)) $errors[] = "المسار غير موجود في web.php: {$needle}";
    }
}
foreach (['app','resources','routes'] as $base) {
    $basePath = $r . '/' . $base;
    if (!is_dir($basePath)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $item) {
        if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
            $errors[] = 'ما زالت توجد مجلدات backup داخل مسارات Laravel: ' . str_replace($r . '/', '', $item->getPathname());
        }
    }
}
if ($errors) {
    echo "ERROR: لم يكتمل إصلاح مركز الإشعارات V5.\n- " . implode("\n- ", $errors) . "\n";
    exit(1);
}
echo "OK: إصلاح مركز الإشعارات V5 مكتمل.\n";

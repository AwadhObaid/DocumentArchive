<?php

$root = dirname(__DIR__);
$checks = [
    'app/Http/Middleware/AutoLogoutIfInactive.php',
    'app/Http/Controllers/SessionActivityController.php',
    'app/Http/Controllers/LiteController.php',
    'resources/views/lite/layout.blade.php',
    'resources/views/lite/index.blade.php',
    'resources/views/lite/documents.blade.php',
    'resources/views/lite/document-show.blade.php',
    'resources/views/lite/memos.blade.php',
    'resources/views/lite/memo-show.blade.php',
    'resources/views/lite/notifications.blade.php',
    'public/css/auto-logout.css',
    'public/css/lite.css',
    'public/js/auto-logout.js',
    'public/js/lite-notifications.js',
    'public/favicon.svg',
    'public/favicon.ico',
    'public/apple-touch-icon.png',
    'public/site.webmanifest',
    'database/migrations/2026_07_05_110000_add_security_lite_default_settings.php',
];

$ok = true;
foreach ($checks as $file) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (is_file($path)) {
        echo "OK: {$file}\n";
    } else {
        echo "MISSING: {$file}\n";
        $ok = false;
    }
}

$routeFile = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$routeContent = is_file($routeFile) ? file_get_contents($routeFile) : '';
$routeNeedles = [
    "prefix('lite')" => 'lite route group',
    "LiteController::class, 'index'" => 'lite.index action',
    "LiteController::class, 'documents'" => 'lite.documents action',
    "LiteController::class, 'memos'" => 'lite.memos action',
    "LiteController::class, 'poll'" => 'lite notifications poll action',
    "session.activity" => 'session.activity route',
];

foreach ($routeNeedles as $needle => $label) {
    if (str_contains($routeContent, $needle)) {
        echo "OK route: {$label}\n";
    } else {
        echo "MISSING route: {$label}\n";
        $ok = false;
    }
}

$layout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
$layoutContent = is_file($layout) ? file_get_contents($layout) : '';
foreach (['favicon.svg', 'auto-logout.js', 'route(\'lite.index\')'] as $needle) {
    if (str_contains($layoutContent, $needle)) {
        echo "OK layout contains: {$needle}\n";
    } else {
        echo "MISSING layout contains: {$needle}\n";
        $ok = false;
    }
}

echo $ok ? "RESULT: OK\n" : "RESULT: FAILED\n";
exit($ok ? 0 : 1);

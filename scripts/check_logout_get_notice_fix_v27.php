<?php

$root = dirname(__DIR__);
$checks = [
    'AuthController' => $root . '/app/Http/Controllers/AuthController.php',
    'Routes' => $root . '/routes/web.php',
    'Logout notice view' => $root . '/resources/views/auth/logout-notice.blade.php',
];

$ok = true;
foreach ($checks as $label => $path) {
    if (is_file($path)) {
        echo "OK: {$label}\n";
    } else {
        echo "MISSING: {$label} => {$path}\n";
        $ok = false;
    }
}

$auth = is_file($checks['AuthController']) ? file_get_contents($checks['AuthController']) : '';
$routes = is_file($checks['Routes']) ? file_get_contents($checks['Routes']) : '';
$view = is_file($checks['Logout notice view']) ? file_get_contents($checks['Logout notice view']) : '';

$snippets = [
    'AuthController::logoutNotice' => str_contains($auth, 'function logoutNotice('),
    'GET logout notice route' => str_contains($routes, "Route::get('/logout'") && str_contains($routes, 'logout.notice'),
    'POST logout route still exists' => str_contains($routes, "Route::post('/logout'") && str_contains($routes, "->name('logout')"),
    'logout notice message' => str_contains($view, 'لا يمكن تسجيل الخروج من شريط العنوان'),
    'logout notice POST form' => str_contains($view, "method=\"POST\"") && str_contains($view, "route('logout')"),
];

foreach ($snippets as $label => $result) {
    echo ($result ? 'OK: ' : 'MISSING: ') . $label . "\n";
    $ok = $ok && $result;
}

echo $ok ? "RESULT: OK\n" : "RESULT: FAILED\n";
exit($ok ? 0 : 1);

<?php

$root = dirname(__DIR__);
$ok = true;

$paths = [
    'controller' => $root . '/app/Http/Controllers/SystemHealthController.php',
    'view' => $root . '/resources/views/system-health/index.blade.php',
    'routes' => $root . '/routes/web.php',
];

foreach ($paths as $label => $path) {
    if (file_exists($path)) {
        echo "OK: {$label} exists.\n";
    } else {
        echo "MISSING: {$label} at {$path}\n";
        $ok = false;
    }
}

if (file_exists($paths['routes'])) {
    $routes = file_get_contents($paths['routes']);
    if (str_contains($routes, 'SystemHealthController') && str_contains($routes, 'system-health.index')) {
        echo "OK: system health route exists.\n";
    } else {
        echo "MISSING: system health route is not registered in routes/web.php.\n";
        $ok = false;
    }
}

$middleware = $root . '/app/Http/Middleware/ApplyRoutePermissions.php';
if (file_exists($middleware)) {
    $content = file_get_contents($middleware);
    if (str_contains($content, 'system-health.')) {
        echo "OK: permission middleware protects system-health route.\n";
    } else {
        echo "WARNING: permission middleware does not explicitly protect system-health route.\n";
    }
}

exit($ok ? 0 : 1);

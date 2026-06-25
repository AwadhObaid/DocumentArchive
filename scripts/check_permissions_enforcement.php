<?php

$root = dirname(__DIR__);
$routesPath = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$middlewarePath = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Middleware' . DIRECTORY_SEPARATOR . 'ApplyRoutePermissions.php';

$ok = true;

if (!file_exists($middlewarePath)) {
    echo "MISSING: app/Http/Middleware/ApplyRoutePermissions.php\n";
    $ok = false;
} else {
    echo "OK: ApplyRoutePermissions middleware exists.\n";
}

if (!file_exists($routesPath)) {
    echo "MISSING: routes/web.php\n";
    $ok = false;
} else {
    $routes = file_get_contents($routesPath);
    if (str_contains($routes, 'ApplyRoutePermissions::class')) {
        echo "OK: routes/web.php uses ApplyRoutePermissions.\n";
    } else {
        echo "MISSING: routes/web.php does not use ApplyRoutePermissions.\n";
        $ok = false;
    }

    if (str_contains($routes, 'use App\\Http\\Middleware\\ApplyRoutePermissions;')) {
        echo "OK: middleware import exists.\n";
    } else {
        echo "MISSING: middleware import is missing.\n";
        $ok = false;
    }
}

exit($ok ? 0 : 1);

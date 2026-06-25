<?php

$root = dirname(__DIR__);
$checks = [
    'app/Http/Controllers/ProfileController.php',
    'resources/views/profile/edit.blade.php',
    'routes/web.php',
];

$ok = true;
foreach ($checks as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!file_exists($path)) {
        echo "ERROR: Missing {$relative}" . PHP_EOL;
        $ok = false;
    } else {
        echo "OK: {$relative}" . PHP_EOL;
    }
}

$routes = file_get_contents($root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php');
foreach (['profile.edit', 'profile.update', 'profile.password.update'] as $routeName) {
    if (!str_contains($routes, $routeName)) {
        echo "ERROR: Route {$routeName} not found in routes/web.php" . PHP_EOL;
        $ok = false;
    } else {
        echo "OK: Route {$routeName}" . PHP_EOL;
    }
}

exit($ok ? 0 : 1);

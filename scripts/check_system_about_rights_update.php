<?php

$root = dirname(__DIR__);
$checks = [
    'Controller' => $root . '/app/Http/Controllers/SystemAboutController.php',
    'View' => $root . '/resources/views/system-about/index.blade.php',
    'CSS' => $root . '/public/css/system-about.css',
    'Route file' => $root . '/routes/web.php',
    'Layout' => $root . '/resources/views/layouts/app.blade.php',
    'Permission registry' => $root . '/app/Support/PermissionRegistry.php',
    'Permission middleware' => $root . '/app/Http/Middleware/ApplyRoutePermissions.php',
];

$ok = true;
foreach ($checks as $label => $path) {
    if (is_file($path)) {
        echo "OK: {$label}
";
    } else {
        echo "MISSING: {$label} => {$path}
";
        $ok = false;
    }
}

$web = file_get_contents($root . '/routes/web.php');
$layout = file_get_contents($root . '/resources/views/layouts/app.blade.php');
$perm = file_get_contents($root . '/app/Support/PermissionRegistry.php');
$middleware = file_get_contents($root . '/app/Http/Middleware/ApplyRoutePermissions.php');

$snippets = [
    'route system-rights.index' => str_contains($web, "system-rights.index"),
    'route SystemAboutController' => str_contains($web, "SystemAboutController"),
    'layout system-about.css' => str_contains($layout, "system-about.css"),
    'layout system-rights.index' => str_contains($layout, "system-rights.index"),
    'permission system_about.view' => str_contains($perm, "system_about.view"),
    'middleware system-rights.*' => str_contains($middleware, "system-rights.*"),
];

foreach ($snippets as $label => $result) {
    echo ($result ? 'OK: ' : 'MISSING: ') . $label . "
";
    $ok = $ok && $result;
}

echo $ok ? "RESULT: OK
" : "RESULT: FAILED
";
exit($ok ? 0 : 1);

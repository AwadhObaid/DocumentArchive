<?php

$root = dirname(__DIR__);

function v75v80_route_v2_check_fail(string $message): void
{
    fwrite(STDERR, "[FAIL] {$message}" . PHP_EOL);
    exit(1);
}

$routesFile = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (! is_file($routesFile)) {
    v75v80_route_v2_check_fail('routes/web.php is missing.');
}
if (! is_file($autoload)) {
    v75v80_route_v2_check_fail('vendor/autoload.php is missing. Run composer install first.');
}

$text = file_get_contents($routesFile);
if ($text === false) {
    v75v80_route_v2_check_fail('Unable to read routes/web.php.');
}

$requiredTextChecks = [
    'V75/V80 route start marker' => '// attachments-scanner-v75-v80-routes:start',
    'V75/V80 route end marker' => '// attachments-scanner-v75-v80-routes:end',
    'Attachment relocation FQCN handler' => '[\\App\\Http\\Controllers\\AttachmentRelocationController::class',
    'Scanner inbox FQCN handler' => '[\\App\\Http\\Controllers\\ScannerInboxController::class',
    'attachments-migration route group' => "name('attachments-migration.')",
    'scanner-inbox route group' => "name('scanner-inbox.')",
];

foreach ($requiredTextChecks as $label => $needle) {
    if (! str_contains($text, $needle)) {
        v75v80_route_v2_check_fail("Missing: {$label}");
    }
}

foreach (['AttachmentRelocationController@index', 'ScannerInboxController@index'] as $legacyAction) {
    if (str_contains($text, $legacyAction)) {
        v75v80_route_v2_check_fail("Legacy string controller action is still present: {$legacyAction}");
    }
}

require $autoload;

if (! class_exists(\App\Http\Controllers\AttachmentRelocationController::class)) {
    v75v80_route_v2_check_fail('AttachmentRelocationController cannot be autoloaded.');
}
if (! class_exists(\App\Http\Controllers\ScannerInboxController::class)) {
    v75v80_route_v2_check_fail('ScannerInboxController cannot be autoloaded.');
}

$app = require $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$router = $app->make('router');
$expectedActions = [
    'attachments-migration.index' => 'App\\Http\\Controllers\\AttachmentRelocationController@index',
    'attachments-migration.dry-run' => 'App\\Http\\Controllers\\AttachmentRelocationController@dryRun',
    'attachments-migration.execute' => 'App\\Http\\Controllers\\AttachmentRelocationController@execute',
    'attachments-migration.show' => 'App\\Http\\Controllers\\AttachmentRelocationController@show',
    'scanner-inbox.index' => 'App\\Http\\Controllers\\ScannerInboxController@index',
    'scanner-inbox.update-path' => 'App\\Http\\Controllers\\ScannerInboxController@updatePath',
    'scanner-inbox.attach' => 'App\\Http\\Controllers\\ScannerInboxController@attach',
];

foreach ($expectedActions as $routeName => $expectedAction) {
    $route = $router->getRoutes()->getByName($routeName);
    if ($route === null) {
        v75v80_route_v2_check_fail("Route is missing: {$routeName}");
    }

    $actualAction = ltrim($route->getActionName(), '\\');
    if ($actualAction !== $expectedAction) {
        v75v80_route_v2_check_fail("Route {$routeName} action is {$actualAction}; expected {$expectedAction}");
    }
}

echo "Attachments/scanner route registration V2 check passed." . PHP_EOL;

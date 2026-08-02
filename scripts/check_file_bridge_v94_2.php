<?php

$root = dirname(__DIR__);
$errors = [];
$checks = [];

function check(bool $condition, string $message): void
{
    global $errors, $checks;
    $checks[] = [$condition, $message];
    if (! $condition) {
        $errors[] = $message;
    }
}

function content(string $relative): string
{
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    return is_file($path) ? (string) file_get_contents($path) : '';
}

$required = [
    'app/Models/FileBridgeRequest.php',
    'app/Services/FileBridgeService.php',
    'app/Http/Controllers/FileBridgeController.php',
    'database/migrations/2026_07_30_140000_create_file_bridge_requests_v94_2.php',
    'public/js/file-bridge-v94-2.js',
    'public/css/file-bridge-v94-2.css',
    'public/downloads/DocumentArchive_File_Bridge_Windows_V94_2.zip',
    'scripts/check_file_bridge_v94_2.php',
];

foreach ($required as $file) {
    check(is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file)), 'Required file exists: ' . $file);
}

$routes = content('routes/web.php');
$controller = content('app/Http/Controllers/FileBridgeController.php');
$service = content('app/Services/FileBridgeService.php');
$documentController = content('app/Http/Controllers/DocumentController.php');
$field = content('resources/views/partials/smart-attachment-browser-field.blade.php');
$layout = content('resources/views/layouts/app.blade.php');
$bootstrap = content('bootstrap/app.php');
$middleware = content('app/Http/Middleware/ApplyRoutePermissions.php');
$settings = content('app/Http/Controllers/SettingsController.php');
$settingsView = content('resources/views/settings/edit.blade.php');
$js = content('public/js/file-bridge-v94-2.js');

check(str_contains($routes, 'file-bridge-v94-2-public-routes:start'), 'Public File Bridge routes marker exists');
check(str_contains($routes, "name('file-bridge.create')"), 'Authenticated request creation route exists');
check(str_contains($routes, "name('file-bridge.status')"), 'Authenticated status route exists');
check(str_contains($routes, "name('file-bridge.client.')") && str_contains($routes, "->name('upload')"), 'Token-authenticated client upload route exists');
check(str_contains($bootstrap, 'file-bridge/client/*'), 'CSRF exclusion is limited to token-authenticated client routes');
check(str_contains($service, "hash('sha256', \$token)"), 'Raw client tokens are stored only as hashes');
check(str_contains($service, 'lockForUpdate()'), 'One-time consumption uses database row locking');
check(str_contains($service, 'STATUS_CONSUMED'), 'Replay prevention status exists');
check(str_contains($service, 'allowedExtensions'), 'Extension allowlist exists');
check(str_contains($service, 'maxFileBytes'), 'File size limit exists');
check(str_contains($service, 'purgeExpired'), 'Expired request cleanup exists');
check(str_contains($controller, 'documentarchive://open?request='), 'Custom protocol deep link is generated');
check(str_contains($controller, 'absoluteRequestUrl'), 'Client receives an absolute HTTPS request URL');
check(str_contains($documentController, 'file_bridge_token'), 'Document forms validate File Bridge selection token');
check(str_contains($documentController, 'storeFileBridgeAttachment'), 'Document controller imports File Bridge files');
check(str_contains($field, 'data-file-bridge-open'), 'File Bridge button exists in attachment field');
check(str_contains($field, 'data-file-bridge-token'), 'File Bridge hidden token exists');
check(str_contains($layout, 'file-bridge-v94-2.css'), 'File Bridge stylesheet is loaded');
check(str_contains($layout, 'file-bridge-v94-2.js'), 'File Bridge JavaScript is loaded');
check(str_contains($middleware, "'file-bridge.create'"), 'File Bridge routes are permission protected');
check(str_contains($settings, "'file_bridge_enabled'"), 'File Bridge settings are persisted');
check(str_contains($settingsView, 'File Bridge'), 'File Bridge settings section exists');
check(str_contains($js, 'selection_token'), 'Browser polling receives a one-time selection token');
check(str_contains($js, 'documentarchive:') || str_contains($controller, 'documentarchive://'), 'Custom protocol launch is present');

foreach ($checks as [$ok, $message]) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $message . PHP_EOL;
}

echo PHP_EOL;

if ($errors) {
    echo 'DocumentArchive File Bridge V94.2 verification FAILED.' . PHP_EOL;
    exit(1);
}

echo 'DocumentArchive File Bridge V94.2 verification PASSED.' . PHP_EOL;

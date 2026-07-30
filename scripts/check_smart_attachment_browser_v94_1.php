<?php

declare(strict_types=1);

$projectRoot = $argv[1] ?? dirname(__DIR__);
$projectRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $projectRoot), DIRECTORY_SEPARATOR);

$failures = 0;

function checkResult(bool $condition, string $message): void
{
    global $failures;

    echo $condition ? "[ OK ] {$message}\n" : "[FAIL] {$message}\n";

    if (! $condition) {
        $failures++;
    }
}

function projectFile(string $root, string $relative): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function readFileText(string $path): string
{
    return is_file($path) ? (string) file_get_contents($path) : '';
}

echo "DocumentArchive Smart Attachment Browser V94.1 verification\n";
echo "===========================================================\n";

$requiredFiles = [
    'app/Services/SmartAttachmentBrowserService.php',
    'app/Http/Controllers/SmartAttachmentBrowserController.php',
    'app/Http/Controllers/SettingsController.php',
    'app/Http/Controllers/DocumentController.php',
    'app/Http/Middleware/ApplyRoutePermissions.php',
    'resources/views/settings/edit.blade.php',
    'resources/views/documents/create.blade.php',
    'resources/views/documents/edit.blade.php',
    'resources/views/partials/smart-attachment-browser-field.blade.php',
    'resources/views/layouts/app.blade.php',
    'public/css/smart-attachment-browser-v94-1.css',
    'public/js/smart-attachment-browser-v94-1.js',
    'routes/web.php',
];

foreach ($requiredFiles as $relative) {
    checkResult(is_file(projectFile($projectRoot, $relative)), "Required file exists: {$relative}");
}

$service = readFileText(projectFile($projectRoot, 'app/Services/SmartAttachmentBrowserService.php'));
$controller = readFileText(projectFile($projectRoot, 'app/Http/Controllers/SmartAttachmentBrowserController.php'));
$settingsController = readFileText(projectFile($projectRoot, 'app/Http/Controllers/SettingsController.php'));
$documentController = readFileText(projectFile($projectRoot, 'app/Http/Controllers/DocumentController.php'));
$middleware = readFileText(projectFile($projectRoot, 'app/Http/Middleware/ApplyRoutePermissions.php'));
$settingsView = readFileText(projectFile($projectRoot, 'resources/views/settings/edit.blade.php'));
$createView = readFileText(projectFile($projectRoot, 'resources/views/documents/create.blade.php'));
$editView = readFileText(projectFile($projectRoot, 'resources/views/documents/edit.blade.php'));
$partial = readFileText(projectFile($projectRoot, 'resources/views/partials/smart-attachment-browser-field.blade.php'));
$layout = readFileText(projectFile($projectRoot, 'resources/views/layouts/app.blade.php'));
$js = readFileText(projectFile($projectRoot, 'public/js/smart-attachment-browser-v94-1.js'));
$routes = readFileText(projectFile($projectRoot, 'routes/web.php'));

checkResult(
    str_contains($service, 'class SmartAttachmentBrowserService'),
    'Smart attachment browser service is defined'
);

checkResult(
    str_contains($service, 'Remove bidi/direction marks'),
    'Arabic direction marks are sanitized from configured paths'
);

checkResult(
    str_contains($service, 'TOKEN_TTL_SECONDS'),
    'File selections use expiring encrypted tokens'
);

checkResult(
    str_contains($service, "part === '..'"),
    'Path traversal is rejected'
);

checkResult(
    str_contains($service, 'MAX_RECURSION_DEPTH'),
    'Recursive search depth is limited'
);

checkResult(
    str_contains($controller, 'authorizeBrowser'),
    'Search and preview endpoints enforce authorization'
);

checkResult(
    str_contains($controller, "'Cache-Control' => 'no-store, private'"),
    'Search responses disable caching'
);

checkResult(
    str_contains($settingsController, "'smart_attachment_outgoing_path'"),
    'Outgoing source path is stored in settings'
);

checkResult(
    str_contains($settingsController, "'smart_attachment_incoming_path'"),
    'Incoming source path is stored in settings'
);

checkResult(
    str_contains($settingsController, "'smart_attachment_general_path'"),
    'General source path is stored in settings'
);

checkResult(
    str_contains($documentController, "'smart_attachment_token'"),
    'Document forms accept a smart source token'
);

checkResult(
    str_contains($documentController, 'storeSmartAttachment'),
    'Selected source files are copied into DocumentArchive storage'
);

checkResult(
    str_contains($documentController, 'attachment.smart_browser_imported'),
    'Smart attachment imports are activity logged'
);

checkResult(
    str_contains($middleware, "'smart-attachment-browser.search'"),
    'Smart browser routes are permission protected'
);

checkResult(
    str_contains($settingsView, 'smart-attachment-browser-v94-1-settings:start'),
    'Smart browser settings section exists'
);

checkResult(
    str_contains($settingsView, '\\\\192.168.1.202'),
    'UNC path example exists in settings'
);

checkResult(
    str_contains($createView, "partials.smart-attachment-browser-field"),
    'Create document page uses the smart browser field'
);

checkResult(
    str_contains($editView, "partials.smart-attachment-browser-field"),
    'Edit document page uses the smart browser field'
);

checkResult(
    str_contains($partial, 'data-smart-attachment-open'),
    'Custom browser button exists'
);

checkResult(
    str_contains($partial, 'data-smart-attachment-native'),
    'Traditional device picker fallback remains available'
);

checkResult(
    substr_count($layout, 'smart-attachment-browser-v94-1-css:start') === 1,
    'Smart browser stylesheet is loaded once'
);

checkResult(
    substr_count($layout, 'smart-attachment-browser-v94-1-js:start') === 1,
    'Smart browser JavaScript is loaded once'
);

checkResult(
    str_contains($js, 'data-smart-attachment-field'),
    'JavaScript discovers smart attachment fields'
);

checkResult(
    str_contains($js, 'اختيار من الجهاز'),
    'Client-local fallback is explained in the custom dialog'
);

checkResult(
    substr_count($routes, 'smart-attachment-browser-v94-1-routes:start') === 1,
    'Smart browser route marker exists once'
);

foreach ([
    'smart-attachment-browser.sources',
    'smart-attachment-browser.search',
    'smart-attachment-browser.preview',
] as $routeName) {
    checkResult(str_contains($routes, $routeName), "Route exists: {$routeName}");
}

echo "\n";

if ($failures > 0) {
    echo "Smart Attachment Browser V94.1 verification FAILED.\n";
    echo "Failures: {$failures}\n";
    exit(1);
}

echo "Smart Attachment Browser V94.1 verification PASSED.\n";
exit(0);

<?php

$root = dirname(__DIR__);
$checks = [];

function v44_check(bool $ok, string $message, array &$checks): void
{
    $checks[] = ['ok' => $ok, 'message' => $message];
}

function v44_file_contains(string $path, array $needles, array &$checks): void
{
    if (! is_file($path)) {
        v44_check(false, 'Missing file: ' . $path, $checks);
        return;
    }

    $content = file_get_contents($path);
    foreach ($needles as $needle) {
        v44_check(str_contains($content, $needle), basename($path) . ' contains: ' . $needle, $checks);
    }
}

v44_file_contains($root . '/app/Http/Middleware/PreventBrowserCacheV44.php', [
    'class PreventBrowserCacheV44',
    'no-store, no-cache, must-revalidate',
    'Clear-Site-Data',
], $checks);

v44_file_contains($root . '/public/js/auth-no-autofill-v44.js', [
    'auth-no-autofill',
    'autocomplete',
    'new-password',
    'data-da-login-form',
    'data-da-remember-disabled-v44',
], $checks);

v44_file_contains($root . '/bootstrap/app.php', [
    'PreventBrowserCacheV44::class',
    'auth-no-autofill-v44:start',
], $checks);

$viewsRoot = $root . '/resources/views';
$loginViewPatched = false;
if (is_dir($viewsRoot)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && str_ends_with($fileInfo->getFilename(), '.blade.php')) {
            $content = file_get_contents($fileInfo->getPathname());
            if (str_contains($content, 'auth-no-autofill-v44.js') && str_contains($content, 'data-da-login-form')) {
                $loginViewPatched = true;
                break;
            }
        }
    }
}
v44_check($loginViewPatched, 'A login Blade view includes auth-no-autofill-v44.js and data-da-login-form', $checks);

$failed = array_filter($checks, fn ($check) => ! $check['ok']);
foreach ($checks as $check) {
    echo ($check['ok'] ? '[OK] ' : '[FAIL] ') . $check['message'] . PHP_EOL;
}

if ($failed) {
    echo PHP_EOL . 'Auth no-autofill V44 check failed. Run: php scripts/apply_auth_no_autofill_v44.php' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Auth no-autofill V44 check passed.' . PHP_EOL;

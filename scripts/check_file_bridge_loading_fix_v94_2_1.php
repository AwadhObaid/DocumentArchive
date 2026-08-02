<?php

$root = dirname(__DIR__);
$errors = [];

function verify(bool $condition, string $message): void
{
    global $errors;
    echo ($condition ? '[ OK ] ' : '[FAIL] ') . $message . PHP_EOL;
    if (! $condition) {
        $errors[] = $message;
    }
}

function readProjectFile(string $relative): string
{
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    return is_file($path) ? (string) file_get_contents($path) : '';
}

$fileBridge = readProjectFile('public/js/file-bridge-v94-2.js');
$globalLoading = readProjectFile('public/js/global-operation-loading-v90.js');

verify($fileBridge !== '', 'File Bridge JavaScript exists');
verify($globalLoading !== '', 'Global loading JavaScript exists');
verify(str_contains($fileBridge, '__daSkipNextBeforeUnloadLoading = true'), 'File Bridge sets protocol launch guard');
verify(str_contains($fileBridge, 'releaseProtocolLaunchGuard'), 'File Bridge releases the protocol launch guard');
verify(str_contains($fileBridge, "window.addEventListener('focus'"), 'Browser focus recovery is enabled');
verify(str_contains($fileBridge, "document.addEventListener('visibilitychange'"), 'Browser visibility recovery is enabled');
verify(str_contains($fileBridge, 'DocumentArchiveLoading?.reset?.()'), 'File Bridge resets stale global loading overlays');
verify(str_contains($globalLoading, 'window.__daSkipNextBeforeUnloadLoading === true'), 'Global loading ignores File Bridge custom protocol navigation');
verify(str_contains($globalLoading, "start('جارٍ تحميل الصفحة...'"), 'Normal page navigation loading remains enabled');

if ($errors) {
    echo PHP_EOL . 'DocumentArchive File Bridge loading fix V94.2.1 verification FAILED.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'DocumentArchive File Bridge loading fix V94.2.1 verification PASSED.' . PHP_EOL;

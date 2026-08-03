<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/public/js/global-operation-loading-v90.js';

$checks = [];

$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks[] = [$condition, $message];
    echo ($condition ? '[ OK ] ' : '[FAIL] ') . $message . PHP_EOL;
};

$check(is_file($file), 'Global loading JavaScript exists');

$content = is_file($file) ? (string) file_get_contents($file) : '';

$check(str_contains($content, 'V90.0.2'), 'Download-compatible loading version is installed');
$check(str_contains($content, 'isDownloadLikeLink'), 'Download links are detected explicitly');
$check(str_contains($content, '[data-da-download]'), 'Explicit download marker is supported');
$check(str_contains($content, '(?:download|downloads)'), 'Download route paths are excluded');
$check(str_contains($content, '__daSkipNextBeforeUnloadLoading = true'), 'Before-unload loading is skipped for downloads');
$check(str_contains($content, 'releaseDownloadNavigationGuard'), 'Download guard is released automatically');
$check(str_contains($content, "window.addEventListener('focus'"), 'Browser focus recovery is enabled');
$check(str_contains($content, "document.addEventListener('visibilitychange'"), 'Visibility recovery is enabled');
$check(str_contains($content, "start(message, { overlayDelay: 260 });"), 'Normal page navigation loading remains enabled');

$failed = array_filter($checks, static fn (array $item): bool => $item[0] === false);

echo PHP_EOL;

if ($failed !== []) {
    echo 'DocumentArchive download loading fix V94.2.2 verification FAILED.' . PHP_EOL;
    exit(1);
}

echo 'DocumentArchive download loading fix V94.2.2 verification PASSED.' . PHP_EOL;

<?php

$root = dirname(__DIR__);
$checks = [];

function v45_contains(string $file, array $needles, array &$checks): void
{
    if (! is_file($file)) {
        $checks[] = ['ok' => false, 'message' => "Missing file: {$file}"];
        return;
    }

    $content = file_get_contents($file);
    foreach ($needles as $needle) {
        $checks[] = [
            'ok' => str_contains($content, $needle),
            'message' => basename($file) . " contains: {$needle}",
        ];
    }
}

v45_contains($root . '/public/css/visual-theme-polish-v45.css', [
    'DocumentArchive Visual Theme Polish V45',
    'html[data-theme="light"]',
    'html[data-theme="dark"]',
    '.da-dashboard',
    '.da-stat-value',
    '.da-admin-alert-title',
    'body:not(.da-print-reference-page)',
    '@media print',
], $checks);

v45_contains($root . '/resources/views/layouts/app.blade.php', [
    'visual-theme-polish-v45.css',
    'visual-theme-polish-v45:start',
    "@yield('content')",
], $checks);

$failed = array_filter($checks, fn ($check) => ! $check['ok']);
foreach ($checks as $check) {
    echo ($check['ok'] ? '[OK] ' : '[FAIL] ') . $check['message'] . PHP_EOL;
}

if ($failed) {
    echo PHP_EOL . 'Visual theme polish V45 check failed.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Visual theme polish V45 check passed.' . PHP_EOL;
echo 'Light and dark UI contrast overrides are installed.' . PHP_EOL;

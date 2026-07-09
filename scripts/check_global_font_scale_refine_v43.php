<?php

$root = dirname(__DIR__);
$checks = [];

function da_v43_check_file_contains(string $file, array $needles, array &$checks): void
{
    if (! is_file($file)) {
        $checks[] = ['ok' => false, 'message' => "Missing file: {$file}"];
        return;
    }

    $content = file_get_contents($file) ?: '';
    foreach ($needles as $needle) {
        $checks[] = [
            'ok' => str_contains($content, $needle),
            'message' => basename($file) . " contains: {$needle}",
        ];
    }
}

da_v43_check_file_contains($root . '/resources/views/layouts/app.blade.php', [
    'global-font-scale-fix.css',
], $checks);

da_v43_check_file_contains($root . '/public/css/global-font-scale-fix.css', [
    'DocumentArchive Global Font Scale Refine V43',
    '@media screen',
    '--da-font-scale-v43: 0.94',
    '--da-body-font-size-v43: 14px',
    '.side-nav a',
    '.internal-chat-widget',
    '@media print',
], $checks);

$failed = array_filter($checks, fn ($check) => ! $check['ok']);

foreach ($checks as $check) {
    echo ($check['ok'] ? '[OK] ' : '[FAIL] ') . $check['message'] . PHP_EOL;
}

if ($failed) {
    echo PHP_EOL . 'Global font scale refine V43 check failed.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Global font scale refine V43 check passed.' . PHP_EOL;

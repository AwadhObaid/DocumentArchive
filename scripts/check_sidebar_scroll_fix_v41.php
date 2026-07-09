<?php

$root = dirname(__DIR__);
$checks = [];

function da_v41_check_file_contains(string $file, array $needles, array &$checks): void
{
    if (!is_file($file)) {
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

da_v41_check_file_contains($root . '/resources/views/layouts/app.blade.php', [
    'sidebar-scroll-fix-v41.css',
], $checks);

da_v41_check_file_contains($root . '/public/css/sidebar-scroll-fix-v41.css', [
    'DocumentArchive Sidebar Scroll Fix V41',
    '.sidebar .side-nav',
    'overflow-y: scroll !important',
    'min-height: 0 !important',
    'scrollbar-gutter: stable both-edges',
    '.sidebar .sidebar-footer',
    'margin-top: 0 !important',
], $checks);

$failed = array_filter($checks, fn ($check) => !$check['ok']);

foreach ($checks as $check) {
    echo ($check['ok'] ? '[OK] ' : '[FAIL] ') . $check['message'] . PHP_EOL;
}

if ($failed) {
    echo PHP_EOL . 'Sidebar scroll fix V41 check failed.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Sidebar scroll fix V41 check passed.' . PHP_EOL;

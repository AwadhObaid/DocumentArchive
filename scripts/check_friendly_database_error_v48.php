<?php

$root = dirname(__DIR__);
$checks = [];

function v48_check_file_contains(string $file, array $needles, array &$checks): void
{
    if (! is_file($file)) {
        $checks[] = ['ok' => false, 'message' => 'Missing file: ' . $file];
        return;
    }

    $content = file_get_contents($file);
    foreach ($needles as $needle) {
        $checks[] = [
            'ok' => str_contains($content, $needle),
            'message' => basename($file) . ' contains: ' . $needle,
        ];
    }
}

v48_check_file_contains($root . '/bootstrap/app.php', [
    'friendly-database-error-v48:start',
    'FriendlyDatabaseConnectionErrors::class',
], $checks);

v48_check_file_contains($root . '/app/Http/Middleware/FriendlyDatabaseConnectionErrors.php', [
    'class FriendlyDatabaseConnectionErrors',
    'isDatabaseConnectionError',
    'sqlstate[hy000] [2002]',
    'friendlyResponse',
    'errors.database-unavailable',
], $checks);

v48_check_file_contains($root . '/resources/views/errors/database-unavailable.blade.php', [
    'تعذر الاتصال بقاعدة البيانات',
    'Start All',
    'friendly-database-error-v48.css',
    'تحديث الصفحة',
], $checks);

v48_check_file_contains($root . '/public/css/friendly-database-error-v48.css', [
    'DocumentArchive Friendly Database Error V48',
    '.da-db-error-page',
    '.da-db-error-card',
    'prefers-color-scheme: dark',
], $checks);

$bootstrapContent = is_file($root . '/bootstrap/app.php') ? file_get_contents($root . '/bootstrap/app.php') : '';
$checks[] = [
    'ok' => substr_count($bootstrapContent, 'FriendlyDatabaseConnectionErrors::class') === 1,
    'message' => 'FriendlyDatabaseConnectionErrors middleware appears exactly once',
];

$failed = array_filter($checks, fn ($check) => ! $check['ok']);

foreach ($checks as $check) {
    echo ($check['ok'] ? '[OK] ' : '[FAIL] ') . $check['message'] . PHP_EOL;
}

if ($failed) {
    echo PHP_EOL . 'Friendly database error V48 check failed.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Friendly database error V48 check passed.' . PHP_EOL;

<?php

$root = dirname(__DIR__);
$checks = [];

function v49_contains(string $file, string $needle, array &$checks): void
{
    if (! is_file($file)) {
        $checks[] = [false, "Missing file: {$file}"];
        return;
    }

    $content = file_get_contents($file);
    $checks[] = [str_contains($content, $needle), basename($file) . " contains: {$needle}"];
}

v49_contains($root . '/bootstrap/app.php', 'DocumentArchive friendly database exceptions v49:start', $checks);
v49_contains($root . '/bootstrap/app.php', 'SQLSTATE[HY000] [2002]', $checks);
v49_contains($root . '/bootstrap/app.php', "response()->view('errors.database-connection'", $checks);
v49_contains($root . '/app/Http/Middleware/FriendlyDatabaseConnectionErrors.php', 'class FriendlyDatabaseConnectionErrors', $checks);
v49_contains($root . '/app/Http/Middleware/FriendlyDatabaseConnectionErrors.php', 'catch (Throwable $exception)', $checks);
v49_contains($root . '/resources/views/errors/database-connection.blade.php', 'تعذر الاتصال بقاعدة البيانات', $checks);
v49_contains($root . '/resources/views/errors/database-connection.blade.php', 'Start All', $checks);

$failed = array_filter($checks, fn ($check) => ! $check[0]);
foreach ($checks as [$ok, $message]) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $message . PHP_EOL;
}

$middleware = $root . '/app/Http/Middleware/FriendlyDatabaseConnectionErrors.php';
$view = $root . '/resources/views/errors/database-connection.blade.php';
$apply = $root . '/scripts/apply_friendly_database_error_fix_v49.php';
foreach ([$middleware, $view, $apply] as $file) {
    $cmd = 'php -l ' . escapeshellarg($file);
    exec($cmd, $output, $code);
    echo ($code === 0 ? '[OK] ' : '[FAIL] ') . "PHP syntax: {$file}" . PHP_EOL;
    if ($code !== 0) {
        $failed[] = [false, "Syntax failed: {$file}"];
    }
}

if ($failed) {
    echo PHP_EOL . 'Friendly database error fix V49 check failed.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Friendly database error fix V49 check passed.' . PHP_EOL;

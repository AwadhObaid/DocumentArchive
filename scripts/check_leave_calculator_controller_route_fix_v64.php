<?php

$root = dirname(__DIR__);
$failed = false;

function v64c_path(string $relative): string
{
    global $root;
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function v64c_ok(string $message): void
{
    echo "[OK] {$message}" . PHP_EOL;
}

function v64c_fail(string $message): void
{
    global $failed;
    $failed = true;
    echo "[FAIL] {$message}" . PHP_EOL;
}

function v64c_must_exist(string $relative): void
{
    if (is_file(v64c_path($relative))) {
        v64c_ok("File exists: {$relative}");
    } else {
        v64c_fail("Missing file: {$relative}");
    }
}

function v64c_must_contain(string $relative, string $needle): void
{
    $path = v64c_path($relative);
    if (!is_file($path)) {
        v64c_fail("Cannot inspect missing file: {$relative}");
        return;
    }

    $content = file_get_contents($path);
    if ($content !== false && str_contains($content, $needle)) {
        v64c_ok("{$relative} contains: {$needle}");
    } else {
        v64c_fail("{$relative} missing: {$needle}");
    }
}

function v64c_must_not_contain(string $relative, string $needle): void
{
    $path = v64c_path($relative);
    if (!is_file($path)) {
        v64c_fail("Cannot inspect missing file: {$relative}");
        return;
    }

    $content = file_get_contents($path);
    if ($content !== false && !str_contains($content, $needle)) {
        v64c_ok("{$relative} does not contain invalid string: {$needle}");
    } else {
        v64c_fail("{$relative} still contains invalid string: {$needle}");
    }
}

function v64c_php_lint(string $relative): void
{
    $path = v64c_path($relative);
    if (!is_file($path)) {
        v64c_fail("Missing PHP file for syntax check: {$relative}");
        return;
    }

    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $output, $code);
    if ($code === 0) {
        v64c_ok("PHP syntax valid: {$relative}");
    } else {
        v64c_fail("PHP syntax invalid: {$relative} => " . implode(' ', $output));
    }
}

v64c_must_exist('app/Http/Controllers/LeaveCalculatorController.php');
v64c_must_exist('resources/views/tools/leave-calculator.blade.php');
v64c_must_exist('routes/web.php');

v64c_php_lint('app/Http/Controllers/LeaveCalculatorController.php');

v64c_must_contain('app/Http/Controllers/LeaveCalculatorController.php', 'namespace App\\Http\\Controllers;');
v64c_must_contain('routes/web.php', 'use App\\Http\\Controllers\\LeaveCalculatorController;');
v64c_must_contain('routes/web.php', "[LeaveCalculatorController::class, 'index']");
v64c_must_contain('routes/web.php', "tools.leave-calculator.index");
v64c_must_not_contain('routes/web.php', 'LeaveCalculatorController@index');

$artisan = v64c_path('artisan');
if (is_file($artisan)) {
    $cmd = 'php ' . escapeshellarg($artisan) . ' route:list --name=tools.leave-calculator.index 2>&1';
    exec($cmd, $output, $code);
    $text = implode(PHP_EOL, $output);
    if ($code === 0 && str_contains($text, 'tools.leave-calculator.index')) {
        v64c_ok('Laravel route list resolves tools.leave-calculator.index');
    } else {
        v64c_fail('Laravel route list did not resolve tools.leave-calculator.index: ' . $text);
    }
}

if ($failed) {
    echo PHP_EOL . "Leave calculator controller route fix V64 check failed." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "Leave calculator controller route fix V64 check passed." . PHP_EOL;
echo "No migration required. No npm build required." . PHP_EOL;

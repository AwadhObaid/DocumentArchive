<?php

$root = dirname(__DIR__);
$failed = false;

function cpath(string $relative): string
{
    global $root;
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function ok(string $message): void
{
    echo "[OK] {$message}" . PHP_EOL;
}

function fail_check(string $message): void
{
    global $failed;
    $failed = true;
    echo "[FAIL] {$message}" . PHP_EOL;
}

function must_exist(string $relative): void
{
    if (is_file(cpath($relative))) {
        ok("File exists: {$relative}");
    } else {
        fail_check("Missing file: {$relative}");
    }
}

function must_contain(string $relative, string $needle, string $label = ''): void
{
    $path = cpath($relative);
    if (!is_file($path)) {
        fail_check("Cannot inspect missing file: {$relative}");
        return;
    }
    $content = file_get_contents($path);
    if ($content !== false && str_contains($content, $needle)) {
        ok(($label ?: $relative) . " contains: {$needle}");
    } else {
        fail_check(($label ?: $relative) . " missing: {$needle}");
    }
}

function php_syntax(string $relative): void
{
    $path = cpath($relative);
    if (!is_file($path)) {
        fail_check("Missing PHP file for syntax check: {$relative}");
        return;
    }
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $output, $code);
    if ($code === 0) {
        ok("PHP syntax valid: {$relative}");
    } else {
        fail_check("PHP syntax invalid: {$relative} => " . implode(' ', $output));
    }
}

$files = [
    'app/Http/Controllers/LeaveCalculatorController.php',
    'resources/views/tools/leave-calculator.blade.php',
    'public/css/leave-calculator-v63.css',
    'public/js/leave-calculator-v63.js',
    'routes/web.php',
    'app/Http/Middleware/ApplyRoutePermissions.php',
    'app/Support/PermissionRegistry.php',
    'resources/views/layouts/app.blade.php',
];

foreach ($files as $file) {
    must_exist($file);
}

php_syntax('app/Http/Controllers/LeaveCalculatorController.php');
php_syntax('app/Http/Middleware/ApplyRoutePermissions.php');
php_syntax('app/Support/PermissionRegistry.php');

must_contain('routes/web.php', 'LeaveCalculatorController');
must_contain('routes/web.php', "tools.leave-calculator.index");
must_contain('app/Http/Middleware/ApplyRoutePermissions.php', "'tools.leave-calculator.index' => 'tools.leave_calculator'");
must_contain('app/Support/PermissionRegistry.php', "'tools.leave_calculator' => 'حاسبة الإجازات'");
must_contain('app/Support/PermissionRegistry.php', "'tools.leave_calculator'");
must_contain('resources/views/layouts/app.blade.php', "tools.leave-calculator.index", 'sidebar');
must_contain('resources/views/tools/leave-calculator.blade.php', 'حاسبة الإجازات');
must_contain('resources/views/tools/leave-calculator.blade.php', 'صافي الأيام بدون عطل');
must_contain('public/js/leave-calculator-v63.js', 'leaveCalculateButton');
must_contain('public/css/leave-calculator-v63.css', '.leave-calculator-page');

if ($failed) {
    echo PHP_EOL . "Leave calculator V63 check failed." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "Leave calculator V63 check passed." . PHP_EOL;
echo "No migration required. No npm build required." . PHP_EOL;

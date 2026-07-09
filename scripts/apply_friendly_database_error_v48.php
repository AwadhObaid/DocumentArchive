<?php

$root = dirname(__DIR__);
$bootstrap = $root . '/bootstrap/app.php';
$middlewareFile = $root . '/app/Http/Middleware/FriendlyDatabaseConnectionErrors.php';
$viewFile = $root . '/resources/views/errors/database-unavailable.blade.php';
$cssFile = $root . '/public/css/friendly-database-error-v48.css';

function v48_fail(string $message): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

function v48_ok(string $message): void
{
    echo '[OK] ' . $message . PHP_EOL;
}

foreach ([$middlewareFile, $viewFile, $cssFile] as $file) {
    is_file($file) ? v48_ok('File exists: ' . $file) : v48_fail('Missing file: ' . $file);
}

if (! is_file($bootstrap)) {
    v48_fail('Missing file: ' . $bootstrap);
}

$content = file_get_contents($bootstrap);
$original = $content;
$block = "        // friendly-database-error-v48:start\n        \$middleware->prepend(\\App\\Http\\Middleware\\FriendlyDatabaseConnectionErrors::class);\n        // friendly-database-error-v48:end\n";

$content = preg_replace(
    '/\s*\/\/ friendly-database-error-v48:start\R.*?\/\/ friendly-database-error-v48:end\R/s',
    "\n",
    $content
);

if (! str_contains($content, '->withMiddleware(function (Middleware $middleware): void {')) {
    v48_fail('Could not locate withMiddleware closure in bootstrap/app.php.');
}

$content = str_replace(
    "->withMiddleware(function (Middleware \$middleware): void {\n",
    "->withMiddleware(function (Middleware \$middleware): void {\n" . $block,
    $content
);

file_put_contents($bootstrap, $content);

if ($content !== $original) {
    v48_ok('bootstrap/app.php updated with FriendlyDatabaseConnectionErrors middleware.');
} else {
    v48_ok('bootstrap/app.php already updated.');
}

require __DIR__ . '/check_friendly_database_error_v48.php';

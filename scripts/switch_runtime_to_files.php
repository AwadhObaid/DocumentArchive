<?php

$root = dirname(__DIR__);
$envPath = $root . DIRECTORY_SEPARATOR . '.env';

if (!file_exists($envPath)) {
    fwrite(STDERR, "ERROR: .env file not found. Run this script from the project root.\n");
    exit(1);
}

$env = file_get_contents($envPath);
if ($env === false) {
    fwrite(STDERR, "ERROR: Unable to read .env.\n");
    exit(1);
}

function setEnvValue(string $env, string $key, string $value): string
{
    $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
    $line = $key . '=' . $value;

    if (preg_match($pattern, $env)) {
        return preg_replace($pattern, $line, $env);
    }

    return rtrim($env) . PHP_EOL . $line . PHP_EOL;
}

$env = setEnvValue($env, 'SESSION_DRIVER', 'file');
$env = setEnvValue($env, 'CACHE_STORE', 'file');
$env = setEnvValue($env, 'QUEUE_CONNECTION', 'sync');

file_put_contents($envPath, $env);

echo "OK: .env updated:\n";
echo "- SESSION_DRIVER=file\n";
echo "- CACHE_STORE=file\n";
echo "- QUEUE_CONNECTION=sync\n";

$dirs = [
    $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'sessions',
    $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache',
    $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'data',
    $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

echo "OK: storage/framework runtime folders checked.\n";

$cacheDir = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
        @unlink($file);
    }
    echo "OK: bootstrap/cache PHP files removed.\n";
}

echo "Done. Now run:\n";
echo "php artisan config:clear\n";
echo "php artisan optimize:clear\n";

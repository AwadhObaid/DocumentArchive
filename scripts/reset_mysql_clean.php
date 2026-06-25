<?php
/**
 * DocumentArchive - Clean MySQL reset helper
 *
 * This script is intentionally destructive for the configured MySQL database only.
 * It drops all tables/views in DB_DATABASE, then runs migrations and AdminUserSeeder.
 * Use only on the development/local database after confirming .env points to MySQL.
 */

$root = dirname(__DIR__);
chdir($root);

function fail(string $message): void
{
    fwrite(STDERR, "\n[ERROR] " . $message . "\n");
    exit(1);
}

function info(string $message): void
{
    fwrite(STDOUT, "[INFO] " . $message . "\n");
}

function parseEnvFile(string $path): array
{
    if (!is_file($path)) {
        fail('.env file was not found at: ' . $path);
    }

    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        $env[$key] = $value;
    }

    return $env;
}

$env = parseEnvFile($root . DIRECTORY_SEPARATOR . '.env');

$connection = strtolower($env['DB_CONNECTION'] ?? '');
if ($connection !== 'mysql') {
    fail('DB_CONNECTION is not mysql. Current value: ' . ($env['DB_CONNECTION'] ?? '(missing)'));
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$database = $env['DB_DATABASE'] ?? '';
$username = $env['DB_USERNAME'] ?? 'root';
$password = $env['DB_PASSWORD'] ?? '';

if ($database === '') {
    fail('DB_DATABASE is empty in .env');
}

if (!extension_loaded('pdo_mysql')) {
    fail('PHP extension pdo_mysql is not enabled. Enable it in php.ini first.');
}

if (!class_exists(PDO::class)) {
    fail('PDO class not found.');
}

$dsn = "mysql:host={$host};port={$port};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fail('Unable to connect to MySQL server: ' . $e->getMessage());
}

info('Connected to MySQL server.');
info('Target database: ' . $database);

$quotedDb = '`' . str_replace('`', '``', $database) . '`';

try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$quotedDb} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE {$quotedDb}");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    $viewsStmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = " . $pdo->quote($database));
    $views = $viewsStmt ? $viewsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    foreach ($views as $view) {
        $safe = '`' . str_replace('`', '``', (string) $view) . '`';
        info('Dropping view: ' . $view);
        $pdo->exec("DROP VIEW IF EXISTS {$safe}");
    }

    $tablesStmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $pdo->quote($database) . " AND TABLE_TYPE = 'BASE TABLE'");
    $tables = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    foreach ($tables as $table) {
        $safe = '`' . str_replace('`', '``', (string) $table) . '`';
        info('Dropping table: ' . $table);
        $pdo->exec("DROP TABLE IF EXISTS {$safe}");
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    info('Database tables cleaned successfully.');
} catch (Throwable $e) {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $ignored) {
        // Ignore cleanup errors.
    }
    fail('Failed while cleaning database: ' . $e->getMessage());
}

function runCommand(string $command): void
{
    info('Running: ' . $command);
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fail('Command failed with exit code ' . $exitCode . ': ' . $command);
    }
}

runCommand(PHP_BINARY . ' artisan migrate --force');
runCommand(PHP_BINARY . ' artisan db:seed --class=AdminUserSeeder --force');
runCommand(PHP_BINARY . ' artisan optimize:clear');

info('Done. MySQL database has been rebuilt successfully.');
info('Login with username: admin / password: 12345678');

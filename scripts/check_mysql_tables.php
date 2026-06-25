<?php

$root = dirname(__DIR__);
$envPath = $root . DIRECTORY_SEPARATOR . '.env';

if (!file_exists($envPath)) {
    fwrite(STDERR, "ERROR: .env file not found.\n");
    exit(1);
}

$envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$env = [];
foreach ($envLines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
}

$driver = $env['DB_CONNECTION'] ?? '';
if ($driver !== 'mysql') {
    echo "WARNING: DB_CONNECTION is not mysql. Current: {$driver}\n";
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$db = $env['DB_DATABASE'] ?? '';
$user = $env['DB_USERNAME'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? '';

$pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$required = [
    'migrations', 'users', 'departments', 'document_types', 'documents', 'document_attachments',
    'settings', 'reference_counters', 'activity_logs', 'sessions', 'cache', 'cache_locks',
    'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
];

$missing = array_values(array_diff($required, $tables));

echo "Database: {$db}\n";
echo "Tables count: " . count($tables) . "\n";

if (count($missing) === 0) {
    echo "OK: All expected tables exist.\n";
    exit(0);
}

echo "MISSING TABLES:\n";
foreach ($missing as $table) {
    echo "- {$table}\n";
}

exit(2);

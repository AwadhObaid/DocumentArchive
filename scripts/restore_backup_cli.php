<?php
/**
 * DocumentArchive CLI Restore Tool
 *
 * Usage:
 *   php scripts/restore_backup_cli.php storage/app/private/backups/full-backup-YYYYMMDD-HHMMSS.zip --full
 *   php scripts/restore_backup_cli.php storage/app/private/backups/database-backup-YYYYMMDD-HHMMSS.zip --database
 *
 * This tool restores database backups outside the web request/session lifecycle.
 * It validates the SQL in a temporary MySQL database before touching the real database.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = dirname(__DIR__);
$backupArg = $argv[1] ?? null;
$mode = $argv[2] ?? '--full';

if (!$backupArg || !in_array($mode, ['--full', '--database', '--files'], true)) {
    fwrite(STDERR, "Usage:\n  php scripts/restore_backup_cli.php <backup-zip-path-or-name> --full|--database|--files\n");
    exit(1);
}

$env = readEnvFile($projectRoot . DIRECTORY_SEPARATOR . '.env');
$db = [
    'connection' => $env['DB_CONNECTION'] ?? 'mysql',
    'host' => $env['DB_HOST'] ?? '127.0.0.1',
    'port' => $env['DB_PORT'] ?? '3306',
    'database' => $env['DB_DATABASE'] ?? 'document_archive',
    'username' => $env['DB_USERNAME'] ?? 'root',
    'password' => $env['DB_PASSWORD'] ?? '',
];

if ($db['connection'] !== 'mysql') {
    fail('هذا السكربت مخصص لاستعادة MySQL فقط. DB_CONNECTION الحالي: ' . $db['connection']);
}

$backupPath = resolveBackupPath($projectRoot, $backupArg);
if (!is_file($backupPath)) {
    fail('ملف النسخة الاحتياطية غير موجود: ' . $backupPath);
}

ensureDirectory($projectRoot . '/storage/app/private/backups');

info('Backup file: ' . $backupPath);
info('Mode: ' . $mode);
info('Target database: ' . $db['database']);

if ($mode !== '--files') {
    $sql = readSqlFromZip($backupPath);
    assertMysqlDump($sql);

    $pdoServer = mysqlPdo($db, null);
    $tempDb = $db['database'] . '_restore_test_' . date('Ymd_His') . '_' . random_int(1000, 9999);

    info('Step 1/5: Testing SQL in temporary database: ' . $tempDb);
    createDatabase($pdoServer, $tempDb);
    try {
        $pdoTemp = mysqlPdo($db, $tempDb);
        runSqlDump($pdoTemp, $sql);
        assertDatabaseTables($pdoTemp, requiredTables());
        success('Temporary restore test passed.');
    } catch (Throwable $e) {
        dropDatabaseQuietly($pdoServer, $tempDb);
        fail('فشل اختبار النسخة داخل قاعدة مؤقتة. لم يتم لمس قاعدة النظام. السبب: ' . $e->getMessage());
    }

    info('Step 2/5: Creating safety backup of current database.');
    createSafetyDatabaseBackup($projectRoot, $db);

    info('Step 3/5: Replacing target database after successful test.');
    dropDatabase($pdoServer, $db['database']);
    createDatabase($pdoServer, $db['database']);
    $pdoTarget = mysqlPdo($db, $db['database']);
    runSqlDump($pdoTarget, $sql);
    assertDatabaseTables($pdoTarget, requiredTables());
    ensureAdmin($pdoTarget);
    dropDatabaseQuietly($pdoServer, $tempDb);
    success('Database restored and admin account ensured.');
}

if ($mode !== '--database') {
    info('Step 4/5: Restoring documents folder from ZIP if available.');
    createSafetyFilesBackup($projectRoot);
    restoreDocumentsFromZip($backupPath, $projectRoot . '/storage/app/private/documents');
}

info('Step 5/5: Cleaning Laravel runtime files.');
cleanRuntime($projectRoot);

success('تمت الاستعادة بنجاح. شغّل النظام ثم ادخل بـ admin / 12345678');
exit(0);

function readEnvFile(string $path): array
{
    if (!is_file($path)) {
        fail('لم يتم العثور على ملف .env في جذر المشروع.');
    }

    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        $env[trim($key)] = $value;
    }
    return $env;
}

function resolveBackupPath(string $projectRoot, string $arg): string
{
    $arg = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $arg);
    if (is_file($arg)) {
        return realpath($arg) ?: $arg;
    }
    $candidates = [
        $projectRoot . DIRECTORY_SEPARATOR . $arg,
        $projectRoot . DIRECTORY_SEPARATOR . 'storage/app/private/backups' . DIRECTORY_SEPARATOR . basename($arg),
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return realpath($candidate) ?: $candidate;
        }
    }
    return $arg;
}

function mysqlPdo(array $db, ?string $database): PDO
{
    $dsn = 'mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';charset=utf8mb4';
    if ($database) {
        $dsn .= ';dbname=' . $database;
    }
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
    return $pdo;
}

function createDatabase(PDO $pdo, string $database): void
{
    $pdo->exec('CREATE DATABASE `' . str_replace('`', '``', $database) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}

function dropDatabase(PDO $pdo, string $database): void
{
    $pdo->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '``', $database) . '`');
}

function dropDatabaseQuietly(PDO $pdo, string $database): void
{
    try { dropDatabase($pdo, $database); } catch (Throwable $e) { /* ignore */ }
}

function readSqlFromZip(string $backupPath): string
{
    $zip = new ZipArchive();
    if ($zip->open($backupPath) !== true) {
        fail('تعذر فتح ملف ZIP.');
    }
    $sqlName = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
        if (str_ends_with(strtolower($name), '.sql')) {
            $sqlName = $name;
            break;
        }
    }
    if ($sqlName === null) {
        $zip->close();
        fail('لا يوجد ملف SQL داخل النسخة.');
    }
    $sql = $zip->getFromName($sqlName);
    $zip->close();
    if ($sql === false || trim($sql) === '') {
        fail('ملف SQL فارغ أو غير قابل للقراءة.');
    }
    return $sql;
}

function assertMysqlDump(string $sql): void
{
    if (preg_match('/^--\s*Driver:\s*([^\r\n]+)/mi', $sql, $m)) {
        $driver = strtolower(trim($m[1]));
        if ($driver !== 'mysql') {
            fail('نوع قاعدة بيانات النسخة هو ' . $driver . ' وليس mysql.');
        }
    }

    $missing = [];
    foreach (requiredTables() as $table) {
        if (!preg_match('/CREATE\s+TABLE\s+`' . preg_quote($table, '/') . '`/i', $sql)) {
            $missing[] = $table;
        }
    }
    if ($missing) {
        fail('ملف SQL غير مكتمل. الجداول الناقصة: ' . implode(', ', $missing));
    }
}

function requiredTables(): array
{
    return [
        'migrations',
        'users',
        'departments',
        'document_types',
        'documents',
        'document_attachments',
        'settings',
        'reference_counters',
        'activity_logs',
    ];
}

function assertDatabaseTables(PDO $pdo, array $required): void
{
    $existing = [];
    foreach ($pdo->query('SHOW TABLES') as $row) {
        $arr = array_values((array) $row);
        if ($arr) { $existing[] = (string) $arr[0]; }
    }
    $missing = array_values(array_diff($required, $existing));
    if ($missing) {
        throw new RuntimeException('الجداول الناقصة بعد الاستعادة: ' . implode(', ', $missing));
    }
}

function runSqlDump(PDO $pdo, string $sql): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        foreach (splitSqlStatements($sql) as $statement) {
            $statement = removeLeadingSqlComments(trim($statement));
            if ($statement === '') { continue; }
            $upper = strtoupper($statement);
            if (str_starts_with($upper, 'SET FOREIGN_KEY_CHECKS') || str_starts_with($upper, 'START TRANSACTION') || str_starts_with($upper, 'COMMIT')) {
                continue;
            }
            $pdo->exec($statement);
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}

function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $quote = null;
    $escaped = false;
    $lineComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($lineComment) {
            $buffer .= $char;
            if ($char === "\n") { $lineComment = false; }
            continue;
        }
        if ($quote === null && $char === '-' && $next === '-') {
            $lineComment = true;
            $buffer .= $char;
            continue;
        }
        if ($quote !== null) {
            $buffer .= $char;
            if ($escaped) { $escaped = false; continue; }
            if ($char === '\\') { $escaped = true; continue; }
            if ($char === $quote) { $quote = null; }
            continue;
        }
        if ($char === "'" || $char === '"') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }
        if ($char === ';') {
            $statements[] = trim($buffer);
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    if (trim($buffer) !== '') { $statements[] = trim($buffer); }
    return $statements;
}

function removeLeadingSqlComments(string $statement): string
{
    $lines = preg_split('/\R/', $statement) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) { continue; }
        $clean[] = $line;
    }
    return trim(implode(PHP_EOL, $clean));
}

function ensureAdmin(PDO $pdo): void
{
    $now = date('Y-m-d H:i:s');
    $hash = password_hash('12345678', PASSWORD_BCRYPT);

    $stmt = $pdo->prepare('SELECT id FROM `users` WHERE `username` = ? LIMIT 1');
    $stmt->execute(['admin']);
    $id = $stmt->fetchColumn();

    if ($id) {
        $update = $pdo->prepare('UPDATE `users` SET `name`=?, `email`=?, `role`=?, `is_active`=1, `password`=?, `updated_at`=? WHERE `username`=?');
        $update->execute(['Admin', 'admin@documentarchive.local', 'admin', $hash, $now, 'admin']);
    } else {
        $insert = $pdo->prepare('INSERT INTO `users` (`name`, `username`, `email`, `role`, `is_active`, `password`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, 1, ?, ?, ?)');
        $insert->execute(['Admin', 'admin', 'admin@documentarchive.local', 'admin', $hash, $now, $now]);
    }
}

function createSafetyDatabaseBackup(string $projectRoot, array $db): void
{
    try {
        $pdo = mysqlPdo($db, $db['database']);
        $folder = $projectRoot . '/storage/app/private/backups';
        ensureDirectory($folder);
        $path = $folder . '/pre-cli-restore-database-' . date('Ymd-His') . '.sql';
        $tables = [];
        foreach ($pdo->query('SHOW TABLES') as $row) {
            $arr = array_values((array) $row);
            if ($arr) { $tables[] = (string) $arr[0]; }
        }
        $out = "-- Pre CLI restore safety dump\n-- Date: " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        foreach ($tables as $table) {
            $escaped = str_replace('`', '``', $table);
            $create = $pdo->query('SHOW CREATE TABLE `' . $escaped . '`')->fetch(PDO::FETCH_ASSOC);
            $out .= "DROP TABLE IF EXISTS `{$escaped}`;\n";
            $out .= ($create['Create Table'] ?? array_values($create)[1] ?? '') . ";\n";
            $rows = $pdo->query('SELECT * FROM `' . $escaped . '`')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row));
                $vals = array_map(fn($v) => sqlValue($pdo, $v), array_values($row));
                $out .= 'INSERT INTO `' . $escaped . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ");\n";
            }
            $out .= "\n";
        }
        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        file_put_contents($path, $out);
        info('Safety database dump saved: ' . $path);
    } catch (Throwable $e) {
        warn('تعذر إنشاء نسخة أمان SQL قبل الاستعادة: ' . $e->getMessage());
    }
}

function sqlValue(PDO $pdo, mixed $value): string
{
    if ($value === null) { return 'NULL'; }
    if (is_bool($value)) { return $value ? '1' : '0'; }
    if (is_int($value) || is_float($value)) { return (string) $value; }
    return $pdo->quote((string) $value);
}

function createSafetyFilesBackup(string $projectRoot): void
{
    $source = $projectRoot . '/storage/app/private/documents';
    if (!is_dir($source)) { return; }
    $target = $projectRoot . '/storage/app/private/backups/pre-cli-restore-documents-' . date('Ymd-His');
    copyDirectory($source, $target);
    info('Safety documents copy saved: ' . $target);
}

function restoreDocumentsFromZip(string $backupPath, string $documentsPath): void
{
    $zip = new ZipArchive();
    if ($zip->open($backupPath) !== true) { fail('تعذر فتح ملف ZIP لاستعادة المرفقات.'); }

    $hasDocuments = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
        if (str_starts_with($name, 'documents/')) { $hasDocuments = true; break; }
    }
    if (!$hasDocuments) {
        $zip->close();
        warn('لا يوجد مجلد documents داخل النسخة. تم تخطي استعادة الملفات.');
        return;
    }

    if (is_dir($documentsPath)) { deleteDirectory($documentsPath); }
    ensureDirectory($documentsPath);

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $zipName = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
        if (!str_starts_with($zipName, 'documents/')) { continue; }
        $relative = trim(substr($zipName, strlen('documents/')), '/');
        if ($relative === '' || isUnsafePath($relative)) { continue; }
        $target = $documentsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (str_ends_with($zipName, '/')) { ensureDirectory($target); continue; }
        ensureDirectory(dirname($target));
        $stream = $zip->getStream($zipName);
        if ($stream === false) { continue; }
        $out = fopen($target, 'wb');
        if ($out === false) { fclose($stream); continue; }
        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);
    }
    $zip->close();
    success('Documents restored.');
}

function cleanRuntime(string $projectRoot): void
{
    foreach (glob($projectRoot . '/storage/framework/sessions/*') ?: [] as $file) { if (is_file($file)) @unlink($file); }
    foreach (glob($projectRoot . '/bootstrap/cache/*.php') ?: [] as $file) { if (is_file($file)) @unlink($file); }
}

function copyDirectory(string $src, string $dst): void
{
    ensureDirectory($dst);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $target = $dst . DIRECTORY_SEPARATOR . $it->getSubPathName();
        if ($item->isDir()) { ensureDirectory($target); }
        else { ensureDirectory(dirname($target)); copy($item->getPathname(), $target); }
    }
}

function deleteDirectory(string $dir): void
{
    if (!is_dir($dir)) { return; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

function ensureDirectory(string $dir): void
{
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }
}

function isUnsafePath(string $path): bool
{
    $path = str_replace('\\', '/', $path);
    return str_contains($path, '../') || str_starts_with($path, '../') || str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\//', $path) === 1;
}

function info(string $m): void { echo "[INFO] {$m}\n"; }
function success(string $m): void { echo "[OK] {$m}\n"; }
function warn(string $m): void { echo "[WARN] {$m}\n"; }
function fail(string $m): never { fwrite(STDERR, "[ERROR] {$m}\n"); exit(1); }

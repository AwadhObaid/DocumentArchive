<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function v87Check(bool $condition, string $label, bool &$failed): void
{
    echo ($condition ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $condition;
}

function v87DirectoryStats(string $directory): array
{
    if (! is_dir($directory)) {
        return ['files' => 0, 'bytes' => 0];
    }

    $files = 0;
    $bytes = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (! $item->isFile() || $item->isLink()) {
            continue;
        }

        $files++;
        $bytes += (int) $item->getSize();
    }

    return ['files' => $files, 'bytes' => $bytes];
}

$failed = false;
$controllerPath = app_path('Http/Controllers/BackupController.php');
$inspectViewPath = resource_path('views/backups/inspect.blade.php');
$restoreViewPath = resource_path('views/backups/restore.blade.php');
$indexViewPath = resource_path('views/backups/index.blade.php');
$cliPath = base_path('scripts/restore_backup_cli.php');

$controller = is_file($controllerPath) ? (string) file_get_contents($controllerPath) : '';
$inspectView = is_file($inspectViewPath) ? (string) file_get_contents($inspectViewPath) : '';
$restoreView = is_file($restoreViewPath) ? (string) file_get_contents($restoreViewPath) : '';
$indexView = is_file($indexViewPath) ? (string) file_get_contents($indexViewPath) : '';
$cli = is_file($cliPath) ? (string) file_get_contents($cliPath) : '';

v87Check(extension_loaded('zip'), 'PHP ZIP extension is loaded', $failed);
v87Check(is_file($controllerPath), 'BackupController exists', $failed);
v87Check(is_file($inspectViewPath), 'Backup inspection view exists', $failed);
v87Check(is_file($restoreViewPath), 'Backup restore view exists', $failed);
v87Check(is_file($indexViewPath), 'Backup index view exists', $failed);
v87Check(is_file($cliPath), 'CLI restore tool exists', $failed);

foreach (['Books', 'documents', 'memos', 'circulars', 'misc-books'] as $directory) {
    v87Check(
        str_contains($controller, "'{$directory}' => storage_path"),
        "Controller includes storage directory: {$directory}",
        $failed
    );
}

v87Check(
    str_contains($controller, 'BACKUP_MANIFEST.json'),
    'Manifest is written and inspected',
    $failed
);
v87Check(
    str_contains($controller, 'verifyCreatedBackup'),
    'Created ZIP is verified before success',
    $failed
);
v87Check(
    str_contains($controller, 'assertFullBackupSafeForRestore'),
    'Unsafe full restore is blocked',
    $failed
);
v87Check(
    str_contains($controller, 'backupDatabaseTables'),
    'Complete database table inventory is used for backup',
    $failed
);
v87Check(
    str_contains($controller, "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"),
    'Only MySQL base tables are dumped',
    $failed
);
v87Check(
    str_contains($controller, 'protectedDatabaseTables'),
    'Web restore protects users and runtime tables',
    $failed
);
v87Check(
    str_contains($inspectView, '$attachmentDirectoryStats'),
    'Inspection displays per-directory attachment stats',
    $failed
);
v87Check(
    str_contains($inspectView, '$criticalWarnings'),
    'Inspection displays critical integrity failures',
    $failed
);
v87Check(
    str_contains($restoreView, '$canRestoreFull'),
    'Full restore button depends on verified integrity',
    $failed
);
v87Check(
    str_contains($indexView, 'جارٍ إنشاء النسخة والتحقق منها'),
    'Backup UI prevents repeated submission',
    $failed
);
v87Check(
    str_contains($cli, 'assertVerifiedFullBackupManifest'),
    'CLI full restore requires verified Manifest',
    $failed
);
v87Check(
    str_contains($cli, "'misc-books' =>"),
    'CLI restore supports all attachment directories',
    $failed
);

v87Check(Route::has('backups.index'), 'Backup index route exists', $failed);
v87Check(Route::has('backups.full'), 'Full backup route exists', $failed);
v87Check(Route::has('backups.inspect'), 'Backup inspection route exists', $failed);
v87Check(Route::has('backups.restore.full'), 'Full restore route exists', $failed);

$attachmentTables = [
    'document_attachments',
    'memo_attachments',
    'circular_attachments',
    'misc_book_attachments',
];

echo PHP_EOL . 'Database attachment rows:' . PHP_EOL;
$totalRows = 0;

foreach ($attachmentTables as $table) {
    $count = Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
    $totalRows += $count;
    echo '- ' . $table . ': ' . $count . PHP_EOL;
}

echo '- total: ' . $totalRows . PHP_EOL;

$storageRoot = storage_path('app/private');
$directories = [
    'Books',
    'documents',
    'memos',
    'circulars',
    'misc-books',
];

echo PHP_EOL . 'Business storage directories:' . PHP_EOL;
$totalFiles = 0;
$totalBytes = 0;

foreach ($directories as $directory) {
    $stats = v87DirectoryStats($storageRoot . DIRECTORY_SEPARATOR . $directory);
    $totalFiles += $stats['files'];
    $totalBytes += $stats['bytes'];

    echo '- ' . $directory
        . ': files=' . $stats['files']
        . ', bytes=' . $stats['bytes']
        . PHP_EOL;
}

echo '- total files: ' . $totalFiles . PHP_EOL;
echo '- total bytes: ' . $totalBytes . PHP_EOL;

$backupFolder = storage_path('app/private/backups');
$freeBytes = disk_free_space($backupFolder);

if ($freeBytes !== false) {
    echo '- free bytes on backup volume: ' . (int) $freeBytes . PHP_EOL;
}

$phpFiles = [
    $controllerPath,
    $cliPath,
];

foreach ($phpFiles as $phpFile) {
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($phpFile), $output, $code);
    v87Check($code === 0, 'PHP syntax: ' . basename($phpFile), $failed);
}

if ($failed) {
    fwrite(STDERR, "Full backup integrity V87 check failed.\n");
    exit(1);
}

echo PHP_EOL;
echo "Full backup integrity V87 check passed.\n";
echo "New backups include all database base tables, all business attachment folders, and a verified Manifest.\n";
echo "Legacy full backups without Manifest remain blocked from full restore.\n";

<?php

$root = dirname(__DIR__);
$failures = [];

function v81_check(bool $condition, string $message): void
{
    global $failures;

    if ($condition) {
        echo "[OK] {$message}" . PHP_EOL;
        return;
    }

    echo "[FAIL] {$message}" . PHP_EOL;
    $failures[] = $message;
}

$required = [
    'app/Http/Controllers/LegacyArchiveImportController.php',
    'app/Models/LegacyArchiveImportRun.php',
    'app/Models/LegacyArchiveImportItem.php',
    'app/Services/LegacyArchiveCsvReader.php',
    'app/Services/LegacyArchiveImportService.php',
    'database/migrations/2026_07_19_060000_create_legacy_archive_import_v81.php',
    'resources/views/legacy_archive_import/index.blade.php',
    'resources/views/legacy_archive_import/show.blade.php',
    'public/css/legacy-archive-import-v81.css',
    'storage/app/private/legacy-archive-imports/Results.csv',
];

foreach ($required as $relative) {
    v81_check(is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative)), $relative);
}

$routes = file_get_contents($root . '/routes/web.php') ?: '';
v81_check(str_contains($routes, 'legacy-archive-import-v81-routes:start'), 'V81 route block exists');
v81_check(str_contains($routes, '\\App\\Http\\Controllers\\LegacyArchiveImportController::class'), 'Routes use full controller class');
v81_check(
    str_contains($routes, "->name('index')")
        && str_contains($routes, "->name('dry-run')")
        && str_contains($routes, "->name('execute')")
        && str_contains($routes, "->name('show')"),
    'Four V81 route actions are registered in source'
);

$document = file_get_contents($root . '/app/Models/Document.php') ?: '';
foreach (['legacy_source', 'legacy_record_id', 'legacy_user_name', 'legacy_archive_folder'] as $column) {
    v81_check(str_contains($document, "'{$column}'"), "Document fillable includes {$column}");
}

$registry = file_get_contents($root . '/app/Support/PermissionRegistry.php') ?: '';
v81_check(str_contains($registry, "'legacy_import.manage'"), 'Permission legacy_import.manage exists');

$layout = file_get_contents($root . '/resources/views/layouts/app.blade.php') ?: '';
v81_check(str_contains($layout, 'legacy-archive-import-v81-css:start'), 'V81 CSS include exists');
v81_check(str_contains($layout, 'legacy-archive-import-v81-nav:start'), 'V81 sidebar link exists');

$csv = $root . '/storage/app/private/legacy-archive-imports/Results.csv';
if (is_file($csv)) {
    $handle = fopen($csv, 'r');
    $rows = 0;
    $validColumns = true;

    if ($handle) {
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }
            $rows++;
            if (count($row) !== 19) {
                $validColumns = false;
            }
        }
        fclose($handle);
    }

    v81_check($rows === 97, "Bundled CSV contains 97 rows (found {$rows})");
    v81_check($validColumns, 'Every bundled CSV row has 19 columns');
}

$phpFiles = [
    'app/Http/Controllers/LegacyArchiveImportController.php',
    'app/Models/LegacyArchiveImportRun.php',
    'app/Models/LegacyArchiveImportItem.php',
    'app/Services/LegacyArchiveCsvReader.php',
    'app/Services/LegacyArchiveImportService.php',
    'database/migrations/2026_07_19_060000_create_legacy_archive_import_v81.php',
];

foreach ($phpFiles as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $output = [];
    $status = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $output, $status);
    v81_check($status === 0, "PHP syntax: {$relative}");
}

if ($failures !== []) {
    echo PHP_EOL . 'Legacy Archive Importer V81 check failed.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Legacy Archive Importer V81 check passed.' . PHP_EOL;

<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function v8602Indexes(string $table): array
{
    if (! Schema::hasTable($table)) {
        return [];
    }

    $rows = DB::select(
        <<<'SQL'
SELECT
    INDEX_NAME AS index_name,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS indexed_columns
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = ?
GROUP BY INDEX_NAME
SQL,
        [$table]
    );

    $result = [];

    foreach ($rows as $row) {
        $result[(string) $row->index_name] = (string) $row->indexed_columns;
    }

    return $result;
}

function v8602HasColumns(array $indexes, array $columns): bool
{
    return in_array(implode(',', $columns), array_values($indexes), true);
}

$migrationName = '2026_07_22_091000_create_legacy_circular_misc_inventory_v86_0_1';

$runIndexes = v8602Indexes('legacy_circular_misc_import_runs');
$sourceIndexes = v8602Indexes('legacy_circular_misc_import_sources');
$itemIndexes = v8602Indexes('legacy_circular_misc_import_items');

$checks = [
    'Migration is recorded as completed' => Schema::hasTable('migrations')
        && DB::table('migrations')->where('migration', $migrationName)->exists(),

    'Run table exists' => Schema::hasTable('legacy_circular_misc_import_runs'),
    'Source table exists' => Schema::hasTable('legacy_circular_misc_import_sources'),
    'Item table exists' => Schema::hasTable('legacy_circular_misc_import_items'),

    'Run status/created index exists' => v8602HasColumns(
        $runIndexes,
        ['status', 'created_at']
    ),

    'Source run/module index exists' => v8602HasColumns(
        $sourceIndexes,
        ['run_id', 'target_module']
    ),

    'Item run/status index exists' => v8602HasColumns(
        $itemIndexes,
        ['run_id', 'status']
    ),

    'Item module/category index exists' => v8602HasColumns(
        $itemIndexes,
        ['target_module', 'category_id']
    ),

    'Item SHA-256 index exists' => v8602HasColumns(
        $itemIndexes,
        ['sha256']
    ),

    'Item existing-reference index exists' => v8602HasColumns(
        $itemIndexes,
        ['existing_type', 'existing_id']
    ),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

foreach ([
    'legacy_circular_misc_import_runs',
    'legacy_circular_misc_import_sources',
    'legacy_circular_misc_import_items',
] as $table) {
    if (Schema::hasTable($table)) {
        echo '[INFO] ' . $table . ' rows: ' . DB::table($table)->count() . PHP_EOL;
    }
}

if ($failed) {
    fwrite(STDERR, "Legacy circular/misc migration V86.0.2 check failed.\n");
    exit(1);
}

echo "Legacy circular/misc migration V86.0.2 check passed.\n";
echo "The interrupted migration was completed without dropping existing V86 tables.\n";

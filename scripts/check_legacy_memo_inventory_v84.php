<?php

declare(strict_types=1);

use App\Models\MemoCounter;
use App\Services\MemoNumberGenerator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$files = [
    'app/Http/Controllers/LegacyMemoImportController.php',
    'app/Models/LegacyMemoImportRun.php',
    'app/Models/LegacyMemoImportItem.php',
    'app/Services/LegacyMemoInventoryService.php',
    'resources/views/legacy_memo_import/index.blade.php',
    'resources/views/legacy_memo_import/show.blade.php',
];

$checks = [];

foreach ($files as $file) {
    $checks["File exists: {$file}"] = is_file(base_path($file));
}

$generator = file_get_contents(
    base_path('app/Services/MemoNumberGenerator.php')
);
$memoIndex = file_get_contents(
    resource_path('views/memos/index.blade.php')
);
$permissions = file_get_contents(
    base_path('app/Http/Middleware/ApplyRoutePermissions.php')
);
$service = file_get_contents(
    base_path('app/Services/LegacyMemoInventoryService.php')
);

$checks['Memo numbering starts at 2600000']
    = MemoNumberGenerator::DEFAULT_START_NUMBER === 2600000
    && str_contains($generator, 'DEFAULT_START_NUMBER = 2600000');
$checks['Batch number preview exists']
    = str_contains($generator, 'public static function previewBatch');
$checks['Memos page shows import button']
    = str_contains($memoIndex, "route('memo-legacy-import.index')");
$checks['Import routes are permission-protected']
    = str_contains(
        $permissions,
        "'memo-legacy-import.*' => 'legacy_import.manage'"
    );
$checks['Scanner never copies source files']
    = str_contains($service, "'files_copied' => false")
    && ! str_contains($service, 'copy(')
    && ! str_contains($service, 'unlink(');
$checks['SHA-256 duplicate detection exists']
    = str_contains($service, "hash_file('sha256'")
    && str_contains($service, 'existingAttachmentByHash');

$checks['Run table exists']
    = Schema::hasTable('legacy_memo_import_runs');
$checks['Item table exists']
    = Schema::hasTable('legacy_memo_import_items');
$checks['Memo attachment SHA-256 column exists']
    = Schema::hasColumn('memo_attachments', 'sha256');
$checks['Memo attachment source path column exists']
    = Schema::hasColumn('memo_attachments', 'source_path');

$checks['Index route exists']
    = Route::has('memo-legacy-import.index');
$checks['Scan route exists']
    = Route::has('memo-legacy-import.scan');
$checks['Report route exists']
    = Route::has('memo-legacy-import.show');

$counter = MemoCounter::query()
    ->where('counter_key', 'default')
    ->first();

$checks['Database memo counter starts at 2600000']
    = $counter && (int) $counter->start_number === 2600000;

$preview = MemoNumberGenerator::preview();
$checks['Next memo preview is numeric and safe']
    = ctype_digit((string) ($preview['memo_number'] ?? ''))
    && (int) $preview['memo_number'] >= 2600000;

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

echo 'Next memo preview: '
    . ($preview['memo_number'] ?? '-')
    . PHP_EOL;

if ($failed) {
    fwrite(STDERR, "Legacy memo inventory V84 check failed.\n");
    exit(1);
}

echo "Legacy memo inventory V84 check passed.\n";

<?php

declare(strict_types=1);

use App\Services\AttachmentIndexInventoryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$checks = [];

$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks[] = [$condition, $message];
    echo ($condition ? '[ OK ] ' : '[FAIL] ') . $message . PHP_EOL;
};

$root = dirname(__DIR__);
$requiredFiles = [
    'app/Support/AttachmentIndexInventoryItem.php',
    'app/Services/AttachmentIndexInventoryService.php',
    'app/Services/PdfTextIndexingService.php',
    'app/Http/Controllers/PdfSearchController.php',
    'resources/views/pdf-search/index.blade.php',
    'resources/views/partials/attachment-index-status.blade.php',
    'resources/views/dashboard/index.blade.php',
    'public/css/pdf-search.css',
    'routes/web.php',
];

foreach ($requiredFiles as $relativePath) {
    $check(is_file($root . '/' . $relativePath), 'Required file exists: ' . $relativePath);
}

$serviceContent = (string) file_get_contents($root . '/app/Services/AttachmentIndexInventoryService.php');
$controllerContent = (string) file_get_contents($root . '/app/Http/Controllers/PdfSearchController.php');
$viewContent = (string) file_get_contents($root . '/resources/views/pdf-search/index.blade.php');
$dashboardContent = (string) file_get_contents($root . '/resources/views/dashboard/index.blade.php');
$partialContent = (string) file_get_contents($root . '/resources/views/partials/attachment-index-status.blade.php');

$check(str_contains($serviceContent, 'fromSub($union'), 'Inventory starts from actual attachment tables');
$check(str_contains($serviceContent, "WHEN ti.id IS NULL THEN 'unindexed'"), 'Never-indexed attachments receive an unindexed status');
$check(str_contains($serviceContent, "'document' => ["), 'Document attachments are included');
$check(str_contains($serviceContent, "'memo' => ["), 'Memo attachments are included');
$check(str_contains($serviceContent, "'circular' => ["), 'Circular attachments are included');
$check(str_contains($serviceContent, "'misc_book' => ["), 'Miscellaneous book attachments are included');
$check(str_contains($controllerContent, 'indexAttachment('), 'Single-attachment indexing action exists');
$check(str_contains($controllerContent, 'indexRecord('), 'Record attachment indexing action exists');
$check(str_contains($viewContent, "'unindexed' => 'غير مفهرس'"), 'Unindexed filter is available');
$check(str_contains($viewContent, "route('pdf-search.index-attachment'"), 'Per-attachment indexing button exists');
$check(str_contains($partialContent, "route('pdf-search.index-record'"), 'Record list indexing button exists');
$check(str_contains($dashboardContent, 'unindexed_attachments_total'), 'Dashboard unindexed statistic exists');
$check(str_contains($dashboardContent, 'indexing_coverage_percent'), 'Dashboard coverage statistic exists');

$check(Route::has('pdf-search.index-attachment'), 'Route exists: pdf-search.index-attachment');
$check(Route::has('pdf-search.index-record'), 'Route exists: pdf-search.index-record');
$check(Schema::hasTable('attachment_text_indexes'), 'Attachment text index table exists');

try {
    /** @var AttachmentIndexInventoryService $inventory */
    $inventory = app(AttachmentIndexInventoryService::class);
    $stats = $inventory->statistics(['document', 'memo', 'circular', 'misc_book']);

    $check(isset($stats['total'], $stats['eligible'], $stats['indexed'], $stats['unindexed']), 'Unified statistics execute successfully');
    $check((int) $stats['total'] >= (int) $stats['eligible'], 'Total attachments include all eligible attachments');
    $check((int) $stats['eligible'] >= (int) $stats['indexed'], 'Indexed count does not exceed eligible count');
    $check((int) $stats['eligible'] >= (int) $stats['unindexed'], 'Unindexed count does not exceed eligible count');

    echo PHP_EOL;
    echo 'Unified attachment inventory:' . PHP_EOL;
    echo '  Total attachments: ' . $stats['total'] . PHP_EOL;
    echo '  Eligible PDF files: ' . $stats['eligible'] . PHP_EOL;
    echo '  Indexed: ' . $stats['indexed'] . PHP_EOL;
    echo '  Never indexed: ' . $stats['unindexed'] . PHP_EOL;
    echo '  Needs OCR: ' . $stats['needs_ocr'] . PHP_EOL;
    echo '  Failed: ' . $stats['failed'] . PHP_EOL;
    echo '  Missing: ' . $stats['missing'] . PHP_EOL;
    echo '  Unsupported: ' . $stats['unsupported'] . PHP_EOL;
    echo '  Coverage: ' . $stats['coverage_percent'] . '%' . PHP_EOL;
} catch (Throwable $e) {
    $check(false, 'Unified inventory query executes: ' . $e->getMessage());
}

$failed = array_filter($checks, static fn (array $result): bool => $result[0] === false);

echo PHP_EOL;

if ($failed !== []) {
    echo 'DocumentArchive Unified Attachment Indexing V95 verification FAILED.' . PHP_EOL;
    exit(1);
}

echo 'DocumentArchive Unified Attachment Indexing V95 verification PASSED.' . PHP_EOL;

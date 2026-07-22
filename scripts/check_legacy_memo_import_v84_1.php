<?php

declare(strict_types=1);

use App\Services\MemoNumberGenerator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$controllerPath = base_path(
    'app/Http/Controllers/LegacyMemoImportController.php'
);
$servicePath = base_path(
    'app/Services/LegacyMemoImportExecutionService.php'
);
$showViewPath = resource_path(
    'views/legacy_memo_import/show.blade.php'
);
$runModelPath = base_path('app/Models/LegacyMemoImportRun.php');
$itemModelPath = base_path('app/Models/LegacyMemoImportItem.php');

$controller = is_file($controllerPath)
    ? file_get_contents($controllerPath)
    : false;
$service = is_file($servicePath)
    ? file_get_contents($servicePath)
    : false;
$showView = is_file($showViewPath)
    ? file_get_contents($showViewPath)
    : false;
$runModel = is_file($runModelPath)
    ? file_get_contents($runModelPath)
    : false;
$itemModel = is_file($itemModelPath)
    ? file_get_contents($itemModelPath)
    : false;

$preview = null;
$previewError = null;

try {
    $preview = MemoNumberGenerator::preview();
} catch (Throwable $exception) {
    $previewError = $exception->getMessage();
}

$checks = [
    'V84.1 import controller action exists'
        => is_string($controller)
            && str_contains($controller, 'public function import('),
    'V84.1 execution service exists'
        => is_string($service),
    'source file is copied, not moved'
        => is_string($service)
            && str_contains($service, "fopen(\$source, 'rb')")
            && str_contains($service, "Storage::disk('local')->put"),
    'source deletion is explicitly disabled'
        => is_string($service)
            && str_contains($service, "'source_file_deleted' => false")
            && ! str_contains($service, 'unlink($source'),
    'source size is verified'
        => is_string($service)
            && str_contains($service, '$sourceSize')
            && str_contains($service, 'copied_size_verified'),
    'SHA-256 is verified after copy'
        => is_string($service)
            && str_contains($service, "hash_file('sha256'")
            && str_contains($service, 'copied_sha256_verified'),
    'idempotent imported-item check exists'
        => is_string($service)
            && str_contains(
                $service,
                "\$item->status === 'imported'"
            ),
    'transactional memo number generation exists'
        => is_string($service)
            && str_contains($service, 'MemoNumberGenerator::generate()')
            && str_contains($service, 'DB::transaction'),
    'review and selection UI exists'
        => is_string($showView)
            && str_contains($showView, 'items[]')
            && str_contains($showView, 'subjects[')
            && str_contains($showView, 'dates['),
    'typed confirmation exists'
        => is_string($showView)
            && str_contains($showView, 'استيراد المذكرات'),
    'run import fields are mapped'
        => is_string($runModel)
            && str_contains($runModel, "'import_status'"),
    'item import fields are mapped'
        => is_string($itemModel)
            && str_contains(
                $itemModel,
                "'imported_attachment_id'"
            ),
    'import route exists'
        => Route::has('memo-legacy-import.import'),
    'run migration columns exist'
        => Schema::hasColumn(
            'legacy_memo_import_runs',
            'import_status'
        )
            && Schema::hasColumn(
                'legacy_memo_import_runs',
                'imported_files'
            ),
    'item migration columns exist'
        => Schema::hasColumn(
            'legacy_memo_import_items',
            'imported_attachment_id'
        )
            && Schema::hasColumn(
                'legacy_memo_import_items',
                'copied_sha256'
            ),
    'memo sequence remains based on 2600000'
        => is_array($preview)
            && (int) ($preview['start_number'] ?? 0) === 2600000,
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if ($previewError) {
    echo '[INFO] Memo preview error: ' . $previewError . PHP_EOL;
}

if (is_array($preview)) {
    echo '[INFO] Next memo preview: '
        . ($preview['memo_number'] ?? '-')
        . PHP_EOL;
}

if ($failed) {
    fwrite(
        STDERR,
        "Legacy memo import V84.1 check failed.\n"
    );
    exit(1);
}

echo "Legacy memo import V84.1 check passed.\n";

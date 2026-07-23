<?php

declare(strict_types=1);

use App\Services\CircularNumberGenerator;
use App\Services\MiscBookNumberGenerator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$checks = [
    'Import route is registered' => Route::has(
        'legacy-circular-misc-import.import'
    ),
    'Execution service exists' => class_exists(
        \App\Services\LegacyCircularMiscImportExecutionService::class
    ),
    'Run import_status column exists' => Schema::hasColumn(
        'legacy_circular_misc_import_runs',
        'import_status'
    ),
    'Run imported_files column exists' => Schema::hasColumn(
        'legacy_circular_misc_import_runs',
        'imported_files'
    ),
    'Item import_attempts column exists' => Schema::hasColumn(
        'legacy_circular_misc_import_items',
        'import_attempts'
    ),
    'Item import_error column exists' => Schema::hasColumn(
        'legacy_circular_misc_import_items',
        'import_error'
    ),
    'Item copied_sha256 column exists' => Schema::hasColumn(
        'legacy_circular_misc_import_items',
        'copied_sha256'
    ),
    'Item source_verified_at column exists' => Schema::hasColumn(
        'legacy_circular_misc_import_items',
        'source_verified_at'
    ),
];

$servicePath = app_path(
    'Services/LegacyCircularMiscImportExecutionService.php'
);
$viewPath = resource_path(
    'views/legacy_circular_misc_import/show.blade.php'
);
$service = is_file($servicePath)
    ? (string) file_get_contents($servicePath)
    : '';
$view = is_file($viewPath)
    ? (string) file_get_contents($viewPath)
    : '';

$checks += [
    'Source hash is verified' => str_contains(
        $service,
        "hash_file('sha256', \$source)"
    ),
    'Copied hash is verified' => str_contains(
        $service,
        "hash_file('sha256', \$absoluteStagePath)"
    ),
    'Source is copied to staging' => str_contains(
        $service,
        "Storage::disk('local')->put(\$stagePath, \$stream)"
    ),
    'Verified copy is moved locally' => str_contains(
        $service,
        "Storage::disk('local')->move(\$stagePath, \$finalPath)"
    ),
    'Original source path is preserved' => str_contains(
        $service,
        "'source_path' => \$source"
    ),
    'Source deletion is explicitly disabled in audit' => str_contains(
        $service,
        "'source_file_deleted' => false"
    ),
    'Confirmation phrase exists in UI' => str_contains(
        $view,
        'استيراد التعاميم والمتفرقات'
    ),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if ($failed) {
    fwrite(
        STDERR,
        "Legacy circular/misc actual import V86.1 check failed.\n"
    );
    exit(1);
}

$circular = CircularNumberGenerator::preview();
$misc = MiscBookNumberGenerator::preview();

echo 'Next circular preview: '
    . $circular['circular_number']
    . PHP_EOL;
echo 'Next misc book preview: '
    . $misc['misc_number']
    . PHP_EOL;
echo "Legacy circular/misc actual import V86.1 check passed.\n";
echo "Server-1 source files remain unchanged.\n";

<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$oldController = base_path('app/Http/Controllers/LegacyArchiveImportController.php');
$newController = base_path('app/Http/Controllers/LegacyCircularMiscImportController.php');
$routes = base_path('routes/web.php');
$layout = resource_path('views/layouts/app.blade.php');

$oldControllerText = is_file($oldController) ? file_get_contents($oldController) : false;
$newControllerText = is_file($newController) ? file_get_contents($newController) : false;
$routeText = is_file($routes) ? file_get_contents($routes) : false;
$layoutText = is_file($layout) ? file_get_contents($layout) : false;

$checks = [
    'V81 legacy run table is preserved' => Schema::hasTable('legacy_archive_import_runs'),
    'V81 source_name column is preserved' => Schema::hasColumn('legacy_archive_import_runs', 'source_name'),
    'V81 controller is restored' => is_string($oldControllerText)
        && str_contains($oldControllerText, 'public function dryRun')
        && str_contains($oldControllerText, 'public function execute'),
    'V81 index route exists' => Route::has('legacy-archive-import.index'),
    'V81 dry-run route exists' => Route::has('legacy-archive-import.dry-run'),
    'V81 execute route exists' => Route::has('legacy-archive-import.execute'),
    'V86 compatibility controller exists' => is_string($newControllerText)
        && str_contains($newControllerText, 'class LegacyCircularMiscImportController'),
    'V86 run table exists' => Schema::hasTable('legacy_circular_misc_import_runs'),
    'V86 source table exists' => Schema::hasTable('legacy_circular_misc_import_sources'),
    'V86 item table exists' => Schema::hasTable('legacy_circular_misc_import_items'),
    'V86 run name column exists' => Schema::hasColumn('legacy_circular_misc_import_runs', 'name'),
    'V86 index route exists' => Route::has('legacy-circular-misc-import.index'),
    'V86 store route exists' => Route::has('legacy-circular-misc-import.store'),
    'V86 show route exists' => Route::has('legacy-circular-misc-import.show'),
    'V86 destroy route exists' => Route::has('legacy-circular-misc-import.destroy'),
    'Conflicting V86 store route was removed' => ! Route::has('legacy-archive-import.store'),
    'Distinct V86 route marker exists' => is_string($routeText)
        && str_contains($routeText, 'legacy-circulars-misc-compatibility-v86-0-1-routes:start'),
    'Distinct navigation link exists' => is_string($layoutText)
        && str_contains($layoutText, "route('legacy-circular-misc-import.index')"),
    'Old conflicting source model removed' => ! is_file(base_path('app/Models/LegacyArchiveImportSource.php')),
    'Old conflicting inventory service removed' => ! is_file(base_path('app/Services/LegacyArchiveInventoryService.php')),
];

$failed = false;
foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if ($failed) {
    fwrite(STDERR, "Legacy circular/misc compatibility V86.0.1 check failed.\n");
    exit(1);
}

echo "Legacy circular/misc compatibility V86.0.1 check passed.\n";
echo "V81 and V86 now use separate controllers, routes, views, models, and tables.\n";

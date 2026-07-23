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

$files = [
    base_path('app/Http/Controllers/CircularController.php'),
    base_path('app/Http/Controllers/MiscBookController.php'),
    base_path('app/Http/Controllers/ArchiveCategoryController.php'),
    base_path('app/Models/Circular.php'),
    base_path('app/Models/MiscBook.php'),
    base_path('app/Models/ArchiveCategory.php'),
    resource_path('views/circulars/index.blade.php'),
    resource_path('views/misc-books/index.blade.php'),
    resource_path('views/archive-categories/index.blade.php'),
    public_path('css/circulars-misc-books-v85.css'),
];

$routesPath = base_path('routes/web.php');
$middlewarePath = base_path(
    'app/Http/Middleware/ApplyRoutePermissions.php'
);
$permissionsPath = base_path('app/Support/PermissionRegistry.php');
$layoutPath = resource_path('views/layouts/app.blade.php');

$routesText = is_file($routesPath)
    ? file_get_contents($routesPath)
    : false;
$middlewareText = is_file($middlewarePath)
    ? file_get_contents($middlewarePath)
    : false;
$permissionsText = is_file($permissionsPath)
    ? file_get_contents($permissionsPath)
    : false;
$layoutText = is_file($layoutPath)
    ? file_get_contents($layoutPath)
    : false;

$circularPreview = null;
$miscPreview = null;
$previewError = null;

try {
    if (Schema::hasTable('circular_counters')
        && Schema::hasTable('circulars')) {
        $circularPreview = CircularNumberGenerator::preview();
    }

    if (Schema::hasTable('misc_book_counters')
        && Schema::hasTable('misc_books')) {
        $miscPreview = MiscBookNumberGenerator::preview();
    }
} catch (Throwable $exception) {
    $previewError = $exception->getMessage();
}

$checks = [
    'All V85 source files exist' => count(
        array_filter($files, 'is_file')
    ) === count($files),

    'archive_categories table exists' =>
        Schema::hasTable('archive_categories'),
    'circulars table exists' => Schema::hasTable('circulars'),
    'circular_attachments table exists' =>
        Schema::hasTable('circular_attachments'),
    'circular_counters table exists' =>
        Schema::hasTable('circular_counters'),
    'misc_books table exists' => Schema::hasTable('misc_books'),
    'misc_book_attachments table exists' =>
        Schema::hasTable('misc_book_attachments'),
    'misc_book_counters table exists' =>
        Schema::hasTable('misc_book_counters'),

    'Circular number column exists' =>
        Schema::hasColumn('circulars', 'circular_number'),
    'Misc book number column exists' =>
        Schema::hasColumn('misc_books', 'misc_number'),
    'Hierarchical category parent exists' =>
        Schema::hasColumn('archive_categories', 'parent_id'),

    'Circular index route exists' => Route::has('circulars.index'),
    'Circular create route exists' => Route::has('circulars.create'),
    'Circular trash route exists' => Route::has('circulars.trash'),
    'Circular attachment preview route exists' =>
        Route::has('circulars.attachments.preview'),

    'Misc books index route exists' => Route::has('misc-books.index'),
    'Misc books create route exists' => Route::has('misc-books.create'),
    'Misc books trash route exists' => Route::has('misc-books.trash'),
    'Misc attachment preview route exists' =>
        Route::has('misc-books.attachments.preview'),

    'Category management route exists' =>
        Route::has('archive-categories.index'),

    'Route marker exists' => is_string($routesText)
        && str_contains(
            $routesText,
            'circulars-misc-books-v85-routes:start'
        ),

    'Circular permission protection exists' =>
        is_string($middlewareText)
        && str_contains(
            $middlewareText,
            "'circulars.create' => 'circulars.create'"
        ),

    'Misc permission protection exists' =>
        is_string($middlewareText)
        && str_contains(
            $middlewareText,
            "'misc-books.create' => 'misc_books.create'"
        ),

    'Permission registry contains modules' =>
        is_string($permissionsText)
        && str_contains($permissionsText, "'circulars.view'")
        && str_contains($permissionsText, "'misc_books.view'"),

    'Sidebar contains module links' =>
        is_string($layoutText)
        && str_contains(
            $layoutText,
            'circulars-misc-books-v85-nav:start'
        ),

    'Default circular categories seeded' =>
        Schema::hasTable('archive_categories')
        && \App\Models\ArchiveCategory::query()
            ->where('module', 'circular')
            ->count() >= 6,

    'Default misc categories seeded' =>
        Schema::hasTable('archive_categories')
        && \App\Models\ArchiveCategory::query()
            ->where('module', 'misc_book')
            ->count() >= 5,

    'Circular preview uses 261xxxx for 2026' =>
        ! is_array($circularPreview)
        || str_starts_with(
            (string) ($circularPreview['circular_number'] ?? ''),
            '261'
        ),

    'Misc preview uses 262xxxx for 2026' =>
        ! is_array($miscPreview)
        || str_starts_with(
            (string) ($miscPreview['misc_number'] ?? ''),
            '262'
        ),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if ($circularPreview) {
    echo '[INFO] Next circular preview: '
        . $circularPreview['circular_number']
        . PHP_EOL;
}

if ($miscPreview) {
    echo '[INFO] Next misc book preview: '
        . $miscPreview['misc_number']
        . PHP_EOL;
}

if ($previewError) {
    echo '[INFO] Number preview error: ' . $previewError . PHP_EOL;
}

if ($failed) {
    fwrite(
        STDERR,
        "Circulars and miscellaneous books V85 check failed.\n"
    );
    exit(1);
}

echo "Circulars and miscellaneous books V85 check passed.\n";

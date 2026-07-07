<?php

$root = dirname(__DIR__);

$requiredFiles = [
    'app/Http/Controllers/PdfSearchController.php',
    'app/Models/AttachmentTextIndex.php',
    'app/Services/PdfTextExtractionService.php',
    'app/Services/PdfTextIndexingService.php',
    'database/migrations/2026_07_07_110000_create_attachment_text_indexes_table.php',
    'resources/views/pdf-search/index.blade.php',
    'public/css/pdf-search.css',
];

$errors = [];
$warnings = [];

foreach ($requiredFiles as $file) {
    if (! is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $errors[] = "Missing file: {$file}";
    }
}

require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (['pdf-search.index', 'pdf-search.run', 'pdf-search.reindex'] as $routeName) {
    if (! Illuminate\Support\Facades\Route::has($routeName)) {
        $errors[] = "Missing route: {$routeName}";
    }
}

$permissions = App\Support\PermissionRegistry::keys();
foreach (['pdf_search.view', 'pdf_search.index'] as $permission) {
    if (! in_array($permission, $permissions, true)) {
        $errors[] = "Missing permission: {$permission}";
    }
}

$commands = Illuminate\Support\Facades\Artisan::all();
if (! array_key_exists('archive:index-pdfs', $commands)) {
    $errors[] = 'Missing artisan command: archive:index-pdfs';
}

try {
    if (! Illuminate\Support\Facades\Schema::hasTable('attachment_text_indexes')) {
        $warnings[] = 'Table attachment_text_indexes is not created yet. Run: php artisan migrate';
    } else {
        foreach (['source_type', 'source_id', 'attachment_id', 'index_status', 'indexed_text', 'last_indexed_at'] as $column) {
            if (! Illuminate\Support\Facades\Schema::hasColumn('attachment_text_indexes', $column)) {
                $errors[] = "Missing column attachment_text_indexes.{$column}";
            }
        }
    }
} catch (Throwable $e) {
    $warnings[] = 'Could not inspect database: ' . $e->getMessage();
}

if ($errors) {
    echo "PDF/OCR search update check FAILED\n";
    foreach ($errors as $error) {
        echo "[ERROR] {$error}\n";
    }
    foreach ($warnings as $warning) {
        echo "[WARN] {$warning}\n";
    }
    exit(1);
}

echo "PDF/OCR search update check OK\n";
foreach ($warnings as $warning) {
    echo "[WARN] {$warning}\n";
}
echo "Routes: pdf-search.index, pdf-search.run, pdf-search.reindex\n";
echo "Command: php artisan archive:index-pdfs --limit=25\n";
echo "Page: /pdf-search\n";

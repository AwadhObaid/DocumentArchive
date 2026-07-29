<?php

declare(strict_types=1);

$projectRoot = $argv[1] ?? dirname(__DIR__);
$projectRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $projectRoot), DIRECTORY_SEPARATOR);

$failures = 0;
$warnings = 0;

function result(bool $condition, string $message): void
{
    global $failures;

    echo $condition ? "[ OK ] {$message}\n" : "[FAIL] {$message}\n";

    if (! $condition) {
        $failures++;
    }
}

function warning(string $message): void
{
    global $warnings;

    $warnings++;
    echo "[WARN] {$message}\n";
}

function projectPath(string $relativePath): string
{
    global $projectRoot;

    return $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
}

function contents(string $relativePath): string
{
    $path = projectPath($relativePath);

    return is_file($path) ? (string) file_get_contents($path) : '';
}

function phpSyntax(string $relativePath): void
{
    $path = projectPath($relativePath);

    if (! is_file($path)) {
        result(false, "PHP file exists: {$relativePath}");

        return;
    }

    $output = [];
    $exitCode = 1;
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1';
    exec($command, $output, $exitCode);

    result($exitCode === 0, "PHP syntax: {$relativePath}");
}

$files = [
    'app/Http/Controllers/PDFSearchController.php',
    'app/Models/AttachmentTextIndex.php',
    'app/Models/CircularAttachment.php',
    'app/Models/MiscBookAttachment.php',
    'app/Services/PdfTextIndexingService.php',
    'resources/views/pdf-search/index.blade.php',
    'routes/console.php',
    'scripts/check_pdf_ocr_sources_v92.php',
];

echo "DocumentArchive PDF/OCR Circulars and Misc Books V92 verification\n";
echo "================================================================\n";

foreach ($files as $file) {
    result(is_file(projectPath($file)), "File exists: {$file}");
}

$controller = contents('app/Http/Controllers/PDFSearchController.php');
$service = contents('app/Services/PdfTextIndexingService.php');
$indexModel = contents('app/Models/AttachmentTextIndex.php');
$circularAttachment = contents('app/Models/CircularAttachment.php');
$miscAttachment = contents('app/Models/MiscBookAttachment.php');
$view = contents('resources/views/pdf-search/index.blade.php');
$console = contents('routes/console.php');

result(str_contains($controller, "'circulars' => ["), 'Circulars source is registered in PDF search');
result(str_contains($controller, "'misc_books' => ["), 'Misc books source is registered in PDF search');
result(str_contains($controller, "'type' => 'circular'"), 'Circular source type is mapped');
result(str_contains($controller, "'type' => 'misc_book'"), 'Misc book source type is mapped');
result(str_contains($controller, "'permission' => 'circulars.view'"), 'Circular permissions are respected');
result(str_contains($controller, "'permission' => 'misc_books.view'"), 'Misc book permissions are respected');
result(str_contains($controller, "'circular.category'"), 'Circular records are eager loaded');
result(str_contains($controller, "'miscBook.category'"), 'Misc book records are eager loaded');
result(str_contains($controller, "where('source_type', 'circular')"), 'Circular metadata search is supported');
result(str_contains($controller, "where('source_type', 'misc_book')"), 'Misc book metadata search is supported');
result(str_contains($controller, "indexCircularAttachment"), 'Circular reindexing is supported');
result(str_contains($controller, "indexMiscBookAttachment"), 'Misc book reindexing is supported');
result(str_contains($controller, 'allowedSourceKeys'), 'Batch indexing is restricted to permitted sources');

result(str_contains($service, 'use App\\Models\\CircularAttachment;'), 'Circular attachment model is imported');
result(str_contains($service, 'use App\\Models\\MiscBookAttachment;'), 'Misc book attachment model is imported');
result(str_contains($service, "'circulars',"), 'Circulars are included in batch sources');
result(str_contains($service, "'misc_books',"), 'Misc books are included in batch sources');
result(str_contains($service, 'public function indexCircularAttachment('), 'Circular attachment indexing method exists');
result(str_contains($service, 'public function indexMiscBookAttachment('), 'Misc book attachment indexing method exists');
result(str_contains($service, 'private function circularAttachmentsQuery('), 'Circular PDF query exists');
result(str_contains($service, 'private function miscBookAttachmentsQuery('), 'Misc book PDF query exists');
result(str_contains($service, "'circular'"), 'Circular index rows use a distinct source type');
result(str_contains($service, "'misc_book'"), 'Misc book index rows use a distinct source type');

result(str_contains($indexModel, 'public function circular()'), 'Text index has circular relation');
result(str_contains($indexModel, 'public function miscBook()'), 'Text index has misc book relation');
result(str_contains($indexModel, 'public function circularAttachment()'), 'Text index has circular attachment relation');
result(str_contains($indexModel, 'public function miscBookAttachment()'), 'Text index has misc attachment relation');
result(str_contains($indexModel, "'circular' => 'تعميم'"), 'Circular result label is Arabic');
result(str_contains($indexModel, "'misc_book' => 'كتاب متفرق'"), 'Misc book result label is Arabic');
result(str_contains($indexModel, "route('circulars.show'"), 'Circular result opens its record');
result(str_contains($indexModel, "route('misc-books.show'"), 'Misc book result opens its record');

result(str_contains($circularAttachment, "where('source_type', 'circular')"), 'Circular attachments expose their text index relation');
result(str_contains($miscAttachment, "where('source_type', 'misc_book')"), 'Misc attachments expose their text index relation');

result(str_contains($view, "'circulars' => 'التعاميم فقط'"), 'Circulars option is shown in the source filter');
result(str_contains($view, "'misc_books' => 'الكتب المتفرقة فقط'"), 'Misc books option is shown in the source filter');
result(str_contains($view, 'جميع المصادر المتاحة'), 'All available sources option is shown');
result(str_contains($view, 'التعاميم والكتب المتفرقة'), 'Page description mentions the new sources');
result(substr_count($view, '@foreach($sourceOptions as $value => $label)') >= 2, 'Search and indexing forms share the permitted source options');

result(str_contains($console, 'all|documents|memos|circulars|misc_books'), 'Artisan command accepts all four sources');
result(str_contains($console, "'circulars', 'misc_books'"), 'Artisan command validates the new source names');
result(str_contains($console, 'التعاميم والكتب المتفرقة'), 'Artisan command description mentions the new sources');

foreach ([
    'app/Http/Controllers/PDFSearchController.php',
    'app/Models/AttachmentTextIndex.php',
    'app/Models/CircularAttachment.php',
    'app/Models/MiscBookAttachment.php',
    'app/Services/PdfTextIndexingService.php',
    'routes/console.php',
    'scripts/check_pdf_ocr_sources_v92.php',
] as $phpFile) {
    phpSyntax($phpFile);
}

$autoload = projectPath('vendor/autoload.php');
$bootstrap = projectPath('bootstrap/app.php');

if (is_file($autoload) && is_file($bootstrap)) {
    try {
        require_once $autoload;

        $app = require $bootstrap;
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $requiredTables = [
            'attachment_text_indexes',
            'document_attachments',
            'memo_attachments',
            'circular_attachments',
            'misc_book_attachments',
        ];

        foreach ($requiredTables as $table) {
            result(\Illuminate\Support\Facades\Schema::hasTable($table), "Database table exists: {$table}");
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('attachment_text_indexes')) {
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing('attachment_text_indexes');

            foreach (['source_type', 'source_id', 'attachment_id', 'indexed_text', 'index_status'] as $column) {
                result(in_array($column, $columns, true), "Text index column exists: {$column}");
            }
        }

        foreach (['circular_attachments', 'misc_book_attachments'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $count = \Illuminate\Support\Facades\DB::table($table)
                ->where(function ($query) {
                    $query->whereRaw('LOWER(COALESCE(extension, "")) = ?', ['pdf'])
                        ->orWhere('mime_type', 'like', '%pdf%');
                })
                ->count();

            echo "[INFO] PDF candidates in {$table}: {$count}\n";
        }
    } catch (Throwable $exception) {
        warning('Database/runtime inspection skipped: ' . $exception->getMessage());
    }
} else {
    warning('Laravel runtime files are unavailable; static verification only.');
}

echo "\n";

if ($failures > 0) {
    echo "PDF/OCR Circulars and Misc Books V92 verification FAILED.\n";
    echo "Failures: {$failures}\n";
    echo "Warnings: {$warnings}\n";
    exit(1);
}

echo "PDF/OCR Circulars and Misc Books V92 verification PASSED.\n";
echo "Warnings: {$warnings}\n";
exit(0);

<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$checks = [
    'app/Http/Controllers/PdfSearchController.php' => [
        'public function selection(',
        "route('pdf-search.index-attachment'",
        '$request->expectsJson()',
        '\'status_name\' => (string) $index->status_name',
    ],
    'app/Services/AttachmentIndexInventoryService.php' => [
        'public function selection(',
        "<> 'indexed'",
        '\'limited\' => $total > $limit',
        '\'returned\' => count($items)',
    ],
    'app/Http/Middleware/ApplyRoutePermissions.php' => [
        "'pdf-search.selection' => 'pdf_search.index'",
    ],
    'routes/web.php' => [
        "'/pdf-search/selection'",
        "->name('pdf-search.selection')",
    ],
    'resources/views/pdf-search/index.blade.php' => [
        'data-pdf-bulk-indexing',
        'data-pdf-select-page-checkbox',
        'data-pdf-bulk-item',
        'data-pdf-select-filtered',
        'data-pdf-run-selected',
        "pdf-search-bulk-indexing-v95-1.js",
    ],
    'public/js/pdf-search-bulk-indexing-v95-1.js' => [
        'const selected = new Map()',
        'const selectFiltered = async () =>',
        'const runSelected = async () =>',
        "'X-CSRF-TOKEN': csrfToken",
        'سيتم إيقاف الفهرسة بعد انتهاء معالجة الملف الحالي.',
    ],
    'public/css/pdf-search.css' => [
        'PDF_BULK_INDEXING_V95_1',
        '.pdf-bulk-index-panel',
        '.pdf-bulk-item-checkbox',
        '.pdf-bulk-progress-track',
    ],
];

$failed = false;

echo "DocumentArchive PDF Bulk Indexing V95.1 verification\n";
echo "===================================================\n";

foreach ($checks as $relativePath => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (! is_file($path)) {
        echo "[FAIL] Missing file: {$relativePath}\n";
        $failed = true;
        continue;
    }

    $content = file_get_contents($path);

    if ($content === false) {
        echo "[FAIL] Could not read: {$relativePath}\n";
        $failed = true;
        continue;
    }

    $filePassed = true;

    foreach ($needles as $needle) {
        if (! str_contains($content, $needle)) {
            echo "[FAIL] {$relativePath}: missing marker: {$needle}\n";
            $failed = true;
            $filePassed = false;
        }
    }

    if ($filePassed) {
        echo "[ OK ] {$relativePath}\n";
    }
}

if ($failed) {
    echo "\nDocumentArchive PDF Bulk Indexing V95.1 verification FAILED.\n";
    exit(1);
}

echo "\nDocumentArchive PDF Bulk Indexing V95.1 verification PASSED.\n";
exit(0);

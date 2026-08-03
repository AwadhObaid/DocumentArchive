<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$view = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views'
    . DIRECTORY_SEPARATOR . 'pdf-search' . DIRECTORY_SEPARATOR . 'index.blade.php';

$errors = [];

if (! is_file($view)) {
    $errors[] = 'Missing resources/views/pdf-search/index.blade.php';
} else {
    $contents = file_get_contents($view);

    if ($contents === false) {
        $errors[] = 'Could not read PDF search Blade view.';
    } else {
        $required = [
            'V95.1.2: raw PHP control flow',
            'data-pdf-bulk-indexing',
            'data-pdf-select-page-checkbox',
            'data-pdf-bulk-item',
            'data-pdf-select-filtered',
            'pdf-search-bulk-indexing-v95-1.js',
            '<?php if ($indexes->count() > 0): ?>',
            '<?php else: ?>',
            '<?php endif; ?>',
        ];

        foreach ($required as $needle) {
            if (! str_contains($contents, $needle)) {
                $errors[] = 'Missing required marker: ' . $needle;
            }
        }

        $resultsOffset = strpos($contents, 'V95.1.2: raw PHP control flow');

        if ($resultsOffset !== false) {
            $resultsBlock = substr($contents, $resultsOffset);

            foreach (['@else', '@endif', '@foreach', '@endforeach'] as $unsafeDirective) {
                if (str_contains($resultsBlock, $unsafeDirective)) {
                    $errors[] = 'Unsafe Blade control directive remains in results block: ' . $unsafeDirective;
                }
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "DocumentArchive PDF Bulk Indexing V95.1.2 verification FAILED.\n");

    foreach ($errors as $error) {
        fwrite(STDERR, ' - ' . $error . PHP_EOL);
    }

    exit(1);
}

echo "DocumentArchive PDF Bulk Indexing V95.1.2 verification PASSED.\n";

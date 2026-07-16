<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sourceRoot = $root . DIRECTORY_SEPARATOR . 'updates' . DIRECTORY_SEPARATOR . 'smart_reports_final_polish_v69';

if (! is_dir($sourceRoot)) {
    fwrite(STDERR, "[FAIL] Update source folder not found: {$sourceRoot}\n");
    exit(1);
}

$files = [
    'app/Http/Controllers/SmartReportController.php',
    'app/Services/GeminiSmartReportService.php',
    'app/Support/SmartReportTextFormatter.php',
    'resources/views/smart_reports/index.blade.php',
    'resources/views/smart_reports/pdf.blade.php',
    'resources/views/smart_reports/word.blade.php',
    'resources/views/smart_reports/partials/chart-summary.blade.php',
    'public/css/smart-reports-v69-final.css',
    'public/js/smart-reports-v69-final.js',
];

foreach ($files as $relative) {
    $from = $sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $to = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (! is_file($from)) {
        fwrite(STDERR, "[FAIL] Missing update file: {$relative}\n");
        exit(1);
    }

    $dir = dirname($to);
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    copy($from, $to);
    echo "[OK] Applied {$relative}\n";
}

function rrmdir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = array_diff(scandir($dir) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

rrmdir($sourceRoot);

$updatesRoot = dirname($sourceRoot);
if (is_dir($updatesRoot) && count(array_diff(scandir($updatesRoot) ?: [], ['.', '..'])) === 0) {
    @rmdir($updatesRoot);
}

echo "Smart reports final polish V69 applied successfully.\n";

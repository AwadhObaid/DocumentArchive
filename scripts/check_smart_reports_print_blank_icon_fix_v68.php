<?php

$root = dirname(__DIR__);
$checks = [
    'resources/views/smart_reports/index.blade.php' => [
        'smart-reports-v68-print-page-fix.css',
        'smart-reports-v68-print-page-fix.js',
        'smartPrintableReport',
    ],
    'public/css/smart-reports-v68-print-page-fix.css' => [
        'body.smart-report-printing > *:not(#smartReportPrintClone)',
        '#smartReportPrintClone',
        'smart-card-title',
    ],
    'public/js/smart-reports-v68-print-page-fix.js' => [
        'smartReportPrintClone',
        'smart-report-printing',
        'stopImmediatePropagation',
        'beforeprint',
        'afterprint',
    ],
];

$failed = false;
foreach ($checks as $relative => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        echo "[FAIL] Missing file: {$relative}\n";
        $failed = true;
        continue;
    }
    echo "[OK] File exists: {$relative}\n";

    if (str_ends_with($relative, '.php')) {
        $cmd = 'php -l ' . escapeshellarg($path);
        exec($cmd, $out, $code);
        if ($code !== 0) {
            echo "[FAIL] PHP syntax invalid: {$relative}\n";
            $failed = true;
        } else {
            echo "[OK] PHP syntax valid: {$relative}\n";
        }
    }

    $content = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            echo "[FAIL] {$relative} missing: {$needle}\n";
            $failed = true;
        } else {
            echo "[OK] {$relative} contains: {$needle}\n";
        }
    }
}

if ($failed) {
    echo "\nSmart reports print blank/icon fix V68 check failed.\n";
    exit(1);
}

echo "\nSmart reports print blank/icon fix V68 check passed.\n";
echo "Print now uses an isolated clone of #smartPrintableReport to avoid blank first page and stray global layout icons.\n";

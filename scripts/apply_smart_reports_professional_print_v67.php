<?php

declare(strict_types=1);

$root = dirname(__DIR__);

echo PHP_EOL;
echo "Applying / verifying Smart Reports Professional Print V67..." . PHP_EOL;

$required = [
    'app/Http/Controllers/SmartReportController.php',
    'app/Support/SmartReportTextFormatter.php',
    'resources/views/smart_reports/index.blade.php',
    'resources/views/smart_reports/pdf.blade.php',
    'resources/views/smart_reports/word.blade.php',
    'resources/views/smart_reports/partials/chart-summary.blade.php',
    'public/css/smart-reports-v67-print.css',
    'scripts/check_smart_reports_professional_print_v67.php',
    'README_SMART_REPORTS_PROFESSIONAL_PRINT_V67.txt',
];

$missing = [];
foreach ($required as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (is_file($path)) {
        echo "[OK] File exists: {$relative}" . PHP_EOL;
    } else {
        $missing[] = $relative;
        echo "[FAIL] Missing: {$relative}" . PHP_EOL;
    }
}

if ($missing !== []) {
    echo PHP_EOL . "V67 files are incomplete. Extract the ZIP into the parent folder of DocumentArchive." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "V67 files are in place. Run:" . PHP_EOL;
echo "php scripts/check_smart_reports_professional_print_v67.php" . PHP_EOL;
echo PHP_EOL;

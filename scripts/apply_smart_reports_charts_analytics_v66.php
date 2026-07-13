<?php

declare(strict_types=1);

$root = dirname(__DIR__);

echo PHP_EOL;
echo "Applying / verifying Smart Reports Charts and Analytics V66..." . PHP_EOL;

$required = [
    'app/Http/Controllers/SmartReportController.php',
    'resources/views/smart_reports/index.blade.php',
    'resources/views/smart_reports/word.blade.php',
    'resources/views/smart_reports/pdf.blade.php',
    'public/css/smart-reports-v66.css',
    'public/js/smart-reports-v66.js',
    'scripts/check_smart_reports_charts_analytics_v66.php',
    'README_SMART_REPORTS_CHARTS_ANALYTICS_V66.txt',
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
    echo PHP_EOL . "V66 files are incomplete. Extract the ZIP into the parent folder of DocumentArchive." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "V66 files are in place. Run:" . PHP_EOL;
echo "php scripts/check_smart_reports_charts_analytics_v66.php" . PHP_EOL;
echo PHP_EOL;

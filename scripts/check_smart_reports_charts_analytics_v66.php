<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failed = false;

function ok_v66(string $message): void
{
    echo "[OK] {$message}" . PHP_EOL;
}

function fail_v66(string $message): void
{
    global $failed;
    $failed = true;
    echo "[FAIL] {$message}" . PHP_EOL;
}

function path_v66(string $relative): string
{
    global $root;
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function file_v66(string $relative): void
{
    is_file(path_v66($relative)) ? ok_v66("File exists: {$relative}") : fail_v66("Missing file: {$relative}");
}

function contains_v66(string $relative, string $needle): void
{
    $path = path_v66($relative);
    if (! is_file($path)) {
        fail_v66("Cannot inspect missing file: {$relative}");
        return;
    }

    $content = file_get_contents($path) ?: '';
    str_contains($content, $needle)
        ? ok_v66("{$relative} contains: {$needle}")
        : fail_v66("{$relative} missing: {$needle}");
}

function php_lint_v66(string $relative): void
{
    $path = path_v66($relative);
    if (! is_file($path)) {
        fail_v66("Cannot lint missing file: {$relative}");
        return;
    }

    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    $code === 0
        ? ok_v66("PHP syntax valid: {$relative}")
        : fail_v66("PHP syntax error in {$relative}: " . implode(' ', $output));
}

$files = [
    'app/Http/Controllers/SmartReportController.php',
    'resources/views/smart_reports/index.blade.php',
    'resources/views/smart_reports/word.blade.php',
    'resources/views/smart_reports/pdf.blade.php',
    'public/css/smart-reports-v66.css',
    'public/js/smart-reports-v66.js',
    'scripts/apply_smart_reports_charts_analytics_v66.php',
    'scripts/check_smart_reports_charts_analytics_v66.php',
    'README_SMART_REPORTS_CHARTS_ANALYTICS_V66.txt',
];

foreach ($files as $file) {
    file_v66($file);
}

php_lint_v66('app/Http/Controllers/SmartReportController.php');
php_lint_v66('scripts/apply_smart_reports_charts_analytics_v66.php');
php_lint_v66('scripts/check_smart_reports_charts_analytics_v66.php');

contains_v66('app/Http/Controllers/SmartReportController.php', 'visual_version');
contains_v66('app/Http/Controllers/SmartReportController.php', 'completion_rate');
contains_v66('app/Http/Controllers/SmartReportController.php', 'buildLocalInsights');
contains_v66('resources/views/smart_reports/index.blade.php', 'smart-reports-v66.css');
contains_v66('resources/views/smart_reports/index.blade.php', 'smartReportChartData');
contains_v66('resources/views/smart_reports/index.blade.php', 'data-smart-chart="companies"');
contains_v66('resources/views/smart_reports/index.blade.php', 'data-smart-print');
contains_v66('public/js/smart-reports-v66.js', 'drawDonut');
contains_v66('public/js/smart-reports-v66.js', 'drawHorizontalBar');
contains_v66('public/js/smart-reports-v66.js', 'drawLine');
contains_v66('public/css/smart-reports-v66.css', 'smart-kpi-grid');
contains_v66('resources/views/smart_reports/pdf.blade.php', 'ملخص الرسوم البيانية');
contains_v66('resources/views/smart_reports/word.blade.php', 'ملخص الرسوم البيانية');

if ($failed) {
    echo PHP_EOL . "Smart Reports Charts and Analytics V66 check failed." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "Smart Reports Charts and Analytics V66 check passed." . PHP_EOL;
echo "No migration required. No npm build required." . PHP_EOL;

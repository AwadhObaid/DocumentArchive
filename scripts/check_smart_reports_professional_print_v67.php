<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function check_ok(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "[OK] {$message}" . PHP_EOL;
    } else {
        echo "[FAIL] {$message}" . PHP_EOL;
        $failures[] = $message;
    }
}

function file_text(string $relative): string
{
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    return is_file($path) ? (string) file_get_contents($path) : '';
}

$files = [
    'app/Http/Controllers/SmartReportController.php',
    'app/Support/SmartReportTextFormatter.php',
    'resources/views/smart_reports/index.blade.php',
    'resources/views/smart_reports/pdf.blade.php',
    'resources/views/smart_reports/word.blade.php',
    'resources/views/smart_reports/partials/chart-summary.blade.php',
    'public/css/smart-reports-v67-print.css',
];

foreach ($files as $file) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    check_ok(is_file($path), "File exists: {$file}");
    if (is_file($path) && str_ends_with($file, '.php')) {
        $output = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
        check_ok($code === 0, "PHP syntax valid: {$file}");
    }
}

$index = file_text('resources/views/smart_reports/index.blade.php');
$pdf = file_text('resources/views/smart_reports/pdf.blade.php');
$word = file_text('resources/views/smart_reports/word.blade.php');
$css = file_text('public/css/smart-reports-v67-print.css');
$controller = file_text('app/Http/Controllers/SmartReportController.php');
$formatter = file_text('app/Support/SmartReportTextFormatter.php');

check_ok(str_contains($index, 'smart-reports-v67-print.css'), 'Index includes V67 print CSS');
check_ok(!str_contains($index, 'الإصدار المرئي: V66'), 'Visual version V66 removed from visible report metadata');
check_ok(str_contains($index, 'smart-print-header'), 'Printable report header exists');
check_ok(str_contains($index, 'SmartReportTextFormatter::toHtml'), 'Index renders Gemini output as clean HTML');
check_ok(str_contains($index, 'smart_print') || str_contains($index, 'smart-print-chart-summary'), 'Index has print chart summary');

check_ok(str_contains($pdf, 'نظام أرشفة المستندات'), 'PDF template has professional report header');
check_ok(!str_contains($pdf, 'الموديل:'), 'PDF template hides model details');
check_ok(!str_contains($pdf, 'الإصدار المرئي'), 'PDF template hides visual version');
check_ok(str_contains($pdf, 'SmartReportTextFormatter::toHtml'), 'PDF template renders clean Gemini analysis');

check_ok(!str_contains($word, 'الموديل:'), 'Word template hides model details');
check_ok(!str_contains($word, 'الإصدار المرئي'), 'Word template hides visual version');
check_ok(str_contains($word, 'SmartReportTextFormatter::toHtml'), 'Word template renders clean Gemini analysis');

check_ok(str_contains($css, '@media print'), 'V67 CSS has print media rules');
check_ok(str_contains($css, '[class*="chat"]'), 'V67 print CSS hides chat/floating widgets');
check_ok(str_contains($css, '.smart-chart-grid'), 'V67 print CSS hides interactive chart canvases');

check_ok(str_contains($controller, 'لا تستخدم Markdown'), 'Prompt instructs Gemini not to return Markdown');
check_ok(!str_contains($controller, "'visual_version' => 'v66'"), 'Controller no longer stores visual version in payload');
check_ok(str_contains($formatter, 'class SmartReportTextFormatter'), 'Formatter class exists');

if ($failures !== []) {
    echo PHP_EOL . 'Smart reports professional print V67 check failed.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Smart reports professional print V67 check passed.' . PHP_EOL;
echo 'No migration required. No npm build required.' . PHP_EOL;

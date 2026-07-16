<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function readFileRequired(string $relative): string
{
    global $root, $failures;
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (! is_file($path)) {
        $failures[] = "Missing file: {$relative}";
        return '';
    }
    return (string) file_get_contents($path);
}

function mustContain(string $haystack, string $needle, string $label): void
{
    global $failures;
    if ($haystack === '' || ! str_contains($haystack, $needle)) {
        $failures[] = "Missing: {$label}";
    }
}

function mustNotContain(string $haystack, string $needle, string $label): void
{
    global $failures;
    if ($haystack !== '' && str_contains($haystack, $needle)) {
        $failures[] = "Unexpected: {$label}";
    }
}

$index = readFileRequired('resources/views/smart_reports/index.blade.php');
$controller = readFileRequired('app/Http/Controllers/SmartReportController.php');
$service = readFileRequired('app/Services/GeminiSmartReportService.php');
$formatter = readFileRequired('app/Support/SmartReportTextFormatter.php');
$pdf = readFileRequired('resources/views/smart_reports/pdf.blade.php');
$word = readFileRequired('resources/views/smart_reports/word.blade.php');
$summary = readFileRequired('resources/views/smart_reports/partials/chart-summary.blade.php');
$css = readFileRequired('public/css/smart-reports-v69-final.css');
$js = readFileRequired('public/js/smart-reports-v69-final.js');

mustContain($index, "smart-reports-v69-final.css", 'V69 CSS include');
mustContain($index, "smart-reports-v69-final.js", 'V69 JS include');
mustContain($index, "smart-official-report-v69", 'official report class');
mustContain($index, "التحليل الذكي والتوصيات", 'final analysis heading');
mustNotContain($index, "التحليل الذكي بواسطة Gemini", 'old Gemini heading');

mustContain($controller, "SmartReportTextFormatter", 'formatter import/use in controller');
mustContain($controller, "SmartReportTextFormatter::clean", 'clean result before save');
mustContain($controller, "SetTitle", 'PDF title metadata');

mustContain($service, "https://generativelanguage.googleapis.com/v1beta/interactions", 'Gemini Interactions API endpoint');
mustContain($service, "thinking_level", 'Gemini thinking level config');
mustContain($service, "output_text", 'Gemini output_text extraction');

mustContain($formatter, "function clean", 'formatter clean method');
mustContain($formatter, "```", 'formatter strips code fences');
mustContain($formatter, "smart-report-list", 'formatter list output');

mustContain($summary, "hasPrintableChartRows", 'chart summary empty-state guard');
mustContain($css, "Smart Reports V69", 'V69 CSS marker');
mustContain($js, "prepareOfficialPrintClone", 'V69 print clone cleanup');

mustNotContain($pdf, "Gemini API", 'PDF should not expose Gemini API wording');
mustNotContain($word, "Gemini API", 'Word should not expose Gemini API wording');
mustNotContain($pdf, "V66", 'PDF should not expose visual version');
mustNotContain($word, "V66", 'Word should not expose visual version');

if ($failures !== []) {
    echo "Smart reports final polish V69 check failed:\n";
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}

echo "Smart reports final polish V69 check passed.\n";

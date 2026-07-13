<?php

$root = dirname(__DIR__);
$index = $root . DIRECTORY_SEPARATOR . 'resources/views/smart_reports/index.blade.php';
$css = $root . DIRECTORY_SEPARATOR . 'public/css/smart-reports-v68-print-page-fix.css';
$js = $root . DIRECTORY_SEPARATOR . 'public/js/smart-reports-v68-print-page-fix.js';

function fail_v68(string $message): void
{
    fwrite(STDERR, "[FAIL] {$message}\n");
    exit(1);
}

function ok_v68(string $message): void
{
    echo "[OK] {$message}\n";
}

if (!is_file($index)) {
    fail_v68('Missing resources/views/smart_reports/index.blade.php');
}
if (!is_file($css)) {
    fail_v68('Missing public/css/smart-reports-v68-print-page-fix.css');
}
if (!is_file($js)) {
    fail_v68('Missing public/js/smart-reports-v68-print-page-fix.js');
}

$content = file_get_contents($index);
if ($content === false) {
    fail_v68('Unable to read index.blade.php');
}

$cssLine = '<link rel="stylesheet" href="{{ asset(\'css/smart-reports-v68-print-page-fix.css\') }}?v=68">';
$jsLine = '<script src="{{ asset(\'js/smart-reports-v68-print-page-fix.js\') }}?v=68" defer></script>';

if (!str_contains($content, 'smart-reports-v68-print-page-fix.css')) {
    if (str_contains($content, 'smart-reports-v67-print.css')) {
        $content = preg_replace(
            '/(<link[^\n]+smart-reports-v67-print\.css[^\n]+>)/',
            "$1\n" . $cssLine,
            $content,
            1
        );
    } else {
        $content = preg_replace(
            '/(@section\(\'content\'\)\s*)/',
            "$1\n" . $cssLine . "\n",
            $content,
            1
        );
    }
    ok_v68('Added V68 print CSS include');
} else {
    ok_v68('V68 print CSS include already exists');
}

if (!str_contains($content, 'smart-reports-v68-print-page-fix.js')) {
    if (str_contains($content, 'smart-reports-v66.js')) {
        $content = preg_replace(
            '/(<script[^\n]+smart-reports-v66\.js[^\n]+><\/script>)/',
            "$1\n" . $jsLine,
            $content,
            1
        );
    } else {
        $content .= "\n" . $jsLine . "\n";
    }
    ok_v68('Added V68 print JS include');
} else {
    ok_v68('V68 print JS include already exists');
}

if (file_put_contents($index, $content) === false) {
    fail_v68('Unable to write index.blade.php');
}

ok_v68('Smart reports print blank/icon fix V68 applied successfully.');

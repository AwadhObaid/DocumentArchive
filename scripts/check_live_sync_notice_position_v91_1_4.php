<?php

declare(strict_types=1);

$projectRoot = $argv[1] ?? dirname(__DIR__);
$projectRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $projectRoot), DIRECTORY_SEPARATOR);

$cssPath = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'live-data-sync-v91.css';
$layoutPath = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';

$failures = 0;

function checkResult(bool $condition, string $message): void
{
    global $failures;

    echo $condition ? "[ OK ] {$message}\n" : "[FAIL] {$message}\n";

    if (! $condition) {
        $failures++;
    }
}

echo "DocumentArchive Live Sync Notice Position V91.1.4 verification\n";
echo "==============================================================\n";

checkResult(is_file($cssPath), 'Live sync stylesheet exists');

$css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';

checkResult(
    str_contains($css, 'V91.1.3 - notice position fix'),
    'Notice position style marker exists'
);

checkResult(
    preg_match('/\.da-live-sync-notice\s*\{[\s\S]*?bottom:\s*96px\s*;/m', $css) === 1,
    'Desktop notice is raised above floating controls'
);

checkResult(
    preg_match('/@media\s*\(max-width:\s*640px\)[\s\S]*?\.da-live-sync-notice\s*\{[\s\S]*?bottom:\s*88px\s*;/m', $css) === 1,
    'Mobile notice is raised above floating controls'
);

checkResult(
    str_contains($css, 'inset-inline-end: 22px;'),
    'Desktop horizontal alignment is preserved'
);

checkResult(
    str_contains($css, 'inset-inline: 14px;'),
    'Mobile responsive width is preserved'
);

checkResult(is_file($layoutPath), 'Application layout exists');

$layout = is_file($layoutPath) ? (string) file_get_contents($layoutPath) : '';

checkResult(
    substr_count($layout, 'live-data-sync-v91-css:start') === 1,
    'Live sync stylesheet start marker exists once'
);

checkResult(
    substr_count($layout, 'live-data-sync-v91-css:end') === 1,
    'Live sync stylesheet end marker exists once'
);

checkResult(
    preg_match(
        '/<link\s+rel="stylesheet"\s+href="\{\{\s*asset\(\'css\/live-data-sync-v91\.css\'\)\s*\}\}\?v=\{\{\s*filemtime\(public_path\(\'css\/live-data-sync-v91\.css\'\)\)\s*\}\}">/m',
        $layout
    ) === 1,
    'Live sync stylesheet link is loaded correctly'
);

echo "\n";

if ($failures > 0) {
    echo "Live Sync Notice Position V91.1.4 verification FAILED.\n";
    echo "Failures: {$failures}\n";
    exit(1);
}

echo "Live Sync Notice Position V91.1.4 verification PASSED.\n";
exit(0);

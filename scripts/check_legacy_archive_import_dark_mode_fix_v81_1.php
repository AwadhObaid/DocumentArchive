<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$cssPath = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'legacy-archive-import-v81.css';

function check_fail_v811(string $message): never
{
    fwrite(STDERR, "[FAIL] {$message}" . PHP_EOL);
    exit(1);
}

if (! is_file($cssPath)) {
    check_fail_v811('public/css/legacy-archive-import-v81.css does not exist.');
}

$css = file_get_contents($cssPath);
if ($css === false) {
    check_fail_v811('Unable to read the stylesheet.');
}

$required = [
    'Legacy Archive Importer V81.1',
    'background: var(--card, #fff);',
    'html[data-theme="dark"] .legacy-import-card',
    'background: #111827;',
    'color: #f8fafc;',
    'html[data-theme="dark"] .legacy-import-warning',
];

foreach ($required as $needle) {
    if (! str_contains($css, $needle)) {
        check_fail_v811("Missing required CSS marker: {$needle}");
    }
}

if (str_contains($css, 'background: var(--card-bg, #fff);')) {
    check_fail_v811('Old undefined --card-bg fallback is still present.');
}

if (str_contains($css, 'border: 1px solid var(--border-color')) {
    check_fail_v811('Old undefined --border-color variable is still present.');
}

echo 'Legacy archive importer dark-mode contrast V81.1 check passed.' . PHP_EOL;

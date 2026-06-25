<?php

/**
 * Installs Arabic custom file input assets into the main Laravel layout(s).
 * Run from the project root:
 * php scripts/apply_arabic_file_input.php
 */

$root = dirname(__DIR__);

$assets = [
    'public/css/arabic-file-input.css',
    'public/js/arabic-file-input.js',
];

foreach ($assets as $asset) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset);
    if (!file_exists($path)) {
        fwrite(STDERR, "Missing asset: {$asset}\n");
        exit(1);
    }
}

$cssTag = '<link rel="stylesheet" href="{{ asset(\'css/arabic-file-input.css\') }}">';
$jsTag = '<script src="{{ asset(\'js/arabic-file-input.js\') }}" defer></script>';

$layoutCandidates = [
    'resources/views/layouts/app.blade.php',
    'resources/views/layouts/admin.blade.php',
    'resources/views/layouts/auth.blade.php',
    'resources/views/app.blade.php',
];

$changed = [];
$found = [];

foreach ($layoutCandidates as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!file_exists($path)) {
        continue;
    }

    $found[] = $relative;
    $content = file_get_contents($path);
    $original = $content;

    if (strpos($content, 'css/arabic-file-input.css') === false) {
        if (stripos($content, '</head>') !== false) {
            $content = preg_replace('/<\/head>/i', "    {$cssTag}\n</head>", $content, 1);
        } else {
            $content = $cssTag . PHP_EOL . $content;
        }
    }

    if (strpos($content, 'js/arabic-file-input.js') === false) {
        if (stripos($content, '</body>') !== false) {
            $content = preg_replace('/<\/body>/i', "    {$jsTag}\n</body>", $content, 1);
        } else {
            $content .= PHP_EOL . $jsTag . PHP_EOL;
        }
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
        $changed[] = $relative;
    }
}

if (!$found) {
    fwrite(STDERR, "No layout file found. Please add these lines manually:\n{$cssTag}\n{$jsTag}\n");
    exit(1);
}

echo "Arabic file input assets installed.\n";
echo "Layouts scanned: " . implode(', ', $found) . "\n";
if ($changed) {
    echo "Layouts updated: " . implode(', ', $changed) . "\n";
} else {
    echo "No layout changes needed; tags already exist.\n";
}

<?php

$root = dirname(__DIR__);
$errors = [];

$program = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'DocArchivePrintPreview' . DIRECTORY_SEPARATOR . 'Program.cs';
$readme = $root . DIRECTORY_SEPARATOR . 'README_LEGACY_PRINT_PREVIEW_UI_COMPILE_FIX.txt';

foreach ([$program, $readme] as $file) {
    if (! is_file($file)) {
        $errors[] = 'Missing: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $file);
    }
}

if (is_file($program)) {
    $contents = file_get_contents($program);
    foreach ([
        'private readonly Label statusLabel;',
        'private readonly Label statusBadge;',
        'private readonly Label titleLabel;',
        'private readonly Label urlLabel;',
        'private readonly Label hintLabel;',
    ] as $bad) {
        if (str_contains($contents, $bad)) {
            $errors[] = 'Still contains readonly UI label declaration: ' . $bad;
        }
    }
}

if ($errors) {
    echo "Legacy print preview UI compile fix check failed:\n";
    foreach ($errors as $error) {
        echo '- ' . $error . "\n";
    }
    exit(1);
}

echo "Legacy print preview UI compile fix check passed.\n";

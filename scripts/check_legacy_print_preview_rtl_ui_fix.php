<?php

$root = realpath(__DIR__ . '/..');
$errors = [];

$requiredFiles = [
    'tools/DocArchivePrintPreview/Program.cs',
    'README_LEGACY_PRINT_PREVIEW_RTL_UI_FIX.txt',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $errors[] = "Missing: {$file}";
    }
}

$programPath = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'DocArchivePrintPreview' . DIRECTORY_SEPARATOR . 'Program.cs';
if (is_file($programPath)) {
    $contents = file_get_contents($programPath);
    $checks = [
        'RightToLeftLayout = false' => 'Form layout must be manually controlled to avoid mirrored button placement issues.',
        'private void LayoutActionButtons' => 'Manual RTL button layout method is missing.',
        'private void LayoutHeaderPanel' => 'Manual header layout method is missing.',
        'DocArchive' => 'DocArchive branding is missing.',
        'معاينة الطباعة القديمة' => 'Arabic legacy print preview button text is missing.',
    ];

    foreach ($checks as $needle => $message) {
        if (strpos($contents, $needle) === false) {
            $errors[] = $message;
        }
    }
}

if ($errors) {
    echo "Legacy print preview RTL UI fix check failed:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "Legacy print preview RTL UI fix check passed.\n";
exit(0);

<?php

$root = dirname(__DIR__);
$required = [
    'resources/views/form-links/print.blade.php',
];

$errors = [];

foreach ($required as $file) {
    if (! is_file($root . DIRECTORY_SEPARATOR . $file)) {
        $errors[] = "Missing file: {$file}";
    }
}

$viewPath = $root . DIRECTORY_SEPARATOR . 'resources/views/form-links/print.blade.php';

if (is_file($viewPath)) {
    $view = file_get_contents($viewPath);

    $checks = [
        '$recommendedSettings = $recommendedSettings ??' => 'recommendedSettings fallback',
        '$sourceUrl = $sourceUrl ??' => 'sourceUrl fallback',
        'docarchive-print://preview' => 'legacy protocol button',
        'فتح ببرنامج المعاينة القديمة' => 'legacy print preview button text',
    ];

    foreach ($checks as $needle => $label) {
        if (! str_contains($view, $needle)) {
            $errors[] = "Missing {$label} in print view.";
        }
    }
}

if ($errors) {
    echo "Legacy print variable fix check failed:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "Legacy print variable fix check passed.\n";
echo "- print.blade.php now has safe defaults for recommendedSettings and sourceUrl.\n";
echo "- /form-links/{id}/print should no longer fail with Undefined variable recommendedSettings.\n";

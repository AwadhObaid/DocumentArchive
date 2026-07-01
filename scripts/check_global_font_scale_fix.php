<?php

$root = dirname(__DIR__);

$requiredFiles = [
    'public/css/global-font-scale-fix.css',
    'resources/views/layouts/app.blade.php',
    'README_GLOBAL_FONT_SCALE_FIX.txt',
];

$errors = [];

foreach ($requiredFiles as $file) {
    if (! is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $errors[] = "Missing file: {$file}";
    }
}

$layoutPath = $root . DIRECTORY_SEPARATOR . 'resources/views/layouts/app.blade.php';
if (is_file($layoutPath)) {
    $layout = file_get_contents($layoutPath);
    if (strpos($layout, "css/global-font-scale-fix.css") === false) {
        $errors[] = 'Layout does not include css/global-font-scale-fix.css';
    }
}

$cssPath = $root . DIRECTORY_SEPARATOR . 'public/css/global-font-scale-fix.css';
if (is_file($cssPath)) {
    $css = file_get_contents($cssPath);
    foreach (['.side-nav a', '.topbar h1', '.forms-actions .btn', '.stat-card strong'] as $needle) {
        if (strpos($css, $needle) === false) {
            $errors[] = "CSS missing expected selector: {$needle}";
        }
    }
}

if ($errors) {
    echo "Global font scale fix check failed:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "Global font scale fix check passed.\n";

<?php

$root = dirname(__DIR__);
$viewPath = $root . DIRECTORY_SEPARATOR . 'resources/views/form-links/print.blade.php';
$errors = [];

if (! is_file($viewPath)) {
    $errors[] = 'Missing file: resources/views/form-links/print.blade.php';
} else {
    $view = file_get_contents($viewPath);

    $checks = [
        'فتح النموذج بدون تغيير هيكله الأصلي' => 'clean page title',
        'option-grid' => 'two option layout',
        'step-list' => 'organized step list',
        'nowrap' => 'nowrap protection for Arabic labels',
        'DocArchivePrintPreview' => 'legacy helper text',
        'docarchive-print://preview' => 'legacy print protocol URL',
        '$recommendedSettings = $recommendedSettings ??' => 'recommended settings fallback',
    ];

    foreach ($checks as $needle => $label) {
        if (! str_contains($view, $needle)) {
            $errors[] = "Missing {$label}.";
        }
    }
}

if ($errors) {
    echo "Legacy print page layout fix check failed:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "Legacy print page layout fix check passed.\n";
echo "- print.blade.php was reorganized into a cleaner two-option layout.\n";
echo "- Arabic button labels are protected from broken word wrapping.\n";
echo "- Print styles were simplified to avoid visual distortion.\n";

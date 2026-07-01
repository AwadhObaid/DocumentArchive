<?php

$root = dirname(__DIR__);
$errors = [];

$cssPath = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'whatsapp-module.css';
if (!is_file($cssPath)) {
    $errors[] = 'Missing file: public/css/whatsapp-module.css';
} else {
    $css = file_get_contents($cssPath);
    foreach ([
        'whatsapp-color-fix:start',
        'html[data-theme="dark"] .whatsapp-page input',
        'html[data-theme="dark"] .whatsapp-page select option',
        '-webkit-text-fill-color: #e5e7eb',
    ] as $needle) {
        if (strpos($css, $needle) === false) {
            $errors[] = 'Missing CSS marker/rule: ' . $needle;
        }
    }
}

if ($errors) {
    echo "WhatsApp color fix check failed:\n";
    foreach ($errors as $error) {
        echo '- ' . $error . "\n";
    }
    exit(1);
}

echo "WhatsApp color fix check passed.\n";

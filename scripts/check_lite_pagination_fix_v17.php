<?php

$root = dirname(__DIR__);
$css = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'lite.css';

$errors = [];

if (! is_file($css)) {
    $errors[] = 'Missing public/css/lite.css';
} else {
    $content = file_get_contents($css);
    foreach ([
        'Lite pagination fix V17',
        '.lite-pagination nav[role="navigation"] > div:first-child',
        '.lite-pagination nav[role="navigation"] svg',
        'span[aria-current="page"]',
    ] as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = "Missing CSS marker: {$needle}";
        }
    }
}

if ($errors) {
    echo "RESULT: FAIL\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: Lite pagination CSS fix V17 is installed.\n";
echo "RESULT: OK\n";

<?php

$basePath = dirname(__DIR__);
$requiredFiles = [
    'public/js/app.js',
    'public/css/email-module.css',
    'resources/views/emails/index.blade.php',
    'resources/views/emails/compose.blade.php',
    'README_EMAIL_POLISH_FIX.txt',
];

$errors = [];

foreach ($requiredFiles as $file) {
    if (!is_file($basePath . DIRECTORY_SEPARATOR . $file)) {
        $errors[] = "Missing file: {$file}";
    }
}

$checks = [
    'public/js/app.js' => [
        'app-confirm-dialog',
        'openConfirmDialog',
        'data-confirm-title',
    ],
    'public/css/email-module.css' => [
        '.email-summary-card strong',
        '.email-summary-icon',
        '.app-confirm-overlay',
        '.app-confirm-card',
    ],
    'resources/views/emails/index.blade.php' => [
        'email-summary-icon',
        'إحصائيات البريد الإلكتروني',
    ],
    'resources/views/emails/compose.blade.php' => [
        'data-confirm-title="تأكيد إرسال البريد الإلكتروني"',
        'data-confirm-yes="نعم، إرسال الآن"',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $basePath . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        continue;
    }

    $contents = file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $errors[] = "Missing expected content in {$file}: {$needle}";
        }
    }
}

if ($errors) {
    echo "Email polish fix check failed:" . PHP_EOL;
    foreach ($errors as $error) {
        echo "- {$error}" . PHP_EOL;
    }
    exit(1);
}

echo "Email polish fix check passed." . PHP_EOL;

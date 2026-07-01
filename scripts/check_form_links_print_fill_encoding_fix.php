<?php

$root = dirname(__DIR__);
$errors = [];

$requiredFiles = [
    'app/Http/Controllers/FormLinkController.php',
    'resources/views/form-links/print.blade.php',
];

foreach ($requiredFiles as $file) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (! is_file($path)) {
        $errors[] = "Missing required file: {$file}";
    }
}

$checks = [
    'app/Http/Controllers/FormLinkController.php' => [
        'detectRemoteHtmlEncoding' => 'Missing encoding detection helper.',
        'normalizeEncodingName' => 'Missing encoding normalizer.',
        'convertHtmlToUtf8' => 'Missing safe UTF-8 conversion helper.',
        'iconvEncodingCandidates' => 'Missing iconv encoding candidates helper.',
        'Windows-1256' => 'Missing Windows-1256 support.',
        'CP1256' => 'Missing CP1256 fallback.',
        "catch (\\Throwable)" => 'Missing safe conversion exception handling.',
    ],
    'resources/views/form-links/print.blade.php' => [
        'طريقة الاستخدام الصحيحة' => 'Missing fill-before-print instruction.',
        'عبّئ النموذج داخل الإطار الأبيض' => 'Missing iframe fill instruction.',
        'applyPrintScale' => 'Missing live scale function.',
        'data-scale' => 'Missing scale buttons data attribute.',
        'لا تعبّئ النموذج في تبويب المصدر الأصلي' => 'Missing cross-tab warning.',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (! is_file($path)) {
        continue;
    }

    $contents = file_get_contents($path);
    foreach ($needles as $needle => $message) {
        if (! str_contains($contents, $needle)) {
            $errors[] = $message . " ({$file})";
        }
    }
}

if (! empty($errors)) {
    echo "Form links print fill/encoding fix check failed:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "Form links print fill/encoding fix check passed.\n";
echo "Use: إدارة النماذج > طباعة محسّنة > fill inside the white preview frame > طباعة الآن\n";

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$serviceFile = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'BookAttachmentSmartPathService.php';
$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$errors = [];

if (! is_file($serviceFile)) {
    $errors[] = 'BookAttachmentSmartPathService.php is missing.';
} else {
    $text = file_get_contents($serviceFile) ?: '';

    if (! str_contains($text, "str_replace(['\\\\', '/', ':', '*', '?', '\"', '<', '>', '|'], ' ', \$value)")) {
        $errors[] = 'Safe Windows filename replacement is missing.';
    }

    if (str_contains($text, "preg_replace('/[\\\\\\/\\:\\*\\?\"\\<\\>\\|]+/u'")) {
        $errors[] = 'Invalid delimiter-sensitive folder-name regex is still present.';
    }
}

if (! is_file($autoload)) {
    $errors[] = 'vendor/autoload.php is missing. Run composer install.';
} elseif ($errors === []) {
    require_once $autoload;

    try {
        $service = new App\Services\BookAttachmentSmartPathService();
        $input = 'DHL/EXPRESS\\Export: A*B?"<C>|';
        $result = $service->sanitizeFolderName($input);

        if ($result === null || $result === '') {
            $errors[] = 'sanitizeFolderName returned an empty result.';
        } elseif (strpbrk($result, '\\/:*?"<>|') !== false) {
            $errors[] = 'sanitizeFolderName left a Windows-invalid filename character: ' . $result;
        }

        $arabic = $service->sanitizeFolderName('شركة / إفراج جمركي');
        if ($arabic === null || ! str_contains($arabic, 'شركة') || ! str_contains($arabic, 'إفراج')) {
            $errors[] = 'Arabic folder text was not preserved correctly.';
        }
    } catch (Throwable $e) {
        $errors[] = 'Runtime sanitizer test failed: ' . $e->getMessage();
    }
}

$updateCopy = $root . DIRECTORY_SEPARATOR . 'updates' . DIRECTORY_SEPARATOR . 'attachments_scanner_v75_v80'
    . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'BookAttachmentSmartPathService.php';

if (is_file($updateCopy)) {
    $updateText = file_get_contents($updateCopy) ?: '';
    if (str_contains($updateText, "preg_replace('/[\\\\\\/\\:\\*\\?\"\\<\\>\\|]+/u'")) {
        $errors[] = 'The V75/V80 update payload still contains the invalid regex.';
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[FAIL] ' . $error . PHP_EOL);
    }
    exit(1);
}

echo 'Book attachment path sanitizer V75/V80 V3 check passed.' . PHP_EOL;

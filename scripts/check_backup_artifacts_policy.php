<?php

$root = dirname(__DIR__);
$gitignore = $root . DIRECTORY_SEPARATOR . '.gitignore';
$errors = [];

$required = [
    'app/**/_backup*/',
    'resources/**/_backup*/',
    'routes/**/_backup*/',
    '*.before-*.bak',
];

if (!file_exists($gitignore)) {
    $errors[] = '.gitignore غير موجود.';
} else {
    $content = file_get_contents($gitignore);
    foreach ($required as $rule) {
        if (!str_contains($content, $rule)) {
            $errors[] = "قاعدة التجاهل غير موجودة في .gitignore: {$rule}";
        }
    }
}

$targets = [
    $root . DIRECTORY_SEPARATOR . 'app',
    $root . DIRECTORY_SEPARATOR . 'resources',
    $root . DIRECTORY_SEPARATOR . 'routes',
];

$found = [];
foreach ($targets as $target) {
    if (!is_dir($target)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        $name = $item->getFilename();
        $path = $item->getPathname();
        if ($item->isDir() && str_starts_with($name, '_backup')) {
            $found[] = relativePath($root, $path);
        }
        if ($item->isFile() && (str_ends_with($name, '.bak') || str_contains($name, '.before-'))) {
            $found[] = relativePath($root, $path);
        }
    }
}

if ($found !== []) {
    $errors[] = "ما زالت توجد ملفات/مجلدات backup داخل مجلدات Laravel النشطة:\n- " . implode("\n- ", $found);
}

if ($errors !== []) {
    echo "ERROR: سياسة تنظيف ملفات backup لم تكتمل.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: Backup artifacts policy is active and no old backup folders/files were found under app/resources/routes.\n";

function relativePath(string $root, string $path): string
{
    return ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
}

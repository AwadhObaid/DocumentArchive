<?php
function project_root(): string
{
    $dir = __DIR__;
    for ($i = 0; $i < 8; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan')) return $dir;
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    echo "ERROR: تعذر تحديد جذر المشروع.\n";
    exit(1);
}

$root = project_root();
$provider = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'AppServiceProvider.php';
$errors = [];

if (!is_file($provider)) {
    $errors[] = 'الملف غير موجود: app/Providers/AppServiceProvider.php';
} else {
    $code = file_get_contents($provider) ?: '';
    foreach ([
        'namespace App\\Providers;',
        'use Illuminate\\Support\\Facades\\View;',
        'daQrUrl',
        'daQrDocument',
        'daQrSettings',
        'daQrEnabled',
        'daQrVisible',
        'daQrPrintOnly',
        'View::share',
    ] as $needle) {
        if (strpos($code, $needle) === false) {
            $errors[] = "ناقص داخل AppServiceProvider.php: {$needle}";
        }
    }
}

$backupFound = [];
foreach (['app', 'resources', 'routes'] as $base) {
    $basePath = $root . DIRECTORY_SEPARATOR . $base;
    if (!is_dir($basePath)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isDir() && str_starts_with($file->getFilename(), '_backup')) {
            $backupFound[] = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }
}
if ($backupFound) {
    $errors[] = 'ما زالت توجد مجلدات backup داخل مسارات Laravel: ' . implode(', ', array_slice($backupFound, 0, 10));
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح متغيرات QR الاحتياطية V8.\n";
    foreach ($errors as $e) echo "- {$e}\n";
    exit(1);
}

echo "OK: متغيرات QR الاحتياطية مضافة داخل AppServiceProvider ولا توجد مجلدات backup داخل المسارات النشطة.\n";

<?php
$root = realpath(__DIR__ . '/..');
$errors = [];
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    $errors[] = 'تأكد أنك تشغل الفحص من جذر مشروع Laravel.';
} else {
    $provider = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'AppServiceProvider.php';
    $code = file_exists($provider) ? file_get_contents($provider) : '';
    foreach ([
        'Illuminate\\Support\\Facades\\View',
        'daQrUrl',
        'daQrDocument',
        'daQrSettings',
        'daQrEnabled',
        'daQrVisible',
        'daQrPrintOnly',
    ] as $needle) {
        if (strpos((string)$code, $needle) === false) {
            $errors[] = "ناقص داخل AppServiceProvider.php: {$needle}";
        }
    }

    foreach (['app', 'resources', 'routes'] as $dirName) {
        $dir = $root . DIRECTORY_SEPARATOR . $dirName;
        if (!is_dir($dir)) continue;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
                $errors[] = 'ما زال يوجد مجلد backup داخل مسارات Laravel: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $item->getPathname());
            }
        }
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح متغيرات QR الاحتياطية.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: تم إضافة متغيرات QR الاحتياطية وتنظيف مجلدات backup.\n";

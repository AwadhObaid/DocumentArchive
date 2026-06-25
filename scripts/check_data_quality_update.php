<?php

$root = dirname(__DIR__);
$errors = [];

$controller = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'DataQualityController.php';
$view = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'data-quality' . DIRECTORY_SEPARATOR . 'index.blade.php';
$routes = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';

if (!file_exists($controller)) {
    $errors[] = 'DataQualityController.php غير موجود.';
} else {
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($controller), $output, $code);
    if ($code !== 0) {
        $errors[] = 'يوجد خطأ syntax في DataQualityController.php: ' . implode(' ', $output);
    }
}

if (!file_exists($view)) {
    $errors[] = 'واجهة جودة البيانات غير موجودة.';
}

if (!file_exists($routes) || !str_contains(file_get_contents($routes), "data-quality.index")) {
    $errors[] = 'مسار data-quality.index غير موجود في routes/web.php.';
}

if ($errors !== []) {
    echo "ERROR: لم يكتمل تحديث جودة البيانات.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: تحديث جودة البيانات مركب بشكل صحيح.\n";
echo "افتح: http://127.0.0.1:8000/data-quality\n";

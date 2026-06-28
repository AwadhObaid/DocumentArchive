<?php
$root = realpath(__DIR__ . '/..');
$errors = [];
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    $errors[] = 'تأكد أنك داخل جذر مشروع Laravel.';
}

$controllerPath = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'DashboardController.php';
$viewPath = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'index.blade.php';

if (!file_exists($controllerPath)) {
    $errors[] = 'DashboardController.php غير موجود.';
} else {
    $controller = file_get_contents($controllerPath);
    if (!str_contains($controller, '[DA-DASHBOARD-OBJECT-COMPAT-START]')) {
        $errors[] = 'بلوك توافق array/object غير موجود داخل DashboardController.';
    }
}

if (!file_exists($viewPath)) {
    $errors[] = 'resources/views/dashboard/index.blade.php غير موجود.';
} else {
    $view = file_get_contents($viewPath);
    if (preg_match('/\$(document|doc|latestDocument|recentDocument|latestDoc|recentDoc)->id\b/', $view)) {
        $errors[] = 'ما زالت توجد قراءة ->id مباشرة داخل لوحة التحكم.';
    }
}

foreach (['app','resources','routes'] as $base) {
    $basePath = $root . DIRECTORY_SEPARATOR . $base;
    if (!is_dir($basePath)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir() && str_starts_with($fileInfo->getFilename(), '_backup')) {
            $errors[] = 'يوجد مجلد backup داخل مسارات Laravel: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $fileInfo->getPathname());
            break 2;
        }
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح لوحة التحكم.\n";
    foreach ($errors as $error) echo "- {$error}\n";
    exit(1);
}

echo "OK: إصلاح توافق لوحة التحكم مكتمل.\n";

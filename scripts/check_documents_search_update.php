<?php
$root = dirname(__DIR__);
$errors = [];

$controller = $root . '/app/Http/Controllers/DocumentController.php';
$view = $root . '/resources/views/documents/index.blade.php';

if (!file_exists($controller)) {
    $errors[] = 'DocumentController.php غير موجود.';
} else {
    $content = file_get_contents($controller);
    foreach (['has_attachment', 'priorityOptions', 'confidentialityOptions', 'summary'] as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = "DocumentController لا يحتوي على {$needle}.";
        }
    }
}

if (!file_exists($view)) {
    $errors[] = 'واجهة documents/index.blade.php غير موجودة.';
} else {
    $content = file_get_contents($view);
    foreach (['بحث وفرز سريع', 'كتب لديها مرفقات', 'درجة السرية', 'عدد النتائج'] as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = "واجهة الكتب لا تحتوي على: {$needle}.";
        }
    }
}

if ($errors) {
    echo "Documents search update check: FAILED\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "Documents search update check: OK\n";
echo "صفحة الكتب والبحث المتقدم جاهزة.\n";

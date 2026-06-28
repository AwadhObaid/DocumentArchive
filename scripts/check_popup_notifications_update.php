<?php
$base = dirname(__DIR__);
$errors = [];

$required = [
    'public/css/app-notifications.css',
    'public/js/app-notifications.js',
    'resources/views/partials/flash-notifications.blade.php',
];

foreach ($required as $file) {
    if (!file_exists($base . '/' . $file)) {
        $errors[] = "ملف مفقود: {$file}";
    }
}

$layout = null;
foreach (['resources/views/layouts/app.blade.php', 'resources/views/layouts/admin.blade.php'] as $candidate) {
    if (file_exists($base . '/' . $candidate)) {
        $layout = $base . '/' . $candidate;
        break;
    }
}

if (!$layout) {
    $errors[] = 'لم يتم العثور على ملف layout لفحص الربط.';
} else {
    $content = file_get_contents($layout);
    foreach (['app-notifications.css', 'partials.flash-notifications', 'app-notifications.js'] as $marker) {
        if (strpos($content, $marker) === false) {
            $errors[] = "الربط مفقود داخل layout: {$marker}";
        }
    }

    if (substr_count($content, 'partials.flash-notifications') > 1) {
        $errors[] = 'تم تضمين partial الإشعارات أكثر من مرة في layout.';
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل تركيب الإشعارات المنبثقة.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: الإشعارات المنبثقة مركبة بنجاح.\n";
echo "اختبرها بتنفيذ أي عملية ترجع with('success', '...') أو جرّب أخطاء التحقق في النماذج.\n";

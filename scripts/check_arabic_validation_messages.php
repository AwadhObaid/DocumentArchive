<?php

$root = getcwd();
$errors = [];

$requiredFiles = [
    'public/js/arabic-form-validation.js',
    'public/css/arabic-validation.css',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = "ملف مفقود: {$file}";
    }
}

$layoutCandidates = [
    'resources/views/layouts/app.blade.php',
    'resources/views/layouts/admin.blade.php',
    'resources/views/app.blade.php',
];

$hasJs = false;
$hasCss = false;
foreach ($layoutCandidates as $layout) {
    $path = $root . '/' . $layout;
    if (!is_file($path)) continue;
    $content = file_get_contents($path);
    if (str_contains($content, 'arabic-form-validation.js')) $hasJs = true;
    if (str_contains($content, 'arabic-validation.css')) $hasCss = true;
}

if (!$hasJs) $errors[] = 'لم يتم ربط arabic-form-validation.js في layout.';
if (!$hasCss) $errors[] = 'لم يتم ربط arabic-validation.css في layout.';

if ($errors) {
    echo "ERROR: لم يكتمل تركيب رسائل التحقق العربية.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: رسائل التحقق العربية مركبة بنجاح.\n";
echo "اختبر بإرسال نموذج وفيه حقل مطلوب فارغ؛ يجب أن تظهر رسالة عربية بدلاً من Please fill out this field.\n";

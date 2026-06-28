<?php
$root = dirname(__DIR__);
$checks = [
    'app/Http/Controllers/DataQualityController.php' => ['duplicatePolicies', 'withoutAttachments', 'safeTrashedDocuments'],
    'resources/views/data-quality/index.blade.php' => ['copyDQValue', 'طباعة التقرير', 'partials.duplicate-policy-panel'],
    'resources/views/data-quality/partials/documents-panel.blade.php' => ['route(\'documents.show\'', 'البوليصة الرئيسية', 'البوليصة الفرعية'],
    'resources/views/data-quality/partials/duplicate-policy-panel.blade.php' => ['نسخ الرقم', 'الكتب المرتبطة', 'dq-pill'],
];

$errors = [];
foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    if (!file_exists($path)) {
        $errors[] = "ملف مفقود: {$file}";
        continue;
    }
    $content = file_get_contents($path);
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = "لم يتم العثور على '{$needle}' داخل {$file}";
        }
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل تحديث جودة البيانات.\n- " . implode("\n- ", $errors) . "\n";
    exit(1);
}

echo "OK: تحديث جودة البيانات وروابط المراجعة مركب بنجاح.\n";

<?php
$root = dirname(__DIR__);

$checks = [
    'routes/web.php' => [
        'documents.check-policy-duplicate',
        '/documents/check-policy-duplicate',
    ],
    'app/Http/Controllers/DocumentController.php' => [
        'function checkPolicyDuplicate',
        'main_policy_number,sub_policy_number',
        'رقم {$label} موجود مسبقاً',
    ],
    'resources/views/layouts/app.blade.php' => [
        'DA_POLICY_DUPLICATE_WARNING_START',
        'تنبيه تكرار البوليصة',
        'نعم، مواصلة',
        'لا، منع',
    ],
];

$errors = [];
foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    if (!file_exists($path)) {
        $errors[] = "الملف غير موجود: {$file}";
        continue;
    }

    $content = file_get_contents($path);
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) {
            $errors[] = "العنصر ناقص في {$file}: {$needle}";
        }
    }
}

if ($errors) {
    echo "ERROR: لم يتم تركيب تحديث فحص تكرار البوليصة بشكل كامل.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: Policy duplicate warning update is installed.\n";
echo "تم التحقق من المسار، دالة الفحص، ونافذة نعم/لا في الواجهة.\n";

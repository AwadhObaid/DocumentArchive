<?php
$root = dirname(__DIR__);
$viewPath = $root . '/resources/views/documents/index.blade.php';

if (!file_exists($viewPath)) {
    echo "ERROR: resources/views/documents/index.blade.php غير موجود.\n";
    exit(1);
}

$content = file_get_contents($viewPath);
$required = [
    'البوليصة الرئيسية',
    'البوليصة الفرعية',
    'main_policy_number',
    'sub_policy_number',
    'document-mobile-card',
    'document-subject-cell',
];

$missing = [];
foreach ($required as $needle) {
    if (strpos($content, $needle) === false) {
        $missing[] = $needle;
    }
}

if ($missing) {
    echo "ERROR: عناصر ناقصة في صفحة الكتب: " . implode(', ', $missing) . "\n";
    exit(1);
}

echo "OK: Documents policy columns update is installed.\n";
echo "تم التحقق من ظهور أعمدة الموضوع والبوليصة الرئيسية والفرعية.\n";

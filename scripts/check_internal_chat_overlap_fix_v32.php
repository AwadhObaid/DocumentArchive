<?php

$root = dirname(__DIR__);
$css = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'internal-chat.css';

$errors = [];
if (!is_file($css)) {
    $errors[] = 'ملف CSS غير موجود: public/css/internal-chat.css';
} else {
    $content = file_get_contents($css);
    foreach ([
        'left: 94px;' => 'موضع الدردشة على سطح المكتب',
        'left: 86px;' => 'موضع الدردشة على الجوال',
        'bottom: 82px;' => 'موضع نافذة الدردشة على الجوال',
        'z-index: 10000;' => 'طبقة ظهور الدردشة',
    ] as $needle => $label) {
        if (strpos($content, $needle) === false) {
            $errors[] = "لم يتم العثور على {$label}: {$needle}";
        }
    }
}

if ($errors) {
    echo "❌ فشل فحص إصلاح تداخل الدردشة والإشعارات V32\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "✅ تم فحص إصلاح تداخل الدردشة والإشعارات V32 بنجاح.\n";

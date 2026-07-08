<?php

$root = dirname(__DIR__);
$checks = [
    'app/Http/Controllers/SettingsController.php' => [
        'internal_chat_sound_volume' => 'إعداد مستوى صوت الدردشة',
        "'min:0'" => 'الحد الأدنى للصوت',
        "'max:100'" => 'الحد الأعلى للصوت',
    ],
    'resources/views/settings/edit.blade.php' => [
        'مستوى صوت تنبيه الدردشة' => 'حقل مستوى الصوت في الإعدادات',
        'internal_chat_sound_volume_number' => 'حقل رقم مستوى الصوت',
        'type="range"' => 'شريط التحكم بالصوت',
    ],
    'resources/views/partials/internal-chat-widget.blade.php' => [
        'data-sound-volume' => 'تمرير مستوى الصوت للواجهة',
    ],
    'public/js/internal-chat.js' => [
        'dataset.soundVolume' => 'قراءة مستوى الصوت من الإعدادات',
        'peakGain = 0.75 * peakVolume' => 'رفع قوة النغمة مع التحكم بالصوت',
        "internal-chat-user-text" => 'كلاس نص المستخدم لفصل الاسم عن المعاينة',
    ],
    'public/css/internal-chat.css' => [
        '.internal-chat-user-text' => 'تنسيق عمود نص المستخدم',
        'display: block;' => 'منع تلاصق الاسم والمعاينة',
        'minmax(0, 1fr)' => 'منع ضغط النص داخل قائمة المستخدمين',
    ],
];

$failed = false;
foreach ($checks as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (! is_file($path)) {
        echo "[FAIL] Missing file: {$file}\n";
        $failed = true;
        continue;
    }

    $content = file_get_contents($path);
    foreach ($needles as $needle => $label) {
        if (strpos($content, $needle) === false) {
            echo "[FAIL] {$file}: {$label}\n";
            $failed = true;
        } else {
            echo "[OK] {$file}: {$label}\n";
        }
    }
}

if ($failed) {
    echo "\nInternal chat volume/spacing V36 check failed.\n";
    exit(1);
}

echo "\nInternal chat volume/spacing V36 check passed.\n";

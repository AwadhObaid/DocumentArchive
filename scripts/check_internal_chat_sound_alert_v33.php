<?php

$root = dirname(__DIR__);
$requiredFiles = [
    'app/Http/Controllers/SettingsController.php',
    'public/css/internal-chat.css',
    'public/js/internal-chat.js',
    'resources/views/partials/internal-chat-widget.blade.php',
    'resources/views/settings/edit.blade.php',
];

$missing = [];
foreach ($requiredFiles as $file) {
    if (! is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $missing[] = $file;
    }
}

$checks = [
    'setting_default' => ['app/Http/Controllers/SettingsController.php', "internal_chat_sound_enabled"],
    'widget_data' => ['resources/views/partials/internal-chat-widget.blade.php', "data-sound-enabled"],
    'widget_button' => ['resources/views/partials/internal-chat-widget.blade.php', "data-chat-sound-toggle"],
    'js_audio' => ['public/js/internal-chat.js', "playNotificationSound"],
    'css_button' => ['public/css/internal-chat.css', "internal-chat-sound-toggle"],
    'settings_ui' => ['resources/views/settings/edit.blade.php', "تفعيل التنبيه الصوتي عند وصول رسالة دردشة جديدة"],
];

$failed = [];
foreach ($checks as $name => [$file, $needle]) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (! is_file($path) || ! str_contains((string) file_get_contents($path), $needle)) {
        $failed[] = $name . ' => ' . $file;
    }
}

if ($missing || $failed) {
    echo "[FAIL] فشل فحص تحديث التنبيه الصوتي للدردشة الداخلية V33\n";
    if ($missing) {
        echo "Missing files:\n - " . implode("\n - ", $missing) . "\n";
    }
    if ($failed) {
        echo "Failed checks:\n - " . implode("\n - ", $failed) . "\n";
    }
    exit(1);
}

echo "[OK] تحديث التنبيه الصوتي للدردشة الداخلية V33 مركب بشكل صحيح.\n";
echo "افتح النظام، ثم اضغط داخل الصفحة مرة واحدة لتفعيل الصوت في المتصفح، وبعدها جرّب وصول رسالة جديدة.\n";

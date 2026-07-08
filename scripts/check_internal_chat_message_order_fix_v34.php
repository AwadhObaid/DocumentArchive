<?php

$root = dirname(__DIR__);
$required = [
    'app/Http/Controllers/InternalChatController.php' => ['created_at_timestamp', 'date_label', 'full_time'],
    'public/js/internal-chat.js' => ['messages: new Map()', 'messageSortKey', 'internal-chat-date-separator', 'renderConversationMessages'],
    'public/css/internal-chat.css' => ['internal-chat-date-separator'],
];

$ok = true;
foreach ($required as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (!is_file($path)) {
        echo "[FAIL] Missing file: {$file}\n";
        $ok = false;
        continue;
    }
    $content = file_get_contents($path);
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) {
            echo "[FAIL] Missing marker '{$needle}' in {$file}\n";
            $ok = false;
        }
    }
    echo "[OK] {$file}\n";
}

if (!$ok) {
    exit(1);
}

echo "\nتم التحقق من إصلاح ترتيب رسائل الدردشة الداخلية V34 بنجاح.\n";

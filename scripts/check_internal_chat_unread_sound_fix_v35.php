<?php
$root = dirname(__DIR__);
$required = [
    'public/js/internal-chat.js' => [
        'shouldFetchActiveConversation = state.open && !!state.activeUserId',
        'do not poll the active conversation while the chat panel is closed',
        'louder two-step notification tone',
        'tone(0, 880, 1040, 0.16)',
        'tone(0.18, 660, 880, 0.18)',
    ],
];

$ok = true;
foreach ($required as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (! is_file($path)) {
        echo "[FAIL] Missing file: {$file}\n";
        $ok = false;
        continue;
    }
    $content = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) {
            echo "[FAIL] Missing marker in {$file}: {$needle}\n";
            $ok = false;
        }
    }
}

if (! $ok) {
    exit(1);
}

echo "[OK] Internal chat unread counter and sound alert fix V35 is installed.\n";

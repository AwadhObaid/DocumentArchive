<?php

$root = dirname(__DIR__);
$file = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'memos' . DIRECTORY_SEPARATOR . 'index.blade.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: resources/views/memos/index.blade.php not found\n");
    exit(1);
}

$content = file_get_contents($file);
$checks = [
    'MEMOS_ACTION_BUTTON_COLORS_V8_START',
    'btn-memo-view',
    'btn-memo-email',
    'btn-memo-whatsapp',
    'btn-memo-share',
    'btn-memo-edit',
    'btn-memo-delete',
];

$ok = true;
foreach ($checks as $check) {
    if (strpos($content, $check) === false) {
        echo "MISSING: {$check}\n";
        $ok = false;
    } else {
        echo "OK: {$check}\n";
    }
}

echo $ok ? "RESULT: OK\n" : "RESULT: FAILED\n";
exit($ok ? 0 : 1);

<?php

$root = dirname(__DIR__);
$checks = [
    'routes/web.php' => [
        "memos.email.compose",
        "memos.whatsapp.compose",
        "memos.shared-attachments.create",
    ],
    'resources/views/memos/index.blade.php' => [
        'إرسال البريد',
        'إرسال واتساب',
        'رابط المرفقات',
    ],
    'app/Http/Controllers/EmailController.php' => [
        'composeMemo',
        'memo_id',
        'defaultsForMemo',
    ],
    'app/Http/Controllers/WhatsappController.php' => [
        'composeMemo',
        'memo_id',
        'defaultsForMemo',
    ],
    'app/Http/Controllers/SharedAttachmentLinkController.php' => [
        'createMemo',
        'createForMemo',
        'memo_id',
    ],
    'database/migrations/2026_07_02_132000_add_memo_context_to_email_whatsapp_and_shared_links.php' => [
        'email_messages',
        'whatsapp_messages',
        'shared_attachment_links',
        'memo_attachment_id',
    ],
];

$ok = true;
foreach ($checks as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (!is_file($path)) {
        echo "MISSING FILE: {$file}\n";
        $ok = false;
        continue;
    }

    $content = file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            echo "MISSING NEEDLE: {$needle} in {$file}\n";
            $ok = false;
        }
    }
}

echo $ok
    ? "Memo actions update check: OK\n"
    : "Memo actions update check: FAILED\n";

exit($ok ? 0 : 1);

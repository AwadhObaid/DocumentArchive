<?php

$root = dirname(__DIR__);
$checks = [
    'Controller previewAttachment method' => $root . '/app/Http/Controllers/InternalMessageController.php',
    'Controller attachmentData method' => $root . '/app/Http/Controllers/InternalMessageController.php',
    'Internal message attachment preview view' => $root . '/resources/views/internal-messages/attachment-preview.blade.php',
    'Internal message show links preview route' => $root . '/resources/views/internal-messages/show.blade.php',
    'Routes preview/data registered' => $root . '/routes/web.php',
];

$ok = true;

foreach ($checks as $label => $file) {
    if (! file_exists($file)) {
        echo "FAIL: {$label} - file missing: {$file}\n";
        $ok = false;
        continue;
    }

    $content = file_get_contents($file);
    $passed = match ($label) {
        'Controller previewAttachment method' => str_contains($content, 'function previewAttachment(InternalMessage $internalMessage'),
        'Controller attachmentData method' => str_contains($content, 'function attachmentData(InternalMessage $internalMessage'),
        'Internal message attachment preview view' => str_contains($content, 'لن يتم تنزيل الملف تلقائياً'),
        'Internal message show links preview route' => str_contains($content, "internal-messages.attachments.preview"),
        'Routes preview/data registered' => str_contains($content, "internal-messages.attachments.preview") && str_contains($content, "internal-messages.attachments.data"),
        default => false,
    };

    if ($passed) {
        echo "OK: {$label}\n";
    } else {
        echo "FAIL: {$label}\n";
        $ok = false;
    }
}

echo $ok ? "RESULT: OK\n" : "RESULT: FAILED\n";
exit($ok ? 0 : 1);

<?php

$root = dirname(__DIR__);

$requiredFiles = [
    'app/Http/Controllers/InternalMessageController.php',
    'app/Models/InternalMessage.php',
    'app/Models/InternalMessageAttachment.php',
    'database/migrations/2026_07_06_080000_create_internal_messages_tables.php',
    'resources/views/internal-messages/index.blade.php',
    'resources/views/internal-messages/create.blade.php',
    'resources/views/internal-messages/show.blade.php',
    'public/css/internal-messages.css',
];

$ok = true;

echo "------------------------------------------------------------\n";
echo "DocumentArchive Internal Messaging Update Check\n";
echo "------------------------------------------------------------\n";

foreach ($requiredFiles as $file) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
    if (is_file($path)) {
        echo "OK: {$file}\n";
    } else {
        echo "MISSING: {$file}\n";
        $ok = false;
    }
}

$contains = [
    'routes/web.php' => [
        'internal-messages.index',
        'documents.internal-message.create',
        'memos.internal-message.create',
    ],
    'app/Http/Middleware/ApplyRoutePermissions.php' => [
        'internal_messages.view',
        'internal_messages.send',
    ],
    'app/Support/PermissionRegistry.php' => [
        'internal_messages.view',
        'internal_messages.send',
        'المراسلات الداخلية',
    ],
    'resources/views/layouts/app.blade.php' => [
        'internal-messages.index',
        'side-nav-badge',
    ],
    'resources/views/documents/show.blade.php' => [
        'documents.internal-message.create',
    ],
    'resources/views/memos/show.blade.php' => [
        'memos.internal-message.create',
    ],
];

foreach ($contains as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
    $content = is_file($path) ? file_get_contents($path) : '';
    foreach ($needles as $needle) {
        if (str_contains($content, $needle)) {
            echo "OK: {$file} contains {$needle}\n";
        } else {
            echo "MISSING TEXT: {$file} => {$needle}\n";
            $ok = false;
        }
    }
}

echo "------------------------------------------------------------\n";
echo $ok ? "RESULT: OK\n" : "RESULT: FAILED\n";
echo "------------------------------------------------------------\n";

exit($ok ? 0 : 1);

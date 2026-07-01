<?php

$root = dirname(__DIR__);

$requiredFiles = [
    'app/Http/Controllers/SharedAttachmentLinkController.php',
    'app/Models/SharedAttachmentLink.php',
    'app/Models/SharedAttachmentLinkItem.php',
    'app/Services/SecureAttachmentLinkService.php',
    'database/migrations/2026_07_01_120500_create_shared_attachment_links_table.php',
    'resources/views/shared-attachment-links/index.blade.php',
    'resources/views/shared-attachment-links/create.blade.php',
    'resources/views/shared-attachment-links/show.blade.php',
    'resources/views/shared-attachment-links/public/show.blade.php',
    'resources/views/shared-attachment-links/public/password.blade.php',
    'resources/views/shared-attachment-links/public/unavailable.blade.php',
    'public/css/shared-attachments.css',
    'public/js/shared-attachments.js',
];

$missing = [];

foreach ($requiredFiles as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . $file)) {
        $missing[] = 'Missing file: ' . $file;
    }
}

$checks = [
    'routes/web.php' => [
        'shared-attachment-links.index',
        'shared-attachments.public.show',
        'documents.shared-attachments.create',
    ],
    'resources/views/layouts/app.blade.php' => [
        'shared-attachments.css',
        'shared-attachments.js',
        'مشاركة المرفقات',
    ],
    'app/Support/PermissionRegistry.php' => [
        'attachment_shares.view',
        'attachment_shares.create',
        'attachment_shares.revoke',
    ],
    'app/Http/Middleware/ApplyRoutePermissions.php' => [
        'shared-attachment-links.index',
        'attachment_shares.create',
    ],
    'resources/views/emails/compose.blade.php' => [
        'include_secure_attachment_link',
        'رابط مرفقات آمن',
    ],
    'resources/views/whatsapp/compose.blade.php' => [
        'include_secure_attachment_link',
        'رابط مرفقات آمن',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        $missing[] = 'Missing file: ' . $file;
        continue;
    }

    $content = file_get_contents($path);

    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $missing[] = 'Missing marker in ' . $file . ': ' . $needle;
        }
    }
}

if ($missing !== []) {
    echo "Secure attachment links update check failed:\n";
    foreach ($missing as $line) {
        echo "- {$line}\n";
    }
    exit(1);
}

echo "Secure attachment links update check passed.\n";

<?php

$root = dirname(__DIR__);

$requiredFiles = [
    'app/Http/Controllers/WhatsappController.php',
    'app/Models/WhatsappMessage.php',
    'database/migrations/2026_07_01_110500_create_whatsapp_messages_table.php',
    'public/css/whatsapp-module.css',
    'resources/views/whatsapp/index.blade.php',
    'resources/views/whatsapp/compose.blade.php',
    'resources/views/whatsapp/show.blade.php',
    'resources/views/layouts/app.blade.php',
    'resources/views/documents/index.blade.php',
    'resources/views/documents/show.blade.php',
    'routes/web.php',
    'app/Support/PermissionRegistry.php',
    'app/Http/Middleware/ApplyRoutePermissions.php',
    'README_WHATSAPP_MODULE_UPDATE.txt',
];

$errors = [];

foreach ($requiredFiles as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . $file)) {
        $errors[] = 'Missing file: ' . $file;
    }
}

$contains = [
    'routes/web.php' => [
        "WhatsappController",
        "whatsapp.index",
        "documents.whatsapp.compose",
    ],
    'resources/views/layouts/app.blade.php' => [
        "whatsapp-module.css",
        "route('whatsapp.index')",
    ],
    'app/Support/PermissionRegistry.php' => [
        "whatsapp.view",
        "whatsapp.send",
    ],
    'app/Http/Middleware/ApplyRoutePermissions.php' => [
        "whatsapp.index",
        "documents.whatsapp.compose",
    ],
    'resources/views/documents/index.blade.php' => [
        "documents.whatsapp.compose",
        "إرسال واتساب",
    ],
    'resources/views/documents/show.blade.php' => [
        "documents.whatsapp.compose",
        "إرسال واتساب",
    ],
];

foreach ($contains as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        continue;
    }

    $content = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = "Missing marker in {$file}: {$needle}";
        }
    }
}

$phpFiles = [
    'app/Http/Controllers/WhatsappController.php',
    'app/Models/WhatsappMessage.php',
    'database/migrations/2026_07_01_110500_create_whatsapp_messages_table.php',
];

foreach ($phpFiles as $file) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        continue;
    }

    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $errors[] = 'PHP syntax failed for ' . $file . ': ' . implode(' ', $output);
    }
}

if ($errors !== []) {
    echo "WhatsApp module check failed:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "WhatsApp module check passed successfully.\n";

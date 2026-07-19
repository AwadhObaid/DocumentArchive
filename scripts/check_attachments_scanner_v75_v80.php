<?php

$root = dirname(__DIR__);
$requiredFiles = [
    'app/Http/Controllers/AttachmentRelocationController.php',
    'app/Http/Controllers/ScannerInboxController.php',
    'app/Models/AttachmentRelocationRun.php',
    'app/Models/AttachmentRelocationItem.php',
    'app/Services/AttachmentRelocationService.php',
    'app/Services/ScannerInboxService.php',
    'resources/views/attachments_migration/index.blade.php',
    'resources/views/attachments_migration/show.blade.php',
    'resources/views/scanner_inbox/index.blade.php',
    'public/css/attachments-scanner-v75-v80.css',
    'public/js/attachments-scanner-v75-v80.js',
    'database/migrations/2026_07_16_120000_create_attachment_relocation_and_scanner_workflow_v75_v80.php',
];

$errors = [];
foreach ($requiredFiles as $file) {
    if (! is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $errors[] = "Missing file: {$file}";
    }
}

$routes = @file_get_contents($root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php') ?: '';
foreach (['attachments-migration.index', 'attachments-migration.execute', 'scanner-inbox.index', 'scanner-inbox.attach'] as $needle) {
    if (! str_contains($routes, $needle)) {
        $errors[] = "Missing route marker: {$needle}";
    }
}

$registry = @file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'PermissionRegistry.php') ?: '';
foreach (['attachments.relocate', 'scanner.workflow', 'scanner.settings'] as $permission) {
    if (! str_contains($registry, $permission)) {
        $errors[] = "Missing permission: {$permission}";
    }
}

$layout = @file_get_contents($root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php') ?: '';
foreach (['attachments-scanner-v75-v80-css:start', 'attachments-scanner-v75-v80-nav:start', 'attachments-scanner-v75-v80-js:start'] as $marker) {
    if (! str_contains($layout, $marker)) {
        $errors[] = "Missing layout marker: {$marker}";
    }
}

$service = @file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'BookAttachmentSmartPathService.php') ?: '';
if (! str_contains($service, 'storeExistingFile(Document $document')) {
    $errors[] = 'BookAttachmentSmartPathService missing storeExistingFile method.';
}

if ($errors) {
    echo "Attachments scanner V75/V80 check failed:" . PHP_EOL;
    foreach ($errors as $error) {
        echo " - {$error}" . PHP_EOL;
    }
    exit(1);
}

echo "Attachments migration + scanner workflow V75/V80 check passed." . PHP_EOL;

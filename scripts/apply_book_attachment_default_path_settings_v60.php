<?php

$root = dirname(__DIR__);
$required = [
    'app/Http/Controllers/SettingsController.php',
    'app/Http/Controllers/DocumentController.php',
    'app/Models/DocumentAttachment.php',
    'app/Services/BookAttachmentSmartPathService.php',
    'app/Services/PdfTextIndexingService.php',
    'resources/views/settings/edit.blade.php',
    'database/migrations/2026_07_13_080000_add_book_attachment_default_storage_path_v60.php',
];

$missing = [];
foreach ($required as $file) {
    if (! is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $missing[] = $file;
    }
}

if ($missing !== []) {
    echo "[FAIL] V60 files are missing:\n";
    foreach ($missing as $file) {
        echo " - {$file}\n";
    }
    exit(1);
}

echo "[OK] V60 files are in place.\n";
echo "Next: run php artisan migrate, then php scripts/check_book_attachment_default_path_settings_v60.php\n";

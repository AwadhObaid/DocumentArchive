<?php

$root = dirname(__DIR__);

$checks = [];
$failures = [];

function check_contains(string $label, string $file, string $needle): void
{
    global $root, $checks, $failures;
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    $ok = is_file($path) && str_contains(file_get_contents($path), $needle);
    $checks[] = [$label, $ok];
    if (! $ok) {
        $failures[] = $label;
    }
}

check_contains('SettingsController has book attachment storage setting', 'app/Http/Controllers/SettingsController.php', 'book_attachment_storage_root');
check_contains('Settings view has default path input', 'resources/views/settings/edit.blade.php', 'name="book_attachment_storage_root"');
check_contains('Smart path service supports custom storage disk', 'app/Services/BookAttachmentSmartPathService.php', 'book_attachment_custom_path');
check_contains('DocumentController stores custom root per attachment', 'app/Http/Controllers/DocumentController.php', "'storage_root_path' => \$storedFile['storage_root_path']");
check_contains('DocumentAttachment model has storage root fillable', 'app/Models/DocumentAttachment.php', "'storage_root_path'");
check_contains('PDF indexing resolves document attachment path through service', 'app/Services/PdfTextIndexingService.php', 'absolutePathForAttachment');
check_contains('V60 migration exists', 'database/migrations/2026_07_13_080000_add_book_attachment_default_storage_path_v60.php', 'storage_root_path');

foreach ($checks as [$label, $ok]) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}

$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$appFile = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

if (is_file($autoload) && is_file($appFile)) {
    require $autoload;
    $app = require $appFile;
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    try {
        if (! Illuminate\Support\Facades\Schema::hasColumn('document_attachments', 'storage_root_path')) {
            $failures[] = 'Database column document_attachments.storage_root_path is missing. Run php artisan migrate.';
            echo "[FAIL] Database column document_attachments.storage_root_path is missing. Run php artisan migrate." . PHP_EOL;
        } else {
            echo "[OK] Database column document_attachments.storage_root_path exists." . PHP_EOL;
        }

        $settingExists = Illuminate\Support\Facades\DB::table('settings')
            ->where('key', 'book_attachment_storage_root')
            ->exists();

        if (! $settingExists) {
            $failures[] = 'Setting book_attachment_storage_root is missing. Run php artisan migrate.';
            echo "[FAIL] Setting book_attachment_storage_root is missing. Run php artisan migrate." . PHP_EOL;
        } else {
            echo "[OK] Setting book_attachment_storage_root exists." . PHP_EOL;
        }
    } catch (Throwable $e) {
        $failures[] = 'Database check failed: ' . $e->getMessage();
        echo "[FAIL] Database check failed: " . $e->getMessage() . PHP_EOL;
    }
} else {
    echo "[WARN] Laravel bootstrap not found. Skipped database checks." . PHP_EOL;
}

if ($failures !== []) {
    echo PHP_EOL . "Book attachment default path settings V60 check failed." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "Book attachment default path settings V60 check passed." . PHP_EOL;

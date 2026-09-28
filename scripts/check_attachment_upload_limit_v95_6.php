<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

$failures = [];
$warnings = [];

function readProjectFile(string $projectRoot, string $relative): string
{
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

    if (! is_file($path)) {
        throw new RuntimeException("Missing file: {$relative}");
    }

    return (string) file_get_contents($path);
}

function assertContains(array &$failures, string $label, string $content, string $needle): void
{
    if (! str_contains($content, $needle)) {
        $failures[] = "{$label}: missing expected text: {$needle}";
    }
}

function assertNotContains(array &$failures, string $label, string $content, string $needle): void
{
    if (str_contains($content, $needle)) {
        $failures[] = "{$label}: unexpected text still present: {$needle}";
    }
}

try {
    $limitClass = readProjectFile($projectRoot, 'app/Support/AttachmentUploadLimits.php');
    assertContains($failures, 'AttachmentUploadLimits', $limitClass, 'public const MAX_MB = 50;');
    assertContains($failures, 'AttachmentUploadLimits', $limitClass, 'public const MAX_KB = self::MAX_MB * 1024;');
    assertContains($failures, 'AttachmentUploadLimits', $limitClass, 'public const MAX_BYTES = self::MAX_MB * 1024 * 1024;');

    $controllerFiles = [
        'app/Http/Controllers/CircularController.php',
        'app/Http/Controllers/DocumentAttachmentManagementController.php',
        'app/Http/Controllers/DocumentController.php',
        'app/Http/Controllers/InternalMessageController.php',
        'app/Http/Controllers/MemoController.php',
        'app/Http/Controllers/MiscBookController.php',
    ];

    foreach ($controllerFiles as $relative) {
        $content = readProjectFile($projectRoot, $relative);
        assertContains($failures, $relative, $content, 'use App\\Support\\AttachmentUploadLimits;');
        assertContains($failures, $relative, $content, 'AttachmentUploadLimits::MAX_KB');
        assertNotContains($failures, $relative, $content, 'max:20480');
        assertNotContains($failures, $relative, $content, '20 MB');
    }

    $settingsController = readProjectFile($projectRoot, 'app/Http/Controllers/SettingsController.php');
    assertContains($failures, 'SettingsController', $settingsController, "'smart_attachment_max_file_mb' => '50'");
    assertContains($failures, 'SettingsController', $settingsController, "'file_bridge_max_file_mb' => '50'");

    $smartService = readProjectFile($projectRoot, 'app/Services/SmartAttachmentBrowserService.php');
    assertContains($failures, 'SmartAttachmentBrowserService', $smartService, 'AttachmentUploadLimits::MAX_MB');
    assertNotContains($failures, 'SmartAttachmentBrowserService', $smartService, "Setting::getValue(self::SETTING_MAX_FILE_MB, '20')");

    $bridgeService = readProjectFile($projectRoot, 'app/Services/FileBridgeService.php');
    assertContains($failures, 'FileBridgeService', $bridgeService, 'AttachmentUploadLimits::MAX_MB');
    assertNotContains($failures, 'FileBridgeService', $bridgeService, 'SmartAttachmentBrowserService::SETTING_MAX_FILE_MB, 20');

    $settingsView = readProjectFile($projectRoot, 'resources/views/settings/edit.blade.php');
    assertContains($failures, 'settings/edit.blade.php', $settingsView, "smart_attachment_max_file_mb");
    assertContains($failures, 'settings/edit.blade.php', $settingsView, "?? 50");
    assertContains($failures, 'settings/edit.blade.php', $settingsView, "file_bridge_max_file_mb");
    assertNotContains($failures, 'settings/edit.blade.php', $settingsView, "?? 20");

    $viewExpectations = [
        'resources/views/circulars/_form.blade.php' => 'بحد أقصى 50 MB لكل ملف.',
        'resources/views/memos/_form.blade.php' => 'الحد الأقصى لكل ملف 50 MB.',
        'resources/views/misc-books/_form.blade.php' => 'بحد أقصى 50 MB لكل ملف.',
    ];

    foreach ($viewExpectations as $relative => $needle) {
        $content = readProjectFile($projectRoot, $relative);
        assertContains($failures, $relative, $content, $needle);
        assertNotContains($failures, $relative, $content, '20 MB');
    }

    $migration = readProjectFile(
        $projectRoot,
        'database/migrations/2026_09_24_000000_upgrade_attachment_limits_to_50_mb.php'
    );
    assertContains($failures, 'attachment-limit migration', $migration, "'smart_attachment_max_file_mb'");
    assertContains($failures, 'attachment-limit migration', $migration, "'file_bridge_max_file_mb'");
    assertContains($failures, 'attachment-limit migration', $migration, "->where('value', '20')");
    assertContains($failures, 'attachment-limit migration', $migration, "->update([");

    $chatAdmin = $projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'InternalChatAdminController.php';
    if (is_file($chatAdmin)) {
        $chatContent = (string) file_get_contents($chatAdmin);
        if (str_contains($chatContent, 'max:20480')) {
            $warnings[] = 'InternalChatAdminController still has a 20 MB backup-file limit; this is intentional and is not an attachment upload limit.';
        }
    }

    echo "DocumentArchive V95.6 attachment limit verification\n";
    echo "Target standard attachment limit: 50 MB (51,200 KB)\n";
    echo "Smart Attachment / File Bridge default: 50 MB; configurable range remains 1-100 MB.\n";

    if ($warnings !== []) {
        foreach ($warnings as $warning) {
            echo "[INFO] {$warning}\n";
        }
    }

    if ($failures !== []) {
        echo "\nVERIFICATION FAILED\n";
        foreach ($failures as $failure) {
            echo "[FAIL] {$failure}\n";
        }
        exit(1);
    }

    echo "\nVERIFICATION PASSED\n";
    exit(0);
} catch (Throwable $exception) {
    echo "VERIFICATION ERROR: {$exception->getMessage()}\n";
    exit(2);
}

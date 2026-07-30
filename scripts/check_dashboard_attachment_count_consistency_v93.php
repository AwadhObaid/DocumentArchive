<?php

declare(strict_types=1);

$projectRoot = $argv[1] ?? dirname(__DIR__);
$projectRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $projectRoot), DIRECTORY_SEPARATOR);

$paths = [
    'dashboard' => $projectRoot . '/app/Http/Controllers/DashboardController.php',
    'live_sync' => $projectRoot . '/app/Http/Controllers/LiveSyncController.php',
    'view' => $projectRoot . '/resources/views/dashboard/index.blade.php',
    'js' => $projectRoot . '/public/js/live-data-sync-v91.js',
];

$failures = 0;

function checkResult(bool $condition, string $message): void
{
    global $failures;

    echo $condition ? "[ OK ] {$message}\n" : "[FAIL] {$message}\n";

    if (! $condition) {
        $failures++;
    }
}

function readText(string $path): string
{
    return is_file($path) ? (string) file_get_contents($path) : '';
}

echo "DocumentArchive Dashboard Attachment Count Consistency V93 verification\n";
echo "=======================================================================\n";

foreach ($paths as $name => $path) {
    checkResult(is_file($path), "Required file exists: {$name}");
}

$dashboard = readText($paths['dashboard']);
$liveSync = readText($paths['live_sync']);
$view = readText($paths['view']);
$js = readText($paths['js']);

checkResult(
    str_contains($dashboard, "'attachments_total' => \$this->countActiveRows('document_attachments')"),
    'Dashboard counts active attachments only'
);

checkResult(
    substr_count($dashboard, "whereNull('document_attachments.deleted_at')") >= 2,
    'Dashboard ignores soft-deleted attachments in presence counts'
);

checkResult(
    str_contains($dashboard, "'key' => 'documents_without_attachments'"),
    'Dashboard alert exposes a live-sync key'
);

checkResult(
    str_contains($dashboard, "'hidden' => \$documentsWithoutAttachments <= 0"),
    'Dashboard alert visibility is count-aware'
);

checkResult(
    str_contains($liveSync, "'attachments_total' => \$this->countActiveRows('document_attachments')"),
    'Live sync counts active attachments only'
);

checkResult(
    str_contains($liveSync, "whereNull('document_attachments.deleted_at')"),
    'Live sync ignores soft-deleted attachments in presence counts'
);

checkResult(
    str_contains($view, 'data-live-sync-alert-count="documents_without_attachments"'),
    'Administrative alert count is connected to live sync'
);

checkResult(
    str_contains($view, 'data-live-sync-alert-empty'),
    'Administrative alert empty state is connected'
);

checkResult(
    str_contains($view, '.da-admin-alert-item[hidden]'),
    'Hidden administrative alerts remain hidden'
);

checkResult(
    str_contains($js, 'dashboard alert consistency'),
    'V93 JavaScript marker exists'
);

checkResult(
    str_contains($js, 'syncAdministrativeAlertEmptyState'),
    'Administrative alert empty state is synchronized'
);

checkResult(
    str_contains($js, 'data-live-sync-alert-count'),
    'Administrative alert count is updated by polling'
);

checkResult(
    str_contains($js, 'alert.hidden = !Number.isFinite(numericValue) || numericValue <= 0'),
    'Administrative alert visibility follows the current count'
);

echo "\n";

if ($failures > 0) {
    echo "Dashboard Attachment Count Consistency V93 verification FAILED.\n";
    echo "Failures: {$failures}\n";
    exit(1);
}

echo "Dashboard Attachment Count Consistency V93 verification PASSED.\n";
exit(0);

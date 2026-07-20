<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$checks = [
    'Service exists' => is_file(base_path('app/Services/LegacyArchiveImportService.php')),
    'Missing attachment analysis exists' => str_contains((string) @file_get_contents(base_path('app/Services/LegacyArchiveImportService.php')), "'attachment_repair_ready'"),
    'Repair method exists' => str_contains((string) @file_get_contents(base_path('app/Services/LegacyArchiveImportService.php')), 'private function repairExistingDocumentAttachment'),
    'Usable attachment check exists' => str_contains((string) @file_get_contents(base_path('app/Services/LegacyArchiveImportService.php')), 'private function documentHasUsableAttachment'),
    'Canonical sequence fix retained' => str_contains((string) @file_get_contents(base_path('app/Services/LegacyArchiveImportService.php')), 'private const LEGACY_REFERENCE_START_NUMBER = 251230000;'),
    'Status labels exist' => str_contains((string) @file_get_contents(base_path('app/Models/LegacyArchiveImportItem.php')), "'attachment_repaired' => 'تم استكمال المرفق'"),
    'Interface notice exists' => str_contains((string) @file_get_contents(resource_path('views/legacy_archive_import/index.blade.php')), 'legacy-archive-missing-attachments-v81-3:start'),
];

$failed = false;
foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if ($failed) {
    fwrite(STDERR, 'Legacy missing-attachment completion V81.3 check failed.' . PHP_EOL);
    exit(1);
}

echo 'Legacy missing-attachment completion V81.3 check passed.' . PHP_EOL;

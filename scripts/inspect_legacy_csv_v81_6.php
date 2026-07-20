<?php

declare(strict_types=1);

use App\Services\LegacyArchiveCsvReader;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$path = $argv[1] ?? null;
$reference = $argv[2] ?? '251230306';

if (! $path || ! is_file($path)) {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "php scripts/inspect_legacy_csv_v81_6.php \"C:\\path\\file.csv\" 251230306\n");
    exit(1);
}

$rows = app(LegacyArchiveCsvReader::class)->read($path, 100000);

echo "Legacy CSV inspection V81.6\n";
echo "===========================\n";
echo "Rows: " . count($rows) . "\n";
echo "Expected columns: " . count(LegacyArchiveCsvReader::COLUMNS) . "\n";

$record = null;

foreach ($rows as $row) {
    if ((string) ($row['ArchiveNum'] ?? '') === (string) $reference) {
        $record = $row;
        break;
    }
}

if (! $record) {
    fwrite(STDERR, "Reference {$reference} was not found.\n");
    exit(1);
}

echo "Reference: {$record['ArchiveNum']}\n";
echo "Legacy ID: {$record['ID']}\n";
echo "Date: {$record['ArchiveDate']}\n";
echo "Subject: {$record['ArchiveSubject']}\n";
echo "FilePath: {$record['FilePath']}\n";
echo "OriginalFilePath: {$record['OriginalFilePath']}\n";
echo "Original file exists: "
    . (is_file((string) $record['OriginalFilePath']) ? 'yes' : 'no')
    . "\n";
echo "Original file readable: "
    . (is_readable((string) $record['OriginalFilePath']) ? 'yes' : 'no')
    . "\n";

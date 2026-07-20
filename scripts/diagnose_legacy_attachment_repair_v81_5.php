<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\LegacyArchiveImportItem;
use App\Services\BookAttachmentSmartPathService;
use App\Services\LegacyArchiveImportService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$reference = '251230306';

foreach ($argv ?? [] as $argument) {
    if (str_starts_with($argument, '--reference=')) {
        $reference = trim(substr($argument, strlen('--reference=')));
    }
}

echo "Legacy attachment repair diagnosis V81.5\n";
echo "=======================================\n";
echo "Reference: {$reference}\n\n";

$document = Document::withTrashed()
    ->where('reference_number', $reference)
    ->first();

if (! $document) {
    fwrite(STDERR, "Document was not found in the new system.\n");
    exit(1);
}

echo "Document ID: {$document->id}\n";
echo "Legacy record ID: " . ($document->legacy_record_id ?? '-') . "\n";
echo "Deleted: " . ($document->trashed() ? 'yes' : 'no') . "\n";

$pathService = app(BookAttachmentSmartPathService::class);
$attachments = DocumentAttachment::where('document_id', $document->id)->get();

echo "Attachment records: {$attachments->count()}\n";

$usable = 0;
foreach ($attachments as $attachment) {
    $exists = $pathService->attachmentExists($attachment);
    $usable += $exists ? 1 : 0;

    echo sprintf(
        "  - Attachment #%d | %s | exists=%s\n",
        $attachment->id,
        $attachment->file_path ?: $attachment->file_name,
        $exists ? 'yes' : 'no'
    );
}

echo "Usable attachments: {$usable}\n\n";

$item = LegacyArchiveImportItem::query()
    ->where('reference_number', $reference)
    ->whereNotNull('payload')
    ->latest('id')
    ->first();

if (! $item || ! is_array($item->payload)) {
    fwrite(STDERR, "No import payload was found for this reference.\n");
    exit(1);
}

$service = app(LegacyArchiveImportService::class);
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('analyzeRow');
$method->setAccessible(true);

$analysis = $method->invoke($service, $item->payload, null);

echo "Analysis status: {$analysis['status']}\n";
echo "Message: {$analysis['message']}\n";
echo "File exists: " . ($analysis['file_exists'] ? 'yes' : 'no') . "\n";
echo "Resolved source: " . ($analysis['resolved_source_path'] ?: '-') . "\n";

$expected = $usable > 0 ? 'duplicate_legacy' : 'attachment_repair_ready';
echo "Expected status for current state: {$expected}\n";

if ($analysis['status'] !== $expected
    && ! in_array($analysis['status'], ['attachment_repair_missing_file', 'document_deleted'], true)) {
    fwrite(STDERR, "Diagnosis did not return the expected state.\n");
    exit(1);
}

echo "Diagnosis completed.\n";

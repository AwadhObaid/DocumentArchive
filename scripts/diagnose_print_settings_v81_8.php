<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\Setting;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$reference = $argv[1] ?? null;

$document = $reference
    ? Document::query()->where('reference_number', $reference)->first()
    : Document::query()->latest('id')->first();

if (! $document) {
    fwrite(STDERR, "No document was found.\n");
    exit(1);
}

echo "Print settings diagnosis V81.8\n";
echo "==============================\n";
echo "Document ID: {$document->id}\n";
echo "Reference: {$document->reference_number}\n\n";

echo "Legacy per-document snapshots:\n";
echo " - print_title: " . ($document->print_title ?? '<null>') . "\n";
echo " - print_top_mm: " . ($document->print_top_mm ?? '<null>') . "\n";
echo " - print_left_mm: " . ($document->print_left_mm ?? '<null>') . "\n\n";

echo "Live global values used by the print page:\n";
foreach ([
    'print_department_title',
    'print_top_mm',
    'print_left_mm',
    'print_font_size_pt',
    'print_department_font_size_pt',
    'print_label_width_mm',
    'print_colon_width_mm',
    'print_value_width_mm',
    'print_column_gap_mm',
    'print_title_gap_mm',
    'print_row_gap_mm',
] as $key) {
    echo " - {$key}: " . Setting::getValue($key, '<missing>') . "\n";
}

echo "\nV81.8 uses the live global values above for every print page.\n";

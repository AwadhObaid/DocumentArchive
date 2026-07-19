<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const LEGACY_START_NUMBER = 251230000;
const SOURCE_NAME = 'ESIS_TbArchive';

$args = $argv ?? [];
$execute = in_array('--execute', $args, true);
$confirmed = in_array('--confirm=FIX-LEGACY-SEQUENCES', $args, true);

function line(string $text = ''): void
{
    fwrite(STDOUT, $text . PHP_EOL);
}

function stopWith(string $message): never
{
    fwrite(STDERR, '[ERROR] ' . $message . PHP_EOL);
    exit(1);
}

if (! Schema::hasTable('documents')) {
    stopWith('documents table does not exist.');
}

$currentSetting = (string) Setting::getValue('reference_start_number', '');
$documents = DB::table('documents')
    ->where('legacy_source', SOURCE_NAME)
    ->whereNotNull('reference_year')
    ->whereRaw("reference_number REGEXP '^[0-9]+$'")
    ->orderBy('reference_year')
    ->orderBy('id')
    ->get(['id', 'reference_number', 'reference_year', 'reference_sequence']);

$plans = [];
$targetKeys = [];
$selectedIds = [];

foreach ($documents as $document) {
    $number = (int) $document->reference_number;
    $target = $number - LEGACY_START_NUMBER;

    if ($target < 0 || $target > 4294967295) {
        continue;
    }

    $key = ((int) $document->reference_year) . ':' . $target;
    if (isset($targetKeys[$key]) && $targetKeys[$key] !== (int) $document->id) {
        stopWith("Duplicate calculated target sequence {$key} for document IDs {$targetKeys[$key]} and {$document->id}.");
    }

    $targetKeys[$key] = (int) $document->id;
    $selectedIds[] = (int) $document->id;

    if ((int) $document->reference_sequence !== $target) {
        $plans[] = [
            'id' => (int) $document->id,
            'year' => (int) $document->reference_year,
            'reference_number' => (string) $document->reference_number,
            'old_sequence' => (int) $document->reference_sequence,
            'new_sequence' => $target,
        ];
    }
}

$years = array_values(array_unique(array_map(
    fn (array $plan): int => $plan['year'],
    $plans
)));

if ($plans !== []) {
    $otherDocuments = DB::table('documents')
        ->whereIn('reference_year', $years)
        ->when($selectedIds !== [], fn ($query) => $query->whereNotIn('id', $selectedIds))
        ->get(['id', 'reference_year', 'reference_sequence', 'reference_number']);

    foreach ($otherDocuments as $other) {
        $key = ((int) $other->reference_year) . ':' . ((int) $other->reference_sequence);
        if (isset($targetKeys[$key])) {
            stopWith(
                "Target sequence {$key} conflicts with existing non-legacy document ID {$other->id} " .
                "({$other->reference_number}). No changes were made."
            );
        }
    }
}

line('Legacy archive sequence repair V81.2');
line('====================================');
line('Current reference_start_number: ' . ($currentSetting !== '' ? $currentSetting : '(missing)'));
line('Required reference_start_number: ' . LEGACY_START_NUMBER);
line('Imported legacy documents scanned: ' . $documents->count());
line('Documents needing sequence repair: ' . count($plans));

foreach (array_slice($plans, 0, 30) as $plan) {
    line(sprintf(
        '  ID %-6d | %d | %s | %d -> %d',
        $plan['id'],
        $plan['year'],
        $plan['reference_number'],
        $plan['old_sequence'],
        $plan['new_sequence']
    ));
}

if (count($plans) > 30) {
    line('  ... and ' . (count($plans) - 30) . ' more.');
}

if (! $execute) {
    line('');
    line('PREVIEW ONLY: no data was changed.');
    line('Execute with:');
    line('php scripts/repair_legacy_archive_reference_sequences_v81_2.php --execute --confirm=FIX-LEGACY-SEQUENCES');
    exit(0);
}

if (! $confirmed) {
    stopWith('Missing exact confirmation: --confirm=FIX-LEGACY-SEQUENCES');
}

DB::transaction(function () use ($plans): void {
    Setting::setValue(
        'reference_start_number',
        (string) LEGACY_START_NUMBER,
        'references',
        'number',
        'رقم بداية الكتاب في بداية كل سنة'
    );

    foreach ($plans as $plan) {
        $temporary = 3000000000 + $plan['id'];
        if ($temporary > 4294967295) {
            throw new RuntimeException('Temporary sequence exceeded the unsigned integer limit.');
        }

        DB::table('documents')
            ->where('id', $plan['id'])
            ->update([
                'reference_sequence' => $temporary,
                'updated_at' => now(),
            ]);
    }

    foreach ($plans as $plan) {
        DB::table('documents')
            ->where('id', $plan['id'])
            ->update([
                'reference_sequence' => $plan['new_sequence'],
                'updated_at' => now(),
            ]);
    }

    if (Schema::hasTable('reference_counters')) {
        $allYears = DB::table('documents')
            ->whereNotNull('reference_year')
            ->distinct()
            ->pluck('reference_year');

        foreach ($allYears as $year) {
            $maxSequence = DB::table('documents')
                ->where('reference_year', (int) $year)
                ->max('reference_sequence');

            $maxSequence = is_null($maxSequence) ? -1 : (int) $maxSequence;
            $existing = DB::table('reference_counters')
                ->where('reference_year', (int) $year)
                ->first();

            DB::table('reference_counters')->updateOrInsert(
                ['reference_year' => (int) $year],
                [
                    'start_number' => LEGACY_START_NUMBER,
                    'last_sequence' => $maxSequence,
                    'last_reference_number' => $maxSequence >= 0
                        ? (string) (LEGACY_START_NUMBER + $maxSequence)
                        : null,
                    'created_at' => $existing->created_at ?? now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
});

line('');
line('Repair completed successfully.');
line('reference_start_number is now: ' . Setting::getValue('reference_start_number'));
line('Re-run the same legacy import file. Successful rows will be skipped and failed rows will be retried.');

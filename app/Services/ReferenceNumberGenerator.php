<?php

namespace App\Services;

use App\Models\Document;
use App\Models\ReferenceCounter;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReferenceNumberGenerator
{
    private const DEFAULT_START_NUMBER = 251230000;

    /**
     * Generate and reserve the next reference number for the year of the selected reference date.
     *
     * Important:
     * - Every Gregorian year has its own counter.
     * - The first number in every new year is the configured start number, normally 251230000.
     * - Existing documents are checked before reserving a number, so imported/restored data cannot make
     *   the counter reuse an already-issued reference number.
     */
    public static function generate(?string $date = null): array
    {
        return DB::transaction(function () use ($date) {
            $referenceDate = self::normalizeDate($date);
            $year = (int) $referenceDate->year;
            $configuredStartNumber = self::configuredStartNumber();

            DB::table('reference_counters')->insertOrIgnore([
                'reference_year' => $year,
                'start_number' => $configuredStartNumber,
                'last_sequence' => -1,
                'last_reference_number' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = ReferenceCounter::query()
                ->where('reference_year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $counterStartNumber = (int) ($counter->start_number ?: $configuredStartNumber);

            // If a year counter was created but no document has used it yet, allow the current setting to apply.
            if ((int) $counter->last_sequence < 0 && ! self::yearHasDocuments($year)) {
                $counterStartNumber = $configuredStartNumber;
                if ((int) $counter->start_number !== $configuredStartNumber) {
                    $counter->start_number = $configuredStartNumber;
                    $counter->save();
                }
            }

            $safeLastSequence = max(
                (int) $counter->last_sequence,
                self::maxDocumentSequence($year, $counterStartNumber)
            );

            $nextSequence = $safeLastSequence + 1;

            for ($attempt = 0; $attempt < 100; $attempt++) {
                $referenceNumber = (string) ($counterStartNumber + $nextSequence);

                $exists = Document::withTrashed()
                    ->where('reference_year', $year)
                    ->where('reference_number', $referenceNumber)
                    ->exists();

                if (! $exists) {
                    $counter->update([
                        'last_sequence' => $nextSequence,
                        'last_reference_number' => $referenceNumber,
                    ]);

                    return [
                        'reference_number' => $referenceNumber,
                        'reference_year' => $year,
                        'reference_sequence' => $nextSequence,
                        'reference_date' => $referenceDate->toDateString(),
                        'start_number' => $counterStartNumber,
                    ];
                }

                // Skip any occupied number and keep the counter moving forward.
                $counter->update([
                    'last_sequence' => $nextSequence,
                    'last_reference_number' => $referenceNumber,
                ]);

                $nextSequence++;
            }

            throw new \RuntimeException('تعذر توليد رقم كتاب غير مكرر بعد عدة محاولات.');
        });
    }

    /**
     * Preview the next reference number without reserving it.
     * This is for display only; the final number is reserved at save time.
     */
    public static function preview(?string $date = null): array
    {
        $referenceDate = self::normalizeDate($date);
        $year = (int) $referenceDate->year;
        $configuredStartNumber = self::configuredStartNumber();

        $counter = ReferenceCounter::query()
            ->where('reference_year', $year)
            ->first();

        $counterStartNumber = $counter
            ? (int) ($counter->start_number ?: $configuredStartNumber)
            : $configuredStartNumber;

        $safeLastSequence = max(
            $counter ? (int) $counter->last_sequence : -1,
            self::maxDocumentSequence($year, $counterStartNumber)
        );

        $nextSequence = $safeLastSequence + 1;
        $referenceNumber = (string) ($counterStartNumber + $nextSequence);

        return [
            'reference_number' => $referenceNumber,
            'reference_year' => $year,
            'reference_sequence' => $nextSequence,
            'reference_date' => $referenceDate->toDateString(),
            'start_number' => $counterStartNumber,
            'reserved' => false,
        ];
    }

    private static function normalizeDate(?string $date = null): Carbon
    {
        return $date ? Carbon::parse($date) : now();
    }

    private static function configuredStartNumber(): int
    {
        $value = (int) Setting::getValue('reference_start_number', self::DEFAULT_START_NUMBER);

        return $value > 0 ? $value : self::DEFAULT_START_NUMBER;
    }

    private static function yearHasDocuments(int $year): bool
    {
        return Document::withTrashed()
            ->where('reference_year', $year)
            ->exists();
    }

    /**
     * Determine the highest already-used sequence for the year.
     *
     * We check both reference_sequence and numeric reference_number to protect the counter after
     * importing/restoring records where reference_sequence may be stale or zero.
     */
    private static function maxDocumentSequence(int $year, int $startNumber): int
    {
        $maxStoredSequence = Document::withTrashed()
            ->where('reference_year', $year)
            ->max('reference_sequence');

        $maxSequence = is_null($maxStoredSequence) ? -1 : (int) $maxStoredSequence;

        Document::withTrashed()
            ->where('reference_year', $year)
            ->select('reference_number')
            ->orderBy('id')
            ->chunk(500, function ($documents) use (&$maxSequence, $startNumber) {
                foreach ($documents as $document) {
                    $referenceNumber = trim((string) $document->reference_number);

                    if ($referenceNumber !== '' && ctype_digit($referenceNumber)) {
                        $sequence = ((int) $referenceNumber) - $startNumber;

                        if ($sequence >= 0) {
                            $maxSequence = max($maxSequence, $sequence);
                        }
                    }
                }
            });

        return $maxSequence;
    }
}
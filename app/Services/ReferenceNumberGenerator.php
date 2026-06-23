<?php

namespace App\Services;

use App\Models\ReferenceCounter;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReferenceNumberGenerator
{
    public static function generate(?string $date = null): array
    {
        return DB::transaction(function () use ($date) {
            $referenceDate = $date ? Carbon::parse($date) : now();

            $year = (int) $referenceDate->year;

            $startNumber = (int) Setting::getValue(
                'reference_start_number',
                251230000
            );

            DB::table('reference_counters')->insertOrIgnore([
                'reference_year' => $year,
                'start_number' => $startNumber,
                'last_sequence' => -1,
                'last_reference_number' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = ReferenceCounter::query()
                ->where('reference_year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $nextSequence = ((int) $counter->last_sequence) + 1;

            $referenceNumber = (string) (((int) $counter->start_number) + $nextSequence);

            $counter->update([
                'last_sequence' => $nextSequence,
                'last_reference_number' => $referenceNumber,
            ]);

            return [
                'reference_number' => $referenceNumber,
                'reference_year' => $year,
                'reference_sequence' => $nextSequence,
                'reference_date' => $referenceDate->toDateString(),
            ];
        });
    }
}
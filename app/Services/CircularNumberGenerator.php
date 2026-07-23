<?php

namespace App\Services;

use App\Models\Circular;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CircularNumberGenerator
{
    private const TYPE_OFFSET = 10000;
    private const MAX_SEQUENCE = 9999;

    public static function preview(?CarbonInterface $date = null): array
    {
        $year = (int) ($date?->format('Y') ?: now()->format('Y'));
        $startNumber = self::startNumber($year);
        $lastSequence = (int) (
            DB::table('circular_counters')
                ->where('year', $year)
                ->value('last_sequence') ?? -1
        );

        $sequence = max(0, $lastSequence + 1);
        $sequence = self::firstAvailableSequence($year, $sequence);

        return self::payload($year, $sequence, $startNumber);
    }

    public static function generate(?CarbonInterface $date = null): array
    {
        return DB::transaction(function () use ($date) {
            $year = (int) ($date?->format('Y') ?: now()->format('Y'));
            $startNumber = self::startNumber($year);

            $counter = DB::table('circular_counters')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                DB::table('circular_counters')->insert([
                    'year' => $year,
                    'start_number' => $startNumber,
                    'last_sequence' => -1,
                    'last_circular_number' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $counter = DB::table('circular_counters')
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();
            }

            $sequence = max(0, ((int) $counter->last_sequence) + 1);
            $sequence = self::firstAvailableSequence($year, $sequence);

            if ($sequence > self::MAX_SEQUENCE) {
                throw new \RuntimeException(
                    'تم استنفاد تسلسل الترقيم للسنة ' . $year . '.'
                );
            }

            $payload = self::payload($year, $sequence, $startNumber);

            DB::table('circular_counters')
                ->where('year', $year)
                ->update([
                    'start_number' => $startNumber,
                    'last_sequence' => $sequence,
                    'last_circular_number' => $payload['circular_number'],
                    'updated_at' => now(),
                ]);

            return $payload;
        }, 5);
    }

    private static function firstAvailableSequence(
        int $year,
        int $sequence
    ): int {
        while ($sequence <= self::MAX_SEQUENCE) {
            $number = (string) (self::startNumber($year) + $sequence);

            $exists = Circular::withTrashed()
                ->where('circular_year', $year)
                ->where('circular_number', $number)
                ->exists();

            if (! $exists) {
                return $sequence;
            }

            $sequence++;
        }

        return $sequence;
    }

    private static function payload(
        int $year,
        int $sequence,
        int $startNumber
    ): array {
        return [
            'circular_number' => (string) ($startNumber + $sequence),
            'circular_sequence' => $sequence,
            'circular_year' => $year,
            'start_number' => $startNumber,
        ];
    }

    private static function startNumber(int $year): int
    {
        return (($year % 100) * 100000) + self::TYPE_OFFSET;
    }
}

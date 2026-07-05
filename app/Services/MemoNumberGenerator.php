<?php

namespace App\Services;

use App\Models\Memo;
use App\Models\MemoCounter;
use Illuminate\Support\Facades\DB;

class MemoNumberGenerator
{
    public const DEFAULT_START_NUMBER = 2600001;
    private const COUNTER_KEY = 'default';

    public static function generate(): array
    {
        return DB::transaction(function () {
            self::ensureCounterExists();

            $counter = MemoCounter::query()
                ->where('counter_key', self::COUNTER_KEY)
                ->lockForUpdate()
                ->firstOrFail();

            $startNumber = (int) ($counter->start_number ?: self::DEFAULT_START_NUMBER);
            $safeLastSequence = max(
                (int) $counter->last_sequence,
                self::maxMemoSequence($startNumber)
            );

            $nextSequence = $safeLastSequence + 1;

            for ($attempt = 0; $attempt < 100; $attempt++) {
                $memoNumber = (string) ($startNumber + $nextSequence);

                $exists = Memo::withTrashed()
                    ->where('memo_number', $memoNumber)
                    ->exists();

                if (! $exists) {
                    $counter->update([
                        'last_sequence' => $nextSequence,
                        'last_memo_number' => $memoNumber,
                    ]);

                    return [
                        'memo_number' => $memoNumber,
                        'memo_sequence' => $nextSequence,
                        'start_number' => $startNumber,
                    ];
                }

                $counter->update([
                    'last_sequence' => $nextSequence,
                    'last_memo_number' => $memoNumber,
                ]);

                $nextSequence++;
            }

            throw new \RuntimeException('تعذر توليد رقم مذكرة غير مكرر بعد عدة محاولات.');
        });
    }

    public static function preview(): array
    {
        self::ensureCounterExists();

        $counter = MemoCounter::query()
            ->where('counter_key', self::COUNTER_KEY)
            ->first();

        $startNumber = $counter ? (int) ($counter->start_number ?: self::DEFAULT_START_NUMBER) : self::DEFAULT_START_NUMBER;

        $safeLastSequence = max(
            $counter ? (int) $counter->last_sequence : -1,
            self::maxMemoSequence($startNumber)
        );

        $nextSequence = $safeLastSequence + 1;

        return [
            'memo_number' => (string) ($startNumber + $nextSequence),
            'memo_sequence' => $nextSequence,
            'start_number' => $startNumber,
            'reserved' => false,
        ];
    }

    public static function reset(int $startNumber = self::DEFAULT_START_NUMBER): void
    {
        self::ensureCounterExists($startNumber);

        MemoCounter::query()
            ->where('counter_key', self::COUNTER_KEY)
            ->update([
                'start_number' => $startNumber,
                'last_sequence' => -1,
                'last_memo_number' => null,
                'updated_at' => now(),
            ]);
    }

    private static function ensureCounterExists(?int $startNumber = null): void
    {
        DB::table('memo_counters')->insertOrIgnore([
            'counter_key' => self::COUNTER_KEY,
            'start_number' => $startNumber ?: self::DEFAULT_START_NUMBER,
            'last_sequence' => -1,
            'last_memo_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function maxMemoSequence(int $startNumber): int
    {
        $maxStoredSequence = Memo::withTrashed()->max('memo_sequence');
        $maxSequence = is_null($maxStoredSequence) ? -1 : (int) $maxStoredSequence;

        Memo::withTrashed()
            ->select('memo_number')
            ->orderBy('id')
            ->chunk(500, function ($memos) use (&$maxSequence, $startNumber) {
                foreach ($memos as $memo) {
                    $memoNumber = trim((string) $memo->memo_number);

                    if ($memoNumber !== '' && ctype_digit($memoNumber)) {
                        $sequence = ((int) $memoNumber) - $startNumber;
                        if ($sequence >= 0) {
                            $maxSequence = max($maxSequence, $sequence);
                        }
                    }
                }
            });

        return $maxSequence;
    }
}

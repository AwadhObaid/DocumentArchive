<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QrPrintLayoutSettings
{
    public const TABLE = 'qr_print_settings';

    public const DEFAULTS = [
        'qr_enabled' => '1',
        'qr_x_mm' => '82',
        'qr_y_mm' => '50',
        'qr_size_mm' => '20',
        'qr_label_enabled' => '1',
        'qr_label_text' => 'رمز الوصول الإلكتروني',
        'qr_label_font_size_mm' => '2.6',
        'qr_card_padding_mm' => '2',
        'qr_background_enabled' => '1',
        'qr_show_border' => '1',
    ];

    public static function all(): array
    {
        $settings = self::DEFAULTS;

        try {
            if (Schema::hasTable(self::TABLE)) {
                $rows = DB::table(self::TABLE)->pluck('value', 'key')->toArray();
                foreach ($rows as $key => $value) {
                    if (array_key_exists($key, $settings)) {
                        $settings[$key] = (string) $value;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Keep defaults if database is not ready.
        }

        return $settings;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = self::all();
        return $settings[$key] ?? $default;
    }

    public static function upsertMany(array $values): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach ($values as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) {
                continue;
            }

            DB::table(self::TABLE)->updateOrInsert(
                ['key' => $key],
                [
                    'value' => (string) $value,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public static function cssVariables(): string
    {
        $s = self::all();

        $enabled = ((string) ($s['qr_enabled'] ?? '1')) === '1' ? 'block' : 'none';
        $background = ((string) ($s['qr_background_enabled'] ?? '1')) === '1' ? '#ffffff' : 'transparent';
        $border = ((string) ($s['qr_show_border'] ?? '1')) === '1' ? '1px solid #cbd5e1' : '0';

        return implode("\n", [
            '--da-qr-display: ' . $enabled . ';',
            '--da-qr-x: ' . self::num($s['qr_x_mm'] ?? '82', 82) . 'mm;',
            '--da-qr-y: ' . self::num($s['qr_y_mm'] ?? '50', 50) . 'mm;',
            '--da-qr-size: ' . self::num($s['qr_size_mm'] ?? '20', 20) . 'mm;',
            '--da-qr-padding: ' . self::num($s['qr_card_padding_mm'] ?? '2', 2) . 'mm;',
            '--da-qr-label-font-size: ' . self::num($s['qr_label_font_size_mm'] ?? '2.6', 2.6) . 'mm;',
            '--da-qr-background: ' . $background . ';',
            '--da-qr-border: ' . $border . ';',
        ]);
    }

    public static function num(mixed $value, float $fallback): float
    {
        return is_numeric($value) ? (float) $value : $fallback;
    }
}
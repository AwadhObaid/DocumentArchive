<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'description',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        try {
            $value = static::query()
                ->where('key', $key)
                ->value('value');

            return $value ?? $default;
        } catch (Throwable $exception) {
            return $default;
        }
    }

    public static function getString(string $key, string $default = ''): string
    {
        $value = static::getValue($key, $default);
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = static::getValue($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function getFloat(string $key, float $default = 0.0): float
    {
        $value = static::getValue($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    public static function setValue(
        string $key,
        mixed $value,
        string $group = 'general',
        string $type = 'text',
        ?string $description = null
    ): self {
        return static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => is_null($value) ? null : (string) $value,
                'group' => $group,
                'type' => $type,
                'description' => $description,
            ]
        );
    }
}
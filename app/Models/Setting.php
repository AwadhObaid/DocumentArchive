<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        $value = static::query()
            ->where('key', $key)
            ->value('value');

        return $value ?? $default;
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
                'value' => (string) $value,
                'group' => $group,
                'type' => $type,
                'description' => $description,
            ]
        );
    }
}
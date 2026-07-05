<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoCounter extends Model
{
    protected $fillable = [
        'counter_key',
        'start_number',
        'last_sequence',
        'last_memo_number',
    ];

    protected function casts(): array
    {
        return [
            'start_number' => 'integer',
            'last_sequence' => 'integer',
        ];
    }
}

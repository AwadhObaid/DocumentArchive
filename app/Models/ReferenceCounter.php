<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenceCounter extends Model
{
    protected $fillable = [
        'reference_year',
        'start_number',
        'last_sequence',
        'last_reference_number',
    ];

    protected function casts(): array
    {
        return [
            'reference_year' => 'integer',
            'start_number' => 'integer',
            'last_sequence' => 'integer',
        ];
    }
}

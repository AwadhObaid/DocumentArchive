<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Memo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'memo_number',
        'memo_sequence',
        'memo_date',
        'subject',
        'description',
        'department_id',
        'sender',
        'receiver',
        'status',
        'notes',
        'search_text',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'memo_sequence' => 'integer',
            'memo_date' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(MemoAttachment::class);
    }

    public function mainAttachment()
    {
        return $this->hasOne(MemoAttachment::class)->where('is_main', true);
    }

    public function internalMessages()
    {
        return $this->hasMany(InternalMessage::class);
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'archived' => 'مؤرشفة',
            'cancelled' => 'ملغاة',
            default => 'نشطة',
        };
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->memo_date ? $this->memo_date->format('d/m/Y') : '';
    }
}

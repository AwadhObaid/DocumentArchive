<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttachmentRelocationRun extends Model
{
    protected $fillable = [
        'mode',
        'status',
        'delete_original',
        'total_items',
        'ready_items',
        'moved_items',
        'copied_items',
        'skipped_items',
        'missing_items',
        'failed_items',
        'created_by',
        'started_at',
        'finished_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'delete_original' => 'boolean',
            'total_items' => 'integer',
            'ready_items' => 'integer',
            'moved_items' => 'integer',
            'copied_items' => 'integer',
            'skipped_items' => 'integer',
            'missing_items' => 'integer',
            'failed_items' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(AttachmentRelocationItem::class, 'run_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getModeNameAttribute(): string
    {
        return $this->mode === 'execute' ? 'تنفيذ فعلي' : 'فحص فقط';
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'running' => 'قيد التنفيذ',
            'finished' => 'مكتمل',
            'failed' => 'فشل',
            default => 'جديد',
        };
    }
}

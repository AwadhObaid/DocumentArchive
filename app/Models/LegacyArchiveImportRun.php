<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyArchiveImportRun extends Model
{
    protected $fillable = [
        'source_name',
        'source_file_path',
        'original_filename',
        'mode',
        'status',
        'total_rows',
        'ready_rows',
        'imported_rows',
        'duplicate_rows',
        'missing_file_rows',
        'failed_rows',
        'skipped_rows',
        'copied_files',
        'copied_bytes',
        'source_root_override',
        'import_missing_without_file',
        'created_by',
        'started_at',
        'finished_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'ready_rows' => 'integer',
            'imported_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'missing_file_rows' => 'integer',
            'failed_rows' => 'integer',
            'skipped_rows' => 'integer',
            'copied_files' => 'integer',
            'copied_bytes' => 'integer',
            'import_missing_without_file' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'notes' => 'array',
        ];
    }

    public function items()
    {
        return $this->hasMany(LegacyArchiveImportItem::class, 'run_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getModeLabelAttribute(): string
    {
        return $this->mode === 'execute' ? 'استيراد فعلي' : 'فحص تجريبي';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'processing' => 'قيد التنفيذ',
            'completed' => 'مكتمل',
            'completed_with_errors' => 'مكتمل مع أخطاء',
            'failed' => 'فشل',
            default => 'جديد',
        };
    }
}

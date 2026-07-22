<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyMemoImportRun extends Model
{
    protected $fillable = [
        'source_root',
        'recursive',
        'status',
        'total_files',
        'supported_files',
        'ready_files',
        'needs_review_files',
        'duplicate_system_files',
        'duplicate_scan_files',
        'unreadable_files',
        'unsupported_files',
        'hashed_files',
        'bytes_total',
        'created_by',
        'started_at',
        'finished_at',
        'notes',
        'import_status',
        'import_total',
        'imported_files',
        'import_failed_files',
        'import_skipped_files',
        'imported_by',
        'import_started_at',
        'import_finished_at',
    ];

    protected function casts(): array
    {
        return [
            'recursive' => 'boolean',
            'total_files' => 'integer',
            'supported_files' => 'integer',
            'ready_files' => 'integer',
            'needs_review_files' => 'integer',
            'duplicate_system_files' => 'integer',
            'duplicate_scan_files' => 'integer',
            'unreadable_files' => 'integer',
            'unsupported_files' => 'integer',
            'hashed_files' => 'integer',
            'bytes_total' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'notes' => 'array',
            'import_total' => 'integer',
            'imported_files' => 'integer',
            'import_failed_files' => 'integer',
            'import_skipped_files' => 'integer',
            'import_started_at' => 'datetime',
            'import_finished_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(LegacyMemoImportItem::class, 'run_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function getImportStatusLabelAttribute(): string
    {
        return match ($this->import_status ?: 'not_started') {
            'processing' => 'جارٍ الاستيراد',
            'partial' => 'مستورد جزئيًا',
            'completed' => 'اكتمل الاستيراد',
            'completed_with_errors' => 'اكتمل مع أخطاء',
            default => 'لم يبدأ الاستيراد',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'processing' => 'قيد الفحص',
            'completed' => 'مكتمل',
            'completed_with_errors' => 'مكتمل مع ملاحظات',
            'failed' => 'فشل',
            default => 'جديد',
        };
    }
}

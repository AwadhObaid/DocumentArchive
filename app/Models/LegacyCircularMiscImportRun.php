<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyCircularMiscImportRun extends Model
{
    protected $table = 'legacy_circular_misc_import_runs';

    protected $fillable = [
        'name',
        'status',
        'total_files',
        'ready_count',
        'needs_review_count',
        'existing_count',
        'duplicate_count',
        'unreadable_count',
        'unsupported_count',
        'total_size',
        'error_message',
        'created_by',
        'started_at',
        'completed_at',
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
            'total_files' => 'integer',
            'ready_count' => 'integer',
            'needs_review_count' => 'integer',
            'existing_count' => 'integer',
            'duplicate_count' => 'integer',
            'unreadable_count' => 'integer',
            'unsupported_count' => 'integer',
            'total_size' => 'integer',
            'import_total' => 'integer',
            'imported_files' => 'integer',
            'import_failed_files' => 'integer',
            'import_skipped_files' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'import_started_at' => 'datetime',
            'import_finished_at' => 'datetime',
        ];
    }

    public function sources()
    {
        return $this->hasMany(
            LegacyCircularMiscImportSource::class,
            'run_id'
        )->orderBy('sort_order')->orderBy('id');
    }

    public function items()
    {
        return $this->hasMany(
            LegacyCircularMiscImportItem::class,
            'run_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'مكتمل',
            'failed' => 'فشل',
            default => 'قيد الفحص',
        };
    }

    public function getImportStatusNameAttribute(): string
    {
        return match ($this->import_status) {
            'processing' => 'جارٍ الاستيراد',
            'partial' => 'مستورد جزئيًا',
            'completed' => 'مكتمل',
            'completed_with_errors' => 'مكتمل مع أخطاء',
            default => 'لم يبدأ',
        };
    }
}

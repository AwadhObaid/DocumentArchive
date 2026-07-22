<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyMemoImportItem extends Model
{
    protected $fillable = [
        'run_id',
        'memo_id',
        'imported_attachment_id',
        'status',
        'message',
        'source_path',
        'relative_path',
        'original_name',
        'extension',
        'mime_type',
        'file_size',
        'modified_at',
        'sha256',
        'proposed_memo_number',
        'proposed_memo_sequence',
        'proposed_memo_date',
        'proposed_subject',
        'proposed_sender',
        'proposed_receiver',
        'proposed_department_id',
        'payload',
        'import_attempts',
        'imported_by',
        'imported_at',
        'source_verified_at',
        'copied_file_size',
        'copied_sha256',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'modified_at' => 'datetime',
            'proposed_memo_sequence' => 'integer',
            'proposed_memo_date' => 'date',
            'payload' => 'array',
            'import_attempts' => 'integer',
            'imported_at' => 'datetime',
            'source_verified_at' => 'datetime',
            'copied_file_size' => 'integer',
        ];
    }

    public function run()
    {
        return $this->belongsTo(LegacyMemoImportRun::class, 'run_id');
    }

    public function memo()
    {
        return $this->belongsTo(Memo::class);
    }

    public function importedAttachment()
    {
        return $this->belongsTo(
            MemoAttachment::class,
            'imported_attachment_id'
        );
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function proposedDepartment()
    {
        return $this->belongsTo(Department::class, 'proposed_department_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'ready' => 'جاهزة للمراجعة',
            'needs_review' => 'تحتاج استكمال البيانات',
            'duplicate_system' => 'موجودة في النظام',
            'duplicate_scan' => 'مكررة داخل المجلد',
            'unreadable' => 'غير قابلة للقراءة',
            'unsupported' => 'امتداد غير مدعوم',
            'failed' => 'فشل الفحص',
            'imported' => 'تم الاستيراد',
            'import_failed' => 'فشل الاستيراد',
            default => $this->status ?: '-',
        };
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            'ready' => 'success',
            'needs_review' => 'warning',
            'duplicate_system', 'duplicate_scan', 'unsupported' => 'neutral',
            'unreadable', 'failed', 'import_failed' => 'danger',
            'imported' => 'success',
            default => 'neutral',
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyArchiveImportItem extends Model
{
    protected $fillable = [
        'run_id',
        'source_record_id',
        'document_id',
        'reference_number',
        'status',
        'message',
        'source_path',
        'resolved_source_path',
        'target_path',
        'file_exists',
        'file_copied',
        'source_size',
        'target_size',
        'source_sha256',
        'target_sha256',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'file_exists' => 'boolean',
            'file_copied' => 'boolean',
            'source_size' => 'integer',
            'target_size' => 'integer',
            'payload' => 'array',
        ];
    }

    public function run()
    {
        return $this->belongsTo(LegacyArchiveImportRun::class, 'run_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'ready' => 'جاهز للاستيراد',
            'imported' => 'تم الاستيراد',
            'imported_missing_file' => 'تم استيراد البيانات والمرفق مفقود',
            'attachment_repair_ready' => 'جاهز لاستكمال المرفق',
            'attachment_repaired' => 'تم استكمال المرفق',
            'attachment_repair_missing_file' => 'مصدر المرفق ما زال مفقودًا',
            'attachment_already_present' => 'المرفق موجود مسبقًا',
            'document_deleted' => 'الكتاب في سلة المحذوفات',
            'duplicate_legacy' => 'مستورد سابقًا',
            'duplicate_reference' => 'رقم الكتاب موجود',
            'missing_file' => 'الملف مفقود',
            'invalid' => 'بيانات غير صالحة',
            'failed' => 'فشل',
            'skipped' => 'تم التجاوز',
            default => $this->status ?: '-',
        };
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            'imported', 'ready', 'attachment_repaired', 'attachment_repair_ready' => 'success',
            'imported_missing_file', 'missing_file', 'attachment_repair_missing_file' => 'warning',
            'duplicate_legacy', 'duplicate_reference', 'attachment_already_present', 'document_deleted', 'skipped' => 'neutral',
            'invalid', 'failed' => 'danger',
            default => 'neutral',
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttachmentRelocationItem extends Model
{
    protected $fillable = [
        'run_id',
        'document_attachment_id',
        'document_id',
        'reference_number',
        'original_path',
        'original_disk',
        'original_root_path',
        'target_path',
        'target_disk',
        'target_root_path',
        'status',
        'message',
        'source_exists',
        'target_exists',
        'file_size',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'source_exists' => 'boolean',
            'target_exists' => 'boolean',
            'file_size' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function run()
    {
        return $this->belongsTo(AttachmentRelocationRun::class, 'run_id');
    }

    public function attachment()
    {
        return $this->belongsTo(DocumentAttachment::class, 'document_attachment_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'ready' => 'جاهز للنقل',
            'copied' => 'تم النسخ',
            'moved' => 'تم النقل',
            'already_sorted' => 'مرتب مسبقًا',
            'missing_file' => 'الملف مفقود',
            'missing_document' => 'الكتاب غير موجود',
            'missing_classification' => 'تصنيف ناقص',
            'failed' => 'فشل',
            default => $this->status ?: '-',
        };
    }
}

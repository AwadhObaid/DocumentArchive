<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\BookAttachmentSmartPathService;

class DocumentAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'document_id',
        'attachment_type',
        'version_no',
        'is_main',
        'original_name',
        'file_name',
        'file_path',
        'disk',
        'storage_root_path',
        'classification_company_name',
        'classification_operation_name',
        'classification_year',
        'classification_folder',
        'extension',
        'mime_type',
        'file_size',
        'ocr_status',
        'ocr_text',
        'ocr_error',
        'uploaded_by',
        'deleted_by',
        'deletion_reason',
        'replaces_attachment_id',
        'replaced_by_attachment_id',
        'replacement_reason',
        'replaced_at',
    ];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'is_main' => 'boolean',
            'file_size' => 'integer',
            'classification_year' => 'integer',
            'replaced_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function replacesAttachment()
    {
        return $this->belongsTo(self::class, 'replaces_attachment_id')->withTrashed();
    }

    public function replacedByAttachment()
    {
        return $this->belongsTo(self::class, 'replaced_by_attachment_id')->withTrashed();
    }

    public function sharedLinkItems()
    {
        return $this->hasMany(SharedAttachmentLinkItem::class);
    }

    public function getFileSizeForHumansAttribute(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' Bytes';
    }

    public function textIndex()
    {
        return $this->hasOne(AttachmentTextIndex::class, 'attachment_id')
            ->where('source_type', 'document');
    }

    public function existsOnDisk(): bool
    {
        return app(BookAttachmentSmartPathService::class)->attachmentExists($this);
    }
}

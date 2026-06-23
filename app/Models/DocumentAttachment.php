<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DocumentAttachment extends Model
{
    protected $fillable = [
        'document_id',
        'attachment_type',
        'version_no',
        'is_main',

        'original_name',
        'file_name',
        'file_path',
        'disk',

        'extension',
        'mime_type',
        'file_size',

        'ocr_status',
        'ocr_text',
        'ocr_error',

        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'is_main' => 'boolean',
            'file_size' => 'integer',
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

    public function existsOnDisk(): bool
    {
        return Storage::disk($this->disk)->exists($this->file_path);
    }
}
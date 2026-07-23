<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CircularAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'circular_id',
        'version_no',
        'is_main',
        'original_name',
        'file_name',
        'file_path',
        'disk',
        'extension',
        'mime_type',
        'file_size',
        'sha256',
        'source_path',
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
            'replaced_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function circular()
    {
        return $this->belongsTo(Circular::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk($this->disk ?: 'local')->exists($this->file_path);
    }

    public function getFileSizeForHumansAttribute(): string
    {
        return self::formatBytes((int) $this->file_size);
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' Bytes';
    }
}

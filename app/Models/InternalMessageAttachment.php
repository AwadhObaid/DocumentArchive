<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class InternalMessageAttachment extends Model
{
    protected $fillable = [
        'internal_message_id',
        'original_name',
        'file_name',
        'file_path',
        'disk',
        'extension',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function message()
    {
        return $this->belongsTo(InternalMessage::class, 'internal_message_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function existsOnDisk(): bool
    {
        try {
            return $this->file_path
                ? Storage::disk($this->disk ?: 'local')->exists($this->file_path)
                : false;
        } catch (\Throwable) {
            return false;
        }
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MemoAttachment extends Model
{
    protected $fillable = [
        'memo_id',
        'version_no',
        'is_main',
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
            'version_no' => 'integer',
            'is_main' => 'boolean',
            'file_size' => 'integer',
        ];
    }

    public function memo()
    {
        return $this->belongsTo(Memo::class);
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

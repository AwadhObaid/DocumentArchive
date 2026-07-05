<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharedAttachmentLinkItem extends Model
{
    protected $fillable = [
        'shared_attachment_link_id',
        'document_attachment_id',
        'memo_attachment_id',
        'download_count',
        'last_downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'download_count' => 'integer',
            'last_downloaded_at' => 'datetime',
        ];
    }

    public function link()
    {
        return $this->belongsTo(SharedAttachmentLink::class, 'shared_attachment_link_id');
    }

    public function attachment()
    {
        return $this->belongsTo(DocumentAttachment::class, 'document_attachment_id');
    }

    public function memoAttachment()
    {
        return $this->belongsTo(MemoAttachment::class, 'memo_attachment_id');
    }

    public function getResolvedAttachmentAttribute()
    {
        return $this->attachment ?: $this->memoAttachment;
    }
}

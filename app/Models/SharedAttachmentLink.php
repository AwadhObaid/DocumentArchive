<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharedAttachmentLink extends Model
{
    protected $fillable = [
        'document_id',
        'memo_id',
        'created_by',
        'token',
        'title',
        'notes',
        'password_hash',
        'expires_at',
        'max_downloads',
        'download_count',
        'view_count',
        'last_viewed_at',
        'last_downloaded_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
            'max_downloads' => 'integer',
            'download_count' => 'integer',
            'view_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function memo()
    {
        return $this->belongsTo(Memo::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(SharedAttachmentLinkItem::class);
    }

    public function attachments()
    {
        return $this->belongsToMany(
            DocumentAttachment::class,
            'shared_attachment_link_items',
            'shared_attachment_link_id',
            'document_attachment_id'
        )->withTimestamps();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function getIsDownloadLimitReachedAttribute(): bool
    {
        return $this->max_downloads !== null
            && (int) $this->download_count >= (int) $this->max_downloads;
    }

    public function getIsAvailableAttribute(): bool
    {
        return (bool) $this->is_active
            && !$this->is_expired
            && !$this->is_download_limit_reached;
    }

    public function getRequiresPasswordAttribute(): bool
    {
        return !empty($this->password_hash);
    }

    public function getPublicUrlAttribute(): string
    {
        return route('shared-attachments.public.show', $this->token);
    }

    public function getStatusNameAttribute(): string
    {
        if (!$this->is_active) {
            return 'معطل';
        }

        if ($this->is_expired) {
            return 'منتهي';
        }

        if ($this->is_download_limit_reached) {
            return 'وصل حد التحميل';
        }

        return 'نشط';
    }

    public function getStatusClassAttribute(): string
    {
        if (!$this->is_active) {
            return 'danger';
        }

        if ($this->is_expired || $this->is_download_limit_reached) {
            return 'warning';
        }

        return 'success';
    }

    public function getRemainingDownloadsAttribute(): ?int
    {
        if ($this->max_downloads === null) {
            return null;
        }

        return max(0, (int) $this->max_downloads - (int) $this->download_count);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'unique_key',
        'type',
        'title',
        'message',
        'body',
        'url',
        'link',
        'source',
        'payload',
        'data',
        'read_at',
        'dismissed_at',
        'hidden_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'data' => 'array',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'hidden_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisible($query)
    {
        try {
            if (Schema::hasColumn($this->getTable(), 'dismissed_at')) {
                $query->whereNull('dismissed_at');
            }
            if (Schema::hasColumn($this->getTable(), 'hidden_at')) {
                $query->whereNull('hidden_at');
            }
        } catch (Throwable $e) {
            // لا نكسر صفحة الإشعارات إذا تعذر فحص الأعمدة.
        }

        return $query;
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at')->visible();
    }

    public function getBodyTextAttribute(): string
    {
        return (string) ($this->body ?? $this->message ?? '');
    }

    public function getBodyAttribute($value): ?string
    {
        return $value ?? ($this->attributes['message'] ?? null);
    }

    public function getLinkAttribute($value): ?string
    {
        return $value ?? ($this->attributes['url'] ?? null);
    }
}
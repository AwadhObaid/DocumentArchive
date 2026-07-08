<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'receiver_id',
        'body',
        'document_id',
        'memo_id',
        'read_at',
        'sender_deleted_at',
        'receiver_deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'sender_deleted_at' => 'datetime',
            'receiver_deleted_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(InternalChatConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function memo(): BelongsTo
    {
        return $this->belongsTo(Memo::class, 'memo_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(InternalChatAttachment::class, 'internal_chat_message_id');
    }

    public function scopeBetween($query, int $firstUserId, int $secondUserId)
    {
        return $query->where(function ($outer) use ($firstUserId, $secondUserId) {
            $outer->where(function ($q) use ($firstUserId, $secondUserId) {
                $q->where('sender_id', $firstUserId)
                    ->where('receiver_id', $secondUserId)
                    ->whereNull('sender_deleted_at');
            })->orWhere(function ($q) use ($firstUserId, $secondUserId) {
                $q->where('sender_id', $secondUserId)
                    ->where('receiver_id', $firstUserId)
                    ->whereNull('receiver_deleted_at');
            });
        });
    }

    public function scopeUnreadFor($query, int $userId)
    {
        return $query->where('receiver_id', $userId)
            ->whereNull('read_at')
            ->whereNull('receiver_deleted_at');
    }

    public function isOutgoingFor(int $userId): bool
    {
        return (int) $this->sender_id === $userId;
    }
}

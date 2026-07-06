<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class InternalMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'subject',
        'body',
        'document_id',
        'memo_id',
        'read_at',
        'sender_archived_at',
        'receiver_archived_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'sender_archived_at' => 'datetime',
            'receiver_archived_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function memo()
    {
        return $this->belongsTo(Memo::class);
    }

    public function attachments()
    {
        return $this->hasMany(InternalMessageAttachment::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $builder) use ($userId) {
            $builder->where('sender_id', $userId)
                ->orWhere('receiver_id', $userId);
        });
    }

    public function scopeInboxFor(Builder $query, int $userId): Builder
    {
        return $query->where('receiver_id', $userId)->whereNull('receiver_archived_at');
    }

    public function scopeSentFor(Builder $query, int $userId): Builder
    {
        return $query->where('sender_id', $userId)->whereNull('sender_archived_at');
    }

    public function scopeUnreadFor(Builder $query, int $userId): Builder
    {
        return $query->where('receiver_id', $userId)->whereNull('read_at')->whereNull('receiver_archived_at');
    }

    public function isUnreadFor(?User $user): bool
    {
        return $user && (int) $this->receiver_id === (int) $user->id && $this->read_at === null;
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user && ((int) $this->sender_id === (int) $user->id || (int) $this->receiver_id === (int) $user->id);
    }

    public function getReferenceLabelAttribute(): string
    {
        if ($this->document) {
            return 'كتاب رقم ' . ($this->document->reference_number ?: $this->document->id);
        }

        if ($this->memo) {
            return 'مذكرة رقم ' . ($this->memo->memo_number ?: $this->memo->id);
        }

        return 'بدون ارتباط';
    }

    public function getStatusNameAttribute(): string
    {
        return $this->read_at ? 'مقروءة' : 'غير مقروءة';
    }
}

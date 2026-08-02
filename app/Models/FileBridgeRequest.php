<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileBridgeRequest extends Model
{
    protected $fillable = [
        'uuid',
        'token_hash',
        'user_id',
        'document_id',
        'status',
        'query',
        'reference_number',
        'reference_year',
        'original_name',
        'temporary_path',
        'extension',
        'mime_type',
        'file_size',
        'client_name',
        'client_machine',
        'error_message',
        'expires_at',
        'uploaded_at',
        'consumed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'reference_year' => 'integer',
        'file_size' => 'integer',
        'expires_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'consumed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}

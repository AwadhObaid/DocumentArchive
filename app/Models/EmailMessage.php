<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailMessage extends Model
{
    protected $fillable = [
        'document_id',
        'created_by',
        'to_recipients',
        'cc_recipients',
        'bcc_recipients',
        'subject',
        'body',
        'attachment_ids',
        'attachment_names',
        'attachments_count',
        'total_attachment_size',
        'status',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'to_recipients' => 'array',
            'cc_recipients' => 'array',
            'bcc_recipients' => 'array',
            'attachment_ids' => 'array',
            'attachment_names' => 'array',
            'attachments_count' => 'integer',
            'total_attachment_size' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'sent' => 'تم الإرسال',
            'failed' => 'فشل الإرسال',
            'pending' => 'قيد الإرسال',
            default => 'غير معروف',
        };
    }

    public function getRecipientsSummaryAttribute(): string
    {
        return implode('، ', array_filter((array) $this->to_recipients));
    }

    public function getTotalAttachmentSizeForHumansAttribute(): string
    {
        $bytes = (int) $this->total_attachment_size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' Bytes';
    }
}

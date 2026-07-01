<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    protected $fillable = [
        'document_id',
        'created_by',
        'contact_id',
        'message_template_id',
        'recipient_name',
        'phone_number',
        'normalized_phone',
        'message_body',
        'whatsapp_url',
        'status',
        'error_message',
        'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
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

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function messageTemplate()
    {
        return $this->belongsTo(MessageTemplate::class);
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'opened' => 'تم فتح واتساب',
            'failed' => 'تعذر فتح واتساب',
            'prepared' => 'تم التجهيز',
            default => 'غير معروف',
        };
    }

    public function getPhoneDisplayAttribute(): string
    {
        return $this->phone_number ?: ('+' . $this->normalized_phone);
    }

    public function getMessagePreviewAttribute(): string
    {
        $body = trim((string) $this->message_body);

        if (mb_strlen($body) <= 120) {
            return $body;
        }

        return mb_substr($body, 0, 120) . '...';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $fillable = [
        'name',
        'channel',
        'subject_template',
        'body_template',
        'sort_order',
        'is_default',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function emailMessages()
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->whereIn('channel', [$channel, 'both']);
    }

    public function getChannelNameAttribute(): string
    {
        return match ($this->channel) {
            'email' => 'البريد الإلكتروني',
            'whatsapp' => 'واتساب',
            'both' => 'البريد وواتساب',
            default => 'غير محدد',
        };
    }

    public function renderFor(?Document $document = null, ?Contact $contact = null): array
    {
        $variables = self::variableValues($document, $contact);

        return [
            'subject' => self::renderText((string) $this->subject_template, $variables),
            'body' => self::renderText((string) $this->body_template, $variables),
        ];
    }

    public static function renderText(string $text, array $variables): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use ($variables) {
            $key = $matches[1];
            return array_key_exists($key, $variables) ? (string) $variables[$key] : $matches[0];
        }, $text) ?? $text;
    }

    public static function variableValues(?Document $document = null, ?Contact $contact = null): array
    {
        $date = $document?->reference_date ? $document->reference_date->format('d/m/Y') : '-';
        $attachmentsCount = $document?->relationLoaded('attachments')
            ? $document->attachments->count()
            : ($document ? $document->attachments()->count() : 0);

        return [
            'document_number' => $document?->reference_number ?: '-',
            'reference_number' => $document?->reference_number ?: '-',
            'document_date' => $date,
            'reference_date' => $date,
            'title' => $document?->title ?: '-',
            'subject' => $document?->subject ?: ($document?->title ?: '-'),
            'description' => $document?->description ?: '-',
            'department' => $document?->department?->name ?: '-',
            'document_type' => $document?->documentType?->name ?: '-',
            'sender' => $document?->sender ?: '-',
            'receiver' => $document?->receiver ?: '-',
            'main_policy_number' => $document?->main_policy_number ?: '-',
            'sub_policy_number' => $document?->sub_policy_number ?: '-',
            'attachments_count' => (string) $attachmentsCount,
            'contact_name' => $contact?->name ?: '-',
            'contact_person' => $contact?->contact_person ?: '-',
            'contact_organization' => $contact?->organization ?: '-',
            'contact_email' => $contact?->email ?: '-',
            'contact_whatsapp' => $contact?->whatsapp_number ?: '-',
            'today' => now()->format('d/m/Y'),
            'system_name' => Setting::getValue('system_name', 'الأرشيف الإلكتروني'),
            'department_name' => Setting::getValue('system_department_name', 'الشحن والتأمين'),
            'share_link' => '-',
        ];
    }

    public static function availableVariables(): array
    {
        return [
            'document_number' => 'رقم الكتاب',
            'document_date' => 'تاريخ الكتاب',
            'subject' => 'موضوع الكتاب',
            'title' => 'عنوان الكتاب',
            'department' => 'الإدارة',
            'document_type' => 'نوع الكتاب',
            'sender' => 'المرسل',
            'receiver' => 'المستلم',
            'main_policy_number' => 'البوليصة الرئيسية',
            'sub_policy_number' => 'البوليصة الفرعية',
            'attachments_count' => 'عدد المرفقات',
            'contact_name' => 'اسم الجهة',
            'contact_person' => 'اسم الشخص',
            'contact_organization' => 'اسم المؤسسة',
            'contact_email' => 'بريد الجهة',
            'contact_whatsapp' => 'واتساب الجهة',
            'today' => 'تاريخ اليوم',
            'system_name' => 'اسم النظام',
            'department_name' => 'اسم القسم',
            'share_link' => 'رابط المرفقات الآمن',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'name',
        'organization',
        'contact_person',
        'email',
        'whatsapp_number',
        'phone',
        'type',
        'notes',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
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

    public function getDisplayNameAttribute(): string
    {
        $parts = array_filter([
            $this->name,
            $this->contact_person ? '(' . $this->contact_person . ')' : null,
        ]);

        return implode(' ', $parts) ?: 'جهة بدون اسم';
    }

    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            'internal' => 'داخلية',
            'external' => 'خارجية',
            'government' => 'جهة حكومية',
            'company' => 'شركة',
            'department' => 'إدارة',
            default => 'أخرى',
        };
    }
}

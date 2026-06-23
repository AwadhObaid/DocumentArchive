<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'role',
        'is_active',
        'last_login_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    public function uploadedAttachments()
    {
        return $this->hasMany(DocumentAttachment::class, 'uploaded_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    protected function roleName(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->role) {
                'admin' => 'مدير النظام',
                'manager' => 'مدير',
                'user' => 'مستخدم',
                'viewer' => 'مشاهد فقط',
                default => 'مستخدم',
            }
        );
    }
}
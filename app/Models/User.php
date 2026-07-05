<?php

namespace App\Models;

use App\Support\PermissionRegistry;
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
        'permissions',
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
            'permissions' => 'array',
        ];
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    public function memos()
    {
        return $this->hasMany(Memo::class, 'created_by');
    }

    public function uploadedAttachments()
    {
        return $this->hasMany(DocumentAttachment::class, 'uploaded_by');
    }

    public function uploadedMemoAttachments()
    {
        return $this->hasMany(MemoAttachment::class, 'uploaded_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function sentEmailMessages()
    {
        return $this->hasMany(EmailMessage::class, 'created_by');
    }

    public function sentWhatsappMessages()
    {
        return $this->hasMany(WhatsappMessage::class, 'created_by');
    }


    public function createdContacts()
    {
        return $this->hasMany(Contact::class, 'created_by');
    }

    public function createdMessageTemplates()
    {
        return $this->hasMany(MessageTemplate::class, 'created_by');
    }


    public function createdSharedAttachmentLinks()
    {
        return $this->hasMany(SharedAttachmentLink::class, 'created_by');
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'administrator', 'super_admin', 'مدير النظام', 'مدير'], true);
    }

    public function resolvedPermissions(): array
    {
        if ($this->isAdmin()) {
            return ['*'];
        }

        $saved = PermissionRegistry::normalize($this->permissions);

        return $saved !== [] ? $saved : PermissionRegistry::defaultsForRole($this->role);
    }

    public function hasPermission(string $permission): bool
    {
        return PermissionRegistry::has($this->resolvedPermissions(), $permission);
    }

    public function permissionLabels(): array
    {
        $permissions = $this->resolvedPermissions();
        if (in_array('*', $permissions, true)) {
            return ['كل الصلاحيات'];
        }

        $labels = PermissionRegistry::labels();
        return array_values(array_filter(array_map(fn ($key) => $labels[$key] ?? $key, $permissions)));
    }

    protected function roleName(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->role) {
                'admin', 'administrator', 'super_admin', 'مدير النظام' => 'مدير النظام',
                'manager', 'مدير' => 'مدير',
                'user' => 'مستخدم',
                'viewer' => 'مشاهد فقط',
                default => 'مستخدم',
            }
        );
    }
}

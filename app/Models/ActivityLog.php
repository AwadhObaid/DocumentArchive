<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * أسماء العمليات المعروضة للمستخدم باللغة العربية.
     * تبقى الأكواد الإنجليزية محفوظة داخلياً في قاعدة البيانات للفهرسة والفلترة.
     */
    public static function actionLabels(): array
    {
        return [
            'document.created' => 'إنشاء كتاب',
            'document.updated' => 'تعديل كتاب',
            'document.deleted' => 'حذف كتاب إلى السلة',
            'document.restored' => 'استعادة كتاب',
            'document.force_deleted' => 'حذف كتاب نهائي',
            'document.printed' => 'طباعة رقم الكتاب',
            'document.viewed' => 'عرض كتاب',

            'attachment.uploaded' => 'رفع مرفق',
            'attachment.previewed' => 'معاينة مرفق',
            'attachment.downloaded' => 'تنزيل مرفق',
            'attachment.printed' => 'طباعة مرفق',
            'attachment.deleted' => 'حذف مرفق',
            'attachment.restored' => 'استعادة مرفق',

            'user.created' => 'إضافة مستخدم',
            'user.updated' => 'تعديل مستخدم',
            'user.deleted' => 'حذف مستخدم',
            'user.enabled' => 'تفعيل مستخدم',
            'user.disabled' => 'تعطيل مستخدم',
            'user.password_changed' => 'تغيير كلمة مرور مستخدم',

            'department.created' => 'إضافة إدارة',
            'department.updated' => 'تعديل إدارة',
            'department.deleted' => 'حذف إدارة',

            'document_type.created' => 'إضافة نوع كتاب',
            'document_type.updated' => 'تعديل نوع كتاب',
            'document_type.deleted' => 'حذف نوع كتاب',

            'settings.updated' => 'تعديل الإعدادات',
            'auth.login' => 'تسجيل دخول',
            'auth.logout' => 'تسجيل خروج',

            'backup.database_created' => 'إنشاء نسخة قاعدة البيانات',
            'backup.files_created' => 'إنشاء نسخة ملفات المرفقات',
            'backup.full_created' => 'إنشاء نسخة كاملة',
            'backup.database_restored' => 'استعادة قاعدة البيانات من نسخة',
            'backup.files_restored' => 'استعادة ملفات المرفقات من نسخة',
            'backup.full_restored' => 'استعادة نسخة كاملة',
            'backup.deleted' => 'حذف نسخة احتياطية',
        ];
    }

    public static function actionLabel(?string $action): string
    {
        if (blank($action)) {
            return '-';
        }

        return self::actionLabels()[$action] ?? $action;
    }

    public function getActionLabelAttribute(): string
    {
        return self::actionLabel($this->action);
    }

    public static function modelLabel(?string $modelType, mixed $modelId = null): string
    {
        if (blank($modelType)) {
            return '-';
        }

        $baseName = class_basename($modelType);

        $label = match ($baseName) {
            'Document' => 'كتاب',
            'DocumentAttachment' => 'مرفق',
            'Department' => 'إدارة',
            'DocumentType' => 'نوع كتاب',
            'User' => 'مستخدم',
            'Setting' => 'إعداد',
            'Backup' => 'نسخة احتياطية',
            default => $baseName,
        };

        return $modelId ? $label . ' #' . $modelId : $label;
    }

    public function getModelLabelAttribute(): string
    {
        return self::modelLabel($this->model_type, $this->model_id);
    }
}

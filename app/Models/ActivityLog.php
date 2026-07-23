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

            'memo.created' => 'إنشاء مذكرة',
            'memo.updated' => 'تعديل مذكرة',
            'memo.deleted' => 'حذف مذكرة إلى السلة',
            'memo.restored' => 'استعادة مذكرة',
            'memo.force_deleted' => 'حذف مذكرة نهائي',
            'memo_attachment.previewed' => 'معاينة مرفق مذكرة',
            'memo_attachment.downloaded' => 'تنزيل مرفق مذكرة',
            'memo_legacy_import.scan_completed' => 'فحص المذكرات القديمة',
            'memo_legacy_import.scan_failed' => 'فشل فحص المذكرات القديمة',
            'memo_legacy_import.memo_imported' => 'استيراد مذكرة قديمة',
            'memo_legacy_import.import_completed' => 'اكتمال استيراد المذكرات القديمة',
            'memo_legacy_import.import_completed_with_errors' => 'استيراد مذكرات قديمة مع أخطاء',

            'circular.created' => 'إنشاء تعميم',
            'circular.updated' => 'تعديل تعميم',
            'circular.deleted' => 'حذف تعميم إلى السلة',
            'circular.restored' => 'استعادة تعميم',
            'circular.force_deleted' => 'حذف تعميم نهائي',
            'circular_attachment.previewed' => 'معاينة مرفق تعميم',
            'circular_attachment.downloaded' => 'تنزيل مرفق تعميم',

            'misc_book.created' => 'إنشاء كتاب متفرق',
            'misc_book.updated' => 'تعديل كتاب متفرق',
            'misc_book.deleted' => 'حذف كتاب متفرق إلى السلة',
            'misc_book.restored' => 'استعادة كتاب متفرق',
            'misc_book.force_deleted' => 'حذف كتاب متفرق نهائي',
            'misc_book_attachment.previewed' => 'معاينة مرفق كتاب متفرق',
            'misc_book_attachment.downloaded' => 'تنزيل مرفق كتاب متفرق',
            'legacy_circular_misc_import.scanned' => 'فحص التعاميم والكتب المتفرقة القديمة',
            'legacy_circular_misc_import.deleted' => 'حذف تقرير فحص التعاميم والمتفرقات',
            'archive_category.created' => 'إضافة تصنيف أرشيف',
            'archive_category.updated' => 'تعديل تصنيف أرشيف',
            'archive_category.toggled' => 'تغيير حالة تصنيف أرشيف',
            'archive_category.deleted' => 'حذف تصنيف أرشيف',

            'attachment.uploaded' => 'رفع مرفق',
            'attachment.previewed' => 'معاينة مرفق',
            'attachment.downloaded' => 'تنزيل مرفق',
            'attachment.printed' => 'طباعة مرفق',
            'attachment.deleted' => 'حذف مرفق',
            'attachment.restored' => 'استعادة مرفق',
            'attachment.replaced' => 'استبدال مرفق',
            'attachment.version_downloaded' => 'تنزيل إصدار مرفق محفوظ',
            'attachment.version_previewed' => 'معاينة إصدار مرفق محفوظ',
            'attachment.force_deleted' => 'حذف مرفق نهائيًا',

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
            'legacy_archive.dry_run_completed' => 'فحص تجريبي للأرشيف القديم',
            'legacy_archive.import_completed' => 'استيراد الأرشيف القديم',
            'legacy_archive.document_imported' => 'استيراد كتاب من النظام القديم',
            'smart_report.generated' => 'توليد تقرير ذكي',
            'smart_report.failed' => 'فشل توليد تقرير ذكي',
            'internal_chat.backup_created' => 'إنشاء نسخة احتياطية للدردشة الداخلية',
            'internal_chat.backup_restored' => 'استعادة نسخة احتياطية للدردشة الداخلية',
            'internal_chat.deleted_restored' => 'استعادة الدردشات المحذوفة ظاهريًا',
            'internal_chat.deleted_purged' => 'تفريغ الدردشات المحذوفة نهائيًا',

            'auth.login' => 'تسجيل دخول',
            'auth.logout' => 'تسجيل خروج',

            'backup.database_created' => 'إنشاء نسخة قاعدة البيانات',
            'backup.files_created' => 'إنشاء نسخة ملفات المرفقات',
            'backup.full_created' => 'إنشاء نسخة كاملة',
            'backup.database_restored' => 'استعادة قاعدة البيانات من نسخة',
            'backup.files_restored' => 'استعادة ملفات المرفقات من نسخة',
            'backup.full_restored' => 'استعادة نسخة كاملة',
            'backup.deleted' => 'حذف نسخة احتياطية',

            'report.viewed' => 'عرض تقرير',
            'report.printed' => 'طباعة تقرير',
            'report.exported' => 'تصدير تقرير',
            'data_quality.viewed' => 'فحص جودة البيانات',
            'system_health.viewed' => 'فحص النظام',
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

    public static function actionTone(?string $action): string
    {
        $action = (string) $action;

        if (str_contains($action, 'deleted') || str_contains($action, 'restored')) {
            return 'danger';
        }

        if (str_contains($action, 'backup') || str_contains($action, 'settings') || str_contains($action, 'password')) {
            return 'warning';
        }

        if (str_contains($action, 'created') || str_contains($action, 'uploaded')) {
            return 'success';
        }

        if (str_contains($action, 'printed') || str_contains($action, 'downloaded') || str_contains($action, 'exported')) {
            return 'info';
        }

        return 'neutral';
    }

    public function getActionToneAttribute(): string
    {
        return self::actionTone($this->action);
    }

    public static function actionGroup(?string $action): string
    {
        $action = (string) $action;

        return match (true) {
            str_starts_with($action, 'document.') => 'الكتب',
            str_starts_with($action, 'memo_legacy_import.') => 'استيراد المذكرات',
            str_starts_with($action, 'memo.') || str_starts_with($action, 'memo_attachment.') => 'المذكرات',
            str_starts_with($action, 'circular.') || str_starts_with($action, 'circular_attachment.') => 'التعاميم',
            str_starts_with($action, 'misc_book.') || str_starts_with($action, 'misc_book_attachment.') => 'الكتب المتفرقة',
            str_starts_with($action, 'legacy_circular_misc_import.') => 'استيراد التعاميم والمتفرقات',
            str_starts_with($action, 'legacy_archive_import.') => 'استيراد الأرشيف القديم',
            str_starts_with($action, 'archive_category.') => 'تصنيفات الأرشيف',
            str_starts_with($action, 'attachment.') => 'المرفقات',
            str_starts_with($action, 'backup.') => 'النسخ الاحتياطي',
            str_starts_with($action, 'user.') => 'المستخدمون',
            str_starts_with($action, 'department.') => 'الإدارات',
            str_starts_with($action, 'document_type.') => 'أنواع الكتب',
            str_starts_with($action, 'settings.') => 'الإعدادات',
            str_starts_with($action, 'internal_chat.') => 'الدردشة الداخلية',
            str_starts_with($action, 'auth.') => 'الدخول والخروج',
            str_starts_with($action, 'report.') => 'التقارير',
            default => 'عمليات أخرى',
        };
    }

    public function getActionGroupAttribute(): string
    {
        return self::actionGroup($this->action);
    }

    public static function modelTypeLabels(): array
    {
        return [
            'App\\Models\\Document' => 'كتاب',
            'App\\Models\\DocumentAttachment' => 'مرفق',
            'App\\Models\\Memo' => 'مذكرة',
            'App\\Models\\MemoAttachment' => 'مرفق مذكرة',
            'App\\Models\\LegacyMemoImportRun' => 'عملية فحص مذكرات قديمة',
            'App\\Models\\Circular' => 'تعميم',
            'App\\Models\\CircularAttachment' => 'مرفق تعميم',
            'App\\Models\\MiscBook' => 'كتاب متفرق',
            'App\\Models\\MiscBookAttachment' => 'مرفق كتاب متفرق',
            'App\\Models\\ArchiveCategory' => 'تصنيف أرشيف',
            'App\\Models\\Department' => 'إدارة',
            'App\\Models\\DocumentType' => 'نوع كتاب',
            'App\\Models\\User' => 'مستخدم',
            'App\\Models\\Setting' => 'إعداد',
            'backup' => 'نسخة احتياطية',
            'internal_chat_backup' => 'نسخة دردشة داخلية',
            'system' => 'النظام',
        ];
    }

    public static function modelLabel(?string $modelType, mixed $modelId = null): string
    {
        if (blank($modelType)) {
            return '-';
        }

        $label = self::modelTypeLabels()[$modelType] ?? match (class_basename($modelType)) {
            'Document' => 'كتاب',
            'DocumentAttachment' => 'مرفق',
            'Department' => 'إدارة',
            'DocumentType' => 'نوع كتاب',
            'User' => 'مستخدم',
            'Setting' => 'إعداد',
            'Backup' => 'نسخة احتياطية',
            default => class_basename($modelType),
        };

        return $modelId ? $label . ' #' . $modelId : $label;
    }

    public function getModelLabelAttribute(): string
    {
        return self::modelLabel($this->model_type, $this->model_id);
    }

    public function getActorLabelAttribute(): string
    {
        if ($this->user) {
            return $this->user->name ?: ($this->user->username ?: 'مستخدم #' . $this->user->id);
        }

        return 'النظام';
    }

    public function getIpLabelAttribute(): string
    {
        return $this->ip_address ?: '-';
    }

    public function shortUserAgent(int $limit = 70): string
    {
        $agent = trim((string) $this->user_agent);

        if ($agent === '') {
            return '-';
        }

        return mb_strlen($agent) > $limit
            ? mb_substr($agent, 0, $limit) . '…'
            : $agent;
    }

    public function propertiesRows(): array
    {
        $properties = $this->properties;

        if (! is_array($properties) || empty($properties)) {
            return [];
        }

        $rows = [];

        foreach ($properties as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $rows[] = [
                'key' => self::propertyLabel((string) $key),
                'value' => filled($value) ? (string) $value : '-',
            ];
        }

        return $rows;
    }

    public static function propertyLabel(string $key): string
    {
        return [
            'reference_number' => 'رقم الكتاب',
            'document_id' => 'رقم الكتاب الداخلي',
            'attachment_id' => 'رقم المرفق',
            'file_name' => 'اسم الملف',
            'original_name' => 'الاسم الأصلي',
            'backup_file' => 'ملف النسخة',
            'file' => 'الملف',
            'role' => 'الدور',
            'username' => 'اسم المستخدم',
            'name' => 'الاسم',
            'email' => 'البريد',
            'department' => 'الإدارة',
            'document_type' => 'نوع الكتاب',
            'status' => 'الحالة',
            'priority' => 'الأولوية',
        ][$key] ?? $key;
    }
}

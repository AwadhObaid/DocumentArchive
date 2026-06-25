<?php

namespace App\Support;

class PermissionRegistry
{
    public static function groups(): array
    {
        return [
            'documents' => [
                'label' => 'الكتب والمستندات',
                'permissions' => [
                    'documents.view' => 'عرض الكتب',
                    'documents.create' => 'إضافة كتاب',
                    'documents.update' => 'تعديل كتاب',
                    'documents.delete' => 'حذف كتاب إلى السلة',
                    'documents.restore' => 'استعادة كتاب',
                    'documents.force_delete' => 'الحذف النهائي للكتاب',
                    'documents.print_reference' => 'طباعة رقم الكتاب',
                ],
            ],
            'attachments' => [
                'label' => 'المرفقات',
                'permissions' => [
                    'attachments.preview' => 'معاينة المرفقات',
                    'attachments.download' => 'تنزيل المرفقات',
                    'attachments.print' => 'طباعة المرفقات',
                    'attachments.upload' => 'رفع مرفقات',
                ],
            ],
            'reports' => [
                'label' => 'التقارير',
                'permissions' => [
                    'reports.view' => 'عرض التقارير',
                    'reports.print' => 'طباعة التقارير',
                    'reports.export' => 'تصدير التقارير',
                ],
            ],
            'administration' => [
                'label' => 'الإدارة والإعدادات',
                'permissions' => [
                    'departments.manage' => 'إدارة الإدارات',
                    'document_types.manage' => 'إدارة أنواع الكتب',
                    'settings.manage' => 'إدارة الإعدادات',
                    'users.manage' => 'إدارة المستخدمين',
                    'activity_logs.view' => 'عرض سجل النشاط',
                ],
            ],
            'backup' => [
                'label' => 'النسخ الاحتياطي',
                'permissions' => [
                    'backups.view' => 'عرض صفحة النسخ الاحتياطي',
                    'backups.create' => 'إنشاء نسخ احتياطية',
                    'backups.download' => 'تنزيل النسخ الاحتياطية',
                    'backups.delete' => 'حذف النسخ الاحتياطية',
                    'backups.restore' => 'استعادة النسخ الاحتياطية',
                ],
            ],
        ];
    }

    public static function all(): array
    {
        $permissions = [];

        foreach (self::groups() as $group) {
            foreach ($group['permissions'] as $key => $label) {
                $permissions[$key] = $label;
            }
        }

        return $permissions;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function defaultsForRole(?string $role): array
    {
        return match ($role) {
            'admin', 'administrator', 'super_admin', 'مدير النظام', 'مدير' => ['*'],
            'viewer' => [
                'documents.view',
                'attachments.preview',
                'attachments.download',
                'reports.view',
            ],
            default => [
                'documents.view',
                'documents.create',
                'documents.update',
                'documents.print_reference',
                'attachments.preview',
                'attachments.download',
                'attachments.print',
                'attachments.upload',
                'reports.view',
                'reports.print',
                'reports.export',
            ],
        };
    }

    public static function normalize(array|string|null $permissions): array
    {
        if ($permissions === null || $permissions === '') {
            return [];
        }

        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        $permissions = array_values(array_unique(array_filter($permissions, fn ($permission) => is_string($permission) && $permission !== '')));

        if (in_array('*', $permissions, true)) {
            return ['*'];
        }

        $allowed = self::keys();

        return array_values(array_intersect($permissions, $allowed));
    }

    public static function has(array|string|null $permissions, string $permission): bool
    {
        $permissions = self::normalize($permissions);

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}

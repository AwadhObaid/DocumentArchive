<?php

namespace App\Support;

class PermissionRegistry
{
    /**
     * Permission groups used in the users form and by the route middleware.
     */
    public static function groups(): array
    {
        return [
            [
                'key' => 'general',
                'label' => 'عام',
                'permissions' => [
                    'dashboard.view' => 'عرض لوحة التحكم',
                    'profile.manage' => 'إدارة الملف الشخصي',
                ],
            ],
            [
                'key' => 'documents',
                'label' => 'الكتب',
                'permissions' => [
                    'documents.view' => 'عرض الكتب',
                    'documents.create' => 'إضافة كتاب',
                    'documents.edit' => 'تعديل كتاب',
                    'documents.delete' => 'حذف كتاب',
                    'documents.restore' => 'سلة المحذوفات والاستعادة',
                    'documents.print' => 'طباعة رقم الكتاب',
                    'memos.view' => 'عرض المذكرات',
                    'memos.create' => 'إضافة مذكرة',
                    'memos.edit' => 'تعديل مذكرة',
                    'memos.delete' => 'حذف مذكرة',
                    'memos.attachments' => 'عرض وتنزيل مرفقات المذكرات',
                    'activity_logs.view' => 'عرض سجل الحركة',
                ],
            ],
            [
                'key' => 'workflow',
                'label' => 'الاعتماد والأرشفة النهائية',
                'permissions' => [
                    'workflow.submit' => 'إرسال الكتب والمذكرات للمراجعة',
                    'workflow.approve' => 'اعتماد الكتب والمذكرات',
                    'workflow.reject' => 'رفض أو إرجاع الكتب والمذكرات للتعديل',
                    'workflow.finalize' => 'الأرشفة النهائية ومنع التعديل',
                    'workflow.override' => 'إعادة فتح السجلات المؤرشفة نهائيًا',
                ],
            ],
            [
                'key' => 'attachments',
                'label' => 'المرفقات',
                'permissions' => [
                    'attachments.preview' => 'معاينة المرفقات',
                    'attachments.download' => 'تنزيل المرفقات',
                ],
            ],
            [
                'key' => 'attachment_shares',
                'label' => 'مشاركة المرفقات',
                'permissions' => [
                    'attachment_shares.view' => 'عرض روابط مشاركة المرفقات',
                    'attachment_shares.create' => 'إنشاء روابط مشاركة للمرفقات',
                    'attachment_shares.revoke' => 'تعطيل وحذف روابط المشاركة',
                ],
            ],
            [
                'key' => 'reports',
                'label' => 'التقارير والجودة',
                'permissions' => [
                    'reports.view' => 'عرض التقارير',
                    'reports.export' => 'تصدير التقارير',
                    'data_quality.view' => 'جودة البيانات',
                ],
            ],
            [
                'key' => 'definitions',
                'label' => 'التعريفات',
                'permissions' => [
                    'departments.manage' => 'إدارة الإدارات',
                    'document_types.manage' => 'إدارة أنواع الكتب',
                    'book_subjects.manage' => 'إدارة مواضيع الكتب',
                ],
            ],
            [
                'key' => 'forms',
                'label' => 'النماذج',
                'permissions' => [
                    'form_links.view' => 'عرض إدارة النماذج',
                    'form_links.manage' => 'إضافة وتعديل وحذف النماذج',
                ],
            ],
            [
                'key' => 'pdf_search',
                'label' => 'PDF / OCR',
                'permissions' => [
                    'pdf_search.view' => 'عرض بحث محتوى PDF ونتائج OCR',
                    'pdf_search.index' => 'تشغيل فهرسة PDF وإعادة الفهرسة',
                ],
            ],
            [
                'key' => 'contacts',
                'label' => 'جهات الاتصال',
                'permissions' => [
                    'contacts.view' => 'عرض جهات الاتصال',
                    'contacts.manage' => 'إضافة وتعديل وحذف جهات الاتصال',
                ],
            ],
            [
                'key' => 'message_templates',
                'label' => 'قوالب الرسائل',
                'permissions' => [
                    'message_templates.view' => 'عرض قوالب الرسائل',
                    'message_templates.manage' => 'إضافة وتعديل وحذف قوالب الرسائل',
                ],
            ],

            [
                'key' => 'internal_messages',
                'label' => 'المراسلات الداخلية',
                'permissions' => [
                    'internal_messages.view' => 'عرض المراسلات الداخلية',
                    'internal_messages.send' => 'إرسال مراسلات داخلية وربط الكتب والمذكرات',
                    'internal_messages.manage' => 'إدارة جميع المراسلات الداخلية',
                ],
            ],
            [
                'key' => 'emails',
                'label' => 'البريد الإلكتروني',
                'permissions' => [
                    'emails.view' => 'عرض البريد الإلكتروني وسجل الإرسال',
                    'emails.send' => 'إرسال الكتب بالبريد الإلكتروني',
                ],
            ],
            [
                'key' => 'whatsapp',
                'label' => 'واتساب',
                'permissions' => [
                    'whatsapp.view' => 'عرض صفحة واتساب وسجل الرسائل',
                    'whatsapp.send' => 'فتح واتساب لإرسال بيانات الكتب',
                ],
            ],
            [
                'key' => 'system',
                'label' => 'إدارة النظام',
                'permissions' => [
                    'settings.manage' => 'إدارة الإعدادات',
                    'system_health.view' => 'فحص النظام',
                    'system_about.view' => 'عرض صفحة حقوق النظام والمطور',
                    'users.manage' => 'إدارة المستخدمين',
                ],
            ],
            [
                'key' => 'backup',
                'label' => 'النسخ الاحتياطي',
                'permissions' => [
                    'backups.view' => 'عرض النسخ الاحتياطية',
                    'backups.create' => 'إنشاء نسخة احتياطية',
                    'backups.download' => 'تنزيل نسخة احتياطية',
                    'backups.restore' => 'استعادة نسخة احتياطية',
                    'backups.delete' => 'حذف نسخة احتياطية',
                ],
            ],
            [
                'key' => 'notifications',
                'label' => 'الإشعارات',
                'permissions' => [
                    'notifications.view' => 'عرض الإشعارات',
                    'notifications.manage' => 'إدارة الإشعارات وإعداداتها',
                ],
            ],
        ];
    }

    public static function keys(): array
    {
        $keys = [];

        foreach (self::groups() as $group) {
            foreach ($group['permissions'] as $key => $label) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public static function labels(): array
    {
        $labels = [];

        foreach (self::groups() as $group) {
            foreach ($group['permissions'] as $key => $label) {
                $labels[$key] = $label;
            }
        }

        return $labels;
    }

    public static function normalize(mixed $permissions): array
    {
        if ($permissions === '*') {
            return ['*'];
        }

        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [$permissions];
        }

        if (!is_array($permissions)) {
            return [];
        }

        if (in_array('*', $permissions, true)) {
            return ['*'];
        }

        $allowed = self::keys();
        $normalized = [];

        foreach ($permissions as $permission) {
            $permission = trim((string) $permission);
            if ($permission !== '' && in_array($permission, $allowed, true)) {
                $normalized[] = $permission;
            }
        }

        return array_values(array_unique($normalized));
    }

    public static function has(array $permissions, string $permission): bool
    {
        if (in_array('*', $permissions, true)) {
            return true;
        }

        if (in_array($permission, $permissions, true)) {
            return true;
        }

        // Allow a parent-style wildcard such as documents.* if it is added later.
        $segments = explode('.', $permission);
        if (count($segments) > 1) {
            return in_array($segments[0] . '.*', $permissions, true);
        }

        return false;
    }

    public static function defaultsForRole(?string $role): array
    {
        $role = trim((string) $role);

        if (in_array($role, ['admin', 'administrator', 'super_admin', 'مدير النظام', 'مدير'], true)) {
            return ['*'];
        }

        if ($role === 'viewer') {
            return [
                'dashboard.view',
                'profile.manage',
                'documents.view',
                'memos.view',
                'attachments.preview',
                'attachment_shares.view',
                'reports.view',
                'form_links.view',
                'emails.view',
                'whatsapp.view',
                'contacts.view',
                'message_templates.view',
                'internal_messages.view',
                'system_about.view',
                'pdf_search.view',
            ];
        }

        return [
            'dashboard.view',
            'profile.manage',
            'documents.view',
            'documents.create',
            'documents.edit',
            'documents.print',
            'memos.view',
            'memos.create',
            'memos.edit',
            'memos.attachments',
            'workflow.submit',
            'workflow.approve',
            'workflow.reject',
            'workflow.finalize',
            'attachments.preview',
            'attachments.download',
            'attachment_shares.view',
            'attachment_shares.create',
            'attachment_shares.revoke',
            'reports.view',
            'form_links.view',
            'emails.view',
            'emails.send',
            'whatsapp.view',
            'whatsapp.send',
            'internal_messages.view',
            'internal_messages.send',
            'system_about.view',
            'pdf_search.view',
        ];
    }
}

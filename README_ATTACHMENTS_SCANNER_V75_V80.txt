# V75 + V80 — Attachments Migration + Scanner Workflow

هذا التحديث يضيف مرحلتين معاً:

1. V75 — أداة نقل وترتيب المرفقات القديمة.
2. V80 — صندوق الماسح الضوئي وربط الملفات الممسوحة بالكتب.

## أهم الملاحظات
- أداة نقل المرفقات تبدأ بفحص فقط Dry Run.
- التنفيذ الفعلي ينسخ الملفات ويحدث قاعدة البيانات.
- لا يتم حذف النسخ القديمة إلا عند تفعيل خيار الحذف صراحة.
- Laravel لا يتحكم بالماسح مباشرة من المتصفح. الحل المعتمد: برنامج المسح يحفظ الملفات في مجلد Scanner Inbox والنظام يربطها بالكتب.

## أوامر التطبيق
```powershell
cd E:\LaravelProjects\DocumentArchive
php scripts/apply_attachments_scanner_v75_v80.php
php artisan migrate
php scripts/check_attachments_scanner_v75_v80.php
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan serve --host=127.0.0.1 --port=8011
```

DocumentArchive V60 - Book Attachment Default Path Settings

الهدف:
إضافة اختيار مسار افتراضي لحفظ مرفقات الكتب من صفحة الإعدادات.

النطاق:
- الكتب فقط.
- مرفقات الكتب الجديدة فقط.
- لا يلمس المذكرات.
- لا ينقل المرفقات القديمة تلقائياً.

الإعداد الجديد:
book_attachment_storage_root

إذا تُرك فارغاً:
يستخدم النظام المسار الافتراضي داخل المشروع:
storage/app/private

إذا تم تحديد مسار مثل:
D:\DocumentArchiveFiles

فسيحفظ النظام المرفقات الجديدة بهذا الشكل:
D:\DocumentArchiveFiles\Books\DHL EXPRESS\إفراج جمركي 2026\251230010\file.pdf

ملاحظة:
يتم حفظ storage_root_path داخل سجل المرفق حتى لا تتأثر المرفقات القديمة إذا تغير المسار لاحقاً.

أوامر التركيب:
cd E:\LaravelProjects\DocumentArchive
php scripts/apply_book_attachment_default_path_settings_v60.php
php artisan migrate
php scripts/check_book_attachment_default_path_settings_v60.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

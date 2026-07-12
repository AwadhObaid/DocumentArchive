DocumentArchive V50 - Memo Soft Delete and Trash Tables

هذا التحديث يوحد استراتيجية حذف المذكرات مع الكتب:
- حذف المذكرة ينقلها إلى سلة المحذوفات ولا يحذفها نهائيًا.
- سلة المحذوفات تعرض جدولين منفصلين: الكتب المحذوفة والمذكرات المحذوفة.
- يمكن استعادة المذكرة من السلة.
- الحذف النهائي للمذكرة يتم فقط من السلة وبصلاحية مستقلة.
- مرفقات المذكرة تبقى محفوظة إلى أن يتم الحذف النهائي.

Important:
- يحتاج php artisan migrate لضمان وجود deleted_at في جدول memos إذا كان غير موجود في قاعدة السيرفر.
- لا يحتاج npm run build.

Development commands:
cd E:\LaravelProjects\DocumentArchive
php artisan migrate
php scripts/check_memo_soft_delete_trash_tables_v50.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

Server commands after Git pull:
cd C:\laragon\www\DocumentArchive
git pull origin master
php artisan migrate
php scripts/check_memo_soft_delete_trash_tables_v50.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

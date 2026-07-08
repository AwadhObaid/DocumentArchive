DocumentArchive - Internal Chat Soft Delete Visibility V39
=========================================================

سبب التحديث:
في V38 كان زر "حذف ظاهري" يضع deleted_at و archived_at على سجل المشارك،
وهذا يؤدي إلى اختفاء المحادثة/المستخدم من قائمة الدردشة. هذا السلوك غير مناسب
للمحادثات المباشرة لأن المطلوب هو مسح السجل ظاهريًا من شاشة المستخدم فقط،
مع بقاء المستخدم أو المجموعة في القائمة.

ما الذي تغير في V39؟
1) إضافة الحقل internal_chat_participants.cleared_at.
2) زر "حذف ظاهري" لم يعد يحذف المحادثة من القائمة.
3) زر "حذف ظاهري" أصبح يخفي الرسائل السابقة عن المستخدم الحالي فقط.
4) الرسائل لا تُحذف من قاعدة البيانات نهائيًا.
5) إذا وصلت رسالة جديدة أو أرسل المستخدم رسالة جديدة بعد الحذف الظاهري، تظهر المحادثة طبيعيًا.
6) يتم تحويل أي حذف ظاهري سابق من V38 إلى cleared_at وإلغاء deleted_at/archived_at حتى يعود المستخدم أو المجموعة للقائمة.

الفرق بين الأرشفة والحذف الظاهري بعد V39:
- الأرشفة: تخفي المحادثة من القائمة الرئيسية، ويمكن إظهارها عند البحث أو عند وصول رسالة جديدة.
- الحذف الظاهري: يمسح سجل الرسائل السابق من شاشة المستخدم فقط، ويبقي المستخدم/المجموعة في القائمة.

أوامر التثبيت على جهاز التطوير:
cd E:\LaravelProjects\DocumentArchive
php artisan migrate
php scripts/check_internal_chat_soft_delete_visibility_v39.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
npm run build
php artisan serve

أوامر السيرفر:
cd C:\laragon\www\DocumentArchive
git pull origin master
php artisan migrate
php scripts/check_internal_chat_soft_delete_visibility_v39.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
npm run build

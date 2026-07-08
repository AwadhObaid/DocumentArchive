DocumentArchive - Internal Chat Archived Restore V40
================================================

هذا التحديث يكمل سلوك الأرشفة في الدردشة الداخلية.

الإضافات:
- زر المحادثات المؤرشفة داخل قائمة الدردشة.
- عرض المحادثات المؤرشفة الخاصة بالمستخدم الحالي فقط.
- زر استعادة للمحادثة المؤرشفة.
- الاستعادة تعيد archived_at إلى null ولا تمس الرسائل.
- منع الإرسال داخل المحادثة المؤرشفة حتى يتم استعادتها.
- الحذف الظاهري V39 يبقى كما هو: يمسح سجل الرسائل من شاشة المستخدم فقط عبر cleared_at.

لا يوجد Migration جديد في V40.

أوامر التحقق:
php scripts/check_internal_chat_archived_restore_v40.php

بعد التركيب:
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
npm run build
php artisan serve

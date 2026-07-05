DocumentArchive - Memo Actions Update V6

هذا التحديث يضيف إلى جدول المذكرات:
- إرسال البريد
- إرسال واتساب
- رابط المرفقات

كما يضيف دعم المذكرات داخل صفحات البريد وواتساب وروابط مشاركة المرفقات الآمنة.

أوامر التركيب:
php artisan migrate
php scripts/check_memo_actions_update.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

ملاحظة:
بعد التجربة محليًا ارفع التعديل إلى Git ثم نفذه على السيرفر بنفس أوامر migrate والتنظيف.

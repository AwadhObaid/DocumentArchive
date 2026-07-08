تحديث V33 - تنبيه صوتي للدردشة الداخلية العائمة

المحتوى:
- إضافة تنبيه صوتي قصير عند وصول رسالة دردشة جديدة.
- عدم تشغيل الصوت على الرسائل التي يرسلها المستخدم نفسه.
- زر كتم/تشغيل الصوت داخل نافذة الدردشة.
- حفظ حالة الكتم في المتصفح لكل مستخدم/جهاز باستخدام localStorage.
- خيار جديد في الإعدادات: تفعيل التنبيه الصوتي عند وصول رسالة دردشة جديدة.
- استخدام Web Audio API بدون الحاجة إلى ملف MP3 خارجي.

ملاحظات:
- المتصفح قد لا يسمح بتشغيل الصوت إلا بعد أول تفاعل من المستخدم مع الصفحة، وهذا سلوك طبيعي في Chrome و Edge.
- هذا التحديث لا يحتاج migration.

طريقة التركيب:
1) فك الضغط داخل جذر المشروع مع الاستبدال.
2) نفذ:
   php scripts/check_internal_chat_sound_alert_v33.php
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear
3) افتح النظام واضغط Ctrl + F5.
4) جرّب إرسال رسالة من مستخدم آخر.

الملفات المعدلة:
- app/Http/Controllers/SettingsController.php
- public/css/internal-chat.css
- public/js/internal-chat.js
- resources/views/partials/internal-chat-widget.blade.php
- resources/views/settings/edit.blade.php
- scripts/check_internal_chat_sound_alert_v33.php

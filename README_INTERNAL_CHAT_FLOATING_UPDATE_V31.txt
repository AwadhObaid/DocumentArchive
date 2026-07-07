# تحديث الدردشة الداخلية العائمة V31

هذا التحديث يضيف نافذة عائمة للدردشة السريعة بين مستخدمي نظام أرشفة المستندات.

## المزايا

- نافذة دردشة عائمة أسفل الشاشة.
- دردشة فردية بين مستخدم ومستخدم.
- قائمة المستخدمين النشطين.
- عداد رسائل غير مقروءة على أيقونة الدردشة.
- تحديث تلقائي بدون تحديث الصفحة.
- إرسال واستقبال عبر AJAX Polling بدون WebSocket.
- حفظ الرسائل في قاعدة البيانات.
- تمييز الرسائل الصادرة والواردة.
- تعليم الرسائل كمقروءة عند فتح المحادثة.
- إعدادات لتفعيل/تعطيل الدردشة وتحديد مدة التحديث.
- صلاحيات مستقلة:
  - internal_chat.view
  - internal_chat.send

## الملفات الجديدة

- app/Http/Controllers/InternalChatController.php
- app/Models/InternalChatMessage.php
- database/migrations/2026_07_07_140000_create_internal_chat_messages_table.php
- resources/views/partials/internal-chat-widget.blade.php
- public/css/internal-chat.css
- public/js/internal-chat.js
- scripts/check_internal_chat_floating_update_v31.php

## الملفات المعدلة

- app/Http/Controllers/SettingsController.php
- app/Http/Middleware/ApplyRoutePermissions.php
- app/Models/User.php
- app/Support/PermissionRegistry.php
- resources/views/layouts/app.blade.php
- resources/views/settings/edit.blade.php
- routes/web.php

## التركيب

فك الضغط داخل مجلد المشروع مع الاستبدال:

E:\LaravelProjects\DocumentArchive

ثم نفذ:

php artisan migrate
php scripts/check_internal_chat_floating_update_v31.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

## الاختبار

1. افتح النظام وسجل الدخول بمستخدم لديه صلاحية الدردشة.
2. ستظهر أيقونة 💬 أسفل الشاشة.
3. اضغط الأيقونة واختر مستخدمًا آخر.
4. أرسل رسالة قصيرة.
5. افتح النظام من متصفح آخر أو مستخدم آخر للتأكد من وصول الرسالة والعداد.

## ملاحظات مهمة

- هذه الدردشة للتنسيق الداخلي السريع فقط.
- المراسلات الرسمية المرتبطة بالكتب والمذكرات تبقى من صفحة المراسلات الداخلية.
- هذا التحديث لا يستخدم WebSocket، بل Polling كل عدة ثواني لضمان الاستقرار على Windows/Laragon.
- يمكن تعديل مدة التحديث من صفحة الإعدادات.

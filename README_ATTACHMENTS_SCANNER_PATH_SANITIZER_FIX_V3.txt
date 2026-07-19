DocumentArchive — Attachments/Scanner Path Sanitizer Fix V3
============================================================

سبب الخطأ:
حزمة V75/V80 أعادت نسخة من BookAttachmentSmartPathService تحتوي على تعبير منتظم غير صالح
لمحارف مسارات Windows، فظهر:

preg_replace(): Unknown modifier '\\'

الإصلاح:
- استبدال التعبير المنتظم بـ str_replace آمن لمحارف Windows المحظورة.
- تطبيق الإصلاح على الملف الفعلي.
- تطبيقه أيضًا على نسخة updates/attachments_scanner_v75_v80 إن كانت موجودة، لمنع رجوع الخطأ.
- اختبار وقت التشغيل مع أسماء عربية ومسارات تحتوي على / و \\ و : وغيرها.

لا يحتاج:
- php artisan migrate
- npm run build

الأوامر:
php scripts/apply_attachments_scanner_path_sanitizer_fix_v3.php
php scripts/check_attachments_scanner_path_sanitizer_fix_v3.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear

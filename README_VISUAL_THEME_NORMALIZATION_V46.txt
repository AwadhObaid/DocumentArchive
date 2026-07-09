DocumentArchive - Visual Theme Normalization V46
================================================

الغرض:
- توحيد ألوان الوضع النهاري والليلي على مستوى النظام كامل.
- جعل الألوان والخطوط مريحة للعين.
- إصلاح الصفحات التي تحتوي CSS قديم داكن داخل ملفات Blade.
- جعل القائمة الجانبية تتأثر بالوضع النهاري والليلي.
- تحسين بطاقات لوحة التحكم، صفحة الكتب، الفلاتر، الجداول، النماذج، الدردشة، الإشعارات، وبعض صفحات الإدارة.
- عدم التأثير على صفحات الطباعة الرسمية.

الملفات:
- public/css/visual-theme-normalization-v46.css
- resources/views/layouts/app.blade.php
- scripts/apply_visual_theme_normalization_v46.php
- scripts/check_visual_theme_normalization_v46.php

طريقة التركيب على السيرفر:
1) فك الضغط داخل:
   C:\laragon\www
   وليس داخل:
   C:\laragon\www\DocumentArchive

2) نفذ:
   cd C:\laragon\www\DocumentArchive
   php scripts/apply_visual_theme_normalization_v46.php
   php scripts/check_visual_theme_normalization_v46.php
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear
   php artisan serve

3) في المتصفح:
   Ctrl + F5

ملاحظات:
- لا يحتاج migrate.
- لا يحتاج npm run build.
- إذا بقيت صفحة معينة مشوهة، التقط صورة لها وارسل اسم الرابط تحديدًا.

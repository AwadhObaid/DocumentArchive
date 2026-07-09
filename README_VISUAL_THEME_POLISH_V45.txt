DocumentArchive Visual Theme Polish V45
======================================

الهدف:
- إصلاح التشويه البصري بين الوضع النهاري والليلي.
- معالجة مشكلة ألوان لوحة التحكم التي كانت تستخدم ألوان داكنة حتى في الوضع النهاري.
- تحسين وضوح البطاقات، ألوان الخطوط، الجداول، التنبيهات، الأزرار، والنماذج.
- عدم التأثير على صفحات الطباعة الرسمية.

الملفات:
- public/css/visual-theme-polish-v45.css
- scripts/apply_visual_theme_polish_v45.php
- scripts/check_visual_theme_polish_v45.php

طريقة التركيب:
1) فك الضغط داخل C:\laragon\www أو E:\LaravelProjects حسب الجهاز.
2) نفّذ:
   php scripts/apply_visual_theme_polish_v45.php
   php scripts/check_visual_theme_polish_v45.php
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear
   php artisan serve

ملاحظات:
- لا يحتاج migration.
- لا يحتاج npm run build.
- بعد التركيب اضغط Ctrl + F5 في المتصفح.
- جرّب الوضع النهاري والليلي في لوحة التحكم، الكتب، إضافة كتاب، التقارير، الإعدادات، والدردشة.

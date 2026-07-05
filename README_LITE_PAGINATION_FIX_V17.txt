DocumentArchive - Lite Pagination Fix V17
========================================

الغرض:
- إصلاح أزرار السابق/التالي في نسخة الهاتف لايت.
- تصغير أسهم SVG التي كانت تظهر كبيرة جداً.
- منع تكرار صف السابق/التالي الافتراضي من Laravel.
- تنسيق أرقام الصفحات داخل نسخة لايت بشكل مناسب للجوال.

الملفات المعدلة:
- public/css/lite.css

ملفات الفحص:
- scripts/check_lite_pagination_fix_v17.php

طريقة التركيب:
1) فك الضغط داخل جذر المشروع مع الاستبدال:
   E:\LaravelProjects\DocumentArchive

2) نفذ:
   php scripts/check_lite_pagination_fix_v17.php
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear
   php artisan serve

3) افتح:
   http://127.0.0.1:8000/lite/notifications

4) اضغط Ctrl + F5.

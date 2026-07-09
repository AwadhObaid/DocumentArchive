DocumentArchive - Global Font Scale Refine V43
================================================

الغرض:
تصغير الخط على مستوى واجهة النظام بشكل بسيط ومريح بدون التأثير على الطباعة الرسمية أو صفحات A4.

الملفات:
- public/css/global-font-scale-fix.css
- scripts/check_global_font_scale_refine_v43.php

ملاحظات:
- لا يحتاج php artisan migrate.
- لا يحتاج npm run build.
- يعتمد على أن resources/views/layouts/app.blade.php يستدعي public/css/global-font-scale-fix.css.
- التعديل يعمل على الشاشة فقط عبر @media screen، والطباعة محفوظة عبر @media print.

طريقة التشغيل على السيرفر:
cd C:\laragon\www\DocumentArchive
php scripts/check_global_font_scale_refine_v43.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

طريقة التشغيل على جهاز التطوير:
cd E:\LaravelProjects\DocumentArchive
php scripts/check_global_font_scale_refine_v43.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

بعد التجربة والاعتماد:
git status --short
git add .
git commit -m "Refine global system font scale v43"
git tag v0.6.33-global-font-scale-refine-v43
git push origin master
git push origin v0.6.33-global-font-scale-refine-v43

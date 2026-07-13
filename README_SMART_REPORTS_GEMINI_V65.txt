# V65 - Gemini Smart Reports Module

هذا التحديث يضيف صفحة التقارير الذكية عبر Gemini API داخل نظام DocumentArchive.

## الميزات

- صفحة جديدة: التقارير الذكية
- إعدادات Gemini من صفحة الإعدادات
- حفظ Gemini API Key بشكل مشفر في قاعدة البيانات
- اختبار الاتصال بـ Gemini
- توليد تقرير عربي من بيانات الكتب والمرفقات
- لا يتم إرسال ملفات المرفقات إلى Gemini
- خيار اختياري لإرسال عناوين/مواضيع عينة من الكتب
- حفظ آخر التقارير في جدول smart_report_runs
- تصدير التقرير إلى Word
- تصدير التقرير إلى PDF عبر mPDF

## مهم جداً

لا تضع Gemini API Key داخل Git ولا داخل .env.example.
أدخل المفتاح من صفحة الإعدادات فقط.

## أوامر التركيب

```powershell
cd E:\LaravelProjects\DocumentArchive

php scripts/apply_smart_reports_gemini_v65.php
php artisan migrate
php scripts/check_smart_reports_gemini_v65.php

php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear

php artisan serve
```

## بعد التشغيل

افتح:

```txt
الإعدادات > إعدادات التقارير الذكية Gemini
```

ثم:
1. فعّل التقارير الذكية.
2. ضع Gemini API Key.
3. اختر الموديل.
4. احفظ الإعدادات.
5. افتح صفحة التقارير الذكية من القائمة الجانبية.
6. اضغط اختبار اتصال Gemini.
7. ولّد التقرير.

## السيرفر

```powershell
cd C:\laragon\www\DocumentArchive

git pull origin master

php artisan migrate
php scripts/check_smart_reports_gemini_v65.php

php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear

php artisan serve
```

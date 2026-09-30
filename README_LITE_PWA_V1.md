# DocumentArchive Lite PWA V1

هذه الحزمة تحول واجهة `/lite` الحالية إلى PWA قابلة للتثبيت على الهاتف.

## الملفات

- `resources/views/lite/layout.blade.php`
  - إضافة Manifest
  - دعم Web App على iOS/Android
  - زر تثبيت عند توفره
  - تسجيل Service Worker
- `public/manifest-lite.json`
- `public/lite-icon.svg`
- `public/lite-offline.html`
- `public/sw-lite.js`
- `public/js/lite-pwa.js`

## مهم

لم يتم تحويل الصفحات إلى API ولم يتم تخزين صفحات الوثائق أو المرفقات في Service Worker، لأن هذه البيانات محمية بجلسة المستخدم وقد تحتوي على معلومات حساسة.

يتم تخزين الملفات الثابتة فقط، بينما تظل الصفحات والوثائق والمرفقات والإشعارات من الخادم مباشرة.

## التثبيت

بعد نسخ الملفات:

```powershell
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
php artisan serve
```

ثم افتح:

`https://YOUR-SERVER/lite/`

على Chrome/Edge في الهاتف. سيظهر زر التثبيت داخل الشريط العلوي عندما يسمح المتصفح بذلك.

على iPhone/iPad يمكن استخدام:
Share -> Add to Home Screen

## الاختبار

1. تسجيل الدخول.
2. فتح `/lite/`.
3. التأكد من ظهور الواجهة دون أخطاء.
4. تجربة زر التثبيت إذا ظهر.
5. إعادة فتح التطبيق من الشاشة الرئيسية.
6. تجربة الكتب والمذكرات والمرفقات والإشعارات.
7. التأكد أن تسجيل الخروج التلقائي لا يزال يعمل.
8. تعطيل الشبكة للتأكد من ظهور صفحة "لا يوجد اتصال" عند محاولة فتح صفحة جديدة.

## الأمان

Service Worker لا يخزن HTML للوثائق أو بيانات API أو المرفقات. هذا مقصود للحفاظ على خصوصية البيانات وعدم تسريب محتوى مستخدم إلى Cache المتصفح.

DocumentArchive - Logout GET Notice Fix V27

الهدف:
منع ظهور خطأ MethodNotAllowedHttpException عند فتح /logout مباشرة من شريط العنوان، واستبداله بصفحة تنبيه واضحة للمستخدم.

ماذا يفعل التحديث:
1) يضيف Route من نوع GET للرابط /logout باسم logout.notice.
2) يضيف دالة logoutNotice داخل AuthController.
3) يضيف صفحة resources/views/auth/logout-notice.blade.php.
4) يبقي Route تسجيل الخروج الأصلي POST كما هو لأمان Laravel.

طريقة التركيب:
1) فك الضغط داخل جذر المشروع مع الاستبدال.
2) نفذ:
   php scripts/apply_logout_get_notice_fix_v27.php
   php scripts/check_logout_get_notice_fix_v27.php
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear
   php artisan serve

اختبار:
افتح:
http://127.0.0.1:8000/logout

المفترض أن تظهر صفحة تنبيه بدل صفحة الخطأ.

ملاحظة:
تسجيل الخروج الفعلي يبقى عبر POST فقط، وهذا هو الأسلوب الصحيح أمنيًا.

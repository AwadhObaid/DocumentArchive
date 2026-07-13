DocumentArchive - V61 Book Attachment Path Browser

الغرض:
إضافة زر داخل الإعدادات لاختيار المسار الافتراضي لمرفقات الكتب من نافذة داخل النظام، بدل كتابة المسار يدوياً فقط.

النطاق:
- الكتب فقط.
- لا يلمس المذكرات.
- لا ينقل المرفقات القديمة.
- لا يحتاج migrate.
- لا يحتاج npm run build.

المضاف:
1) زر "اختيار المسار" بجانب حقل المسار الافتراضي لمرفقات الكتب.
2) نافذة داخلية متناسقة مع النظام لاستعراض مجلدات جهاز السيرفر.
3) عرض محركات الأقراص على Windows مثل C:\ و D:\.
4) إمكانية الرجوع للمجلد السابق وتحديث القائمة.
5) إمكانية إنشاء مجلد جديد داخل المسار الحالي.
6) زر "استخدام هذا المسار" لنسخ المسار المختار إلى حقل الإعدادات.

ملاحظة مهمة:
المتصفح لا يسمح لصفحات الويب بقراءة المسار الكامل لمجلد من جهاز المستخدم مباشرة لأسباب أمنية.
لذلك هذا التحديث يستعرض مجلدات جهاز السيرفر من Laravel. في بيئة Laragon المحلية، جهاز السيرفر هو جهازك نفسه.

التركيب:
1) فك الضغط داخل E:\LaravelProjects
2) نفذ:
   cd E:\LaravelProjects\DocumentArchive
   php scripts/apply_book_attachment_path_browser_v61.php
   php scripts/check_book_attachment_path_browser_v61.php
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear
   php artisan serve

الاختبار:
- افتح الإعدادات.
- اذهب إلى إعدادات حفظ مرفقات الكتب.
- اضغط اختيار المسار.
- اختر محرك D: أو أنشئ مجلد DocumentArchiveFiles.
- اضغط استخدام هذا المسار.
- احفظ الإعدادات.

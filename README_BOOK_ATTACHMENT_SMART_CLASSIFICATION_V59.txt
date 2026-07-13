DocumentArchive - V59 Book Attachment Smart Folder Classification
===============================================================

الغرض
-----
إضافة تصنيف ذكي لمرفقات الكتب فقط، بدون أي تعديل على المذكرات.

ما يضيفه التحديث
----------------
1) حقول جديدة في صفحة إضافة/تعديل كتاب:
   - شركة / جهة حفظ المرفقات
   - نوع عملية حفظ المرفقات

2) عند رفع مرفق جديد لكتاب، إذا تم تعبئة أحد حقول التصنيف، يحفظ النظام الملف في مسار منظم:
   Books/{الشركة أو الجهة}/{نوع العملية السنة}/{رقم الكتاب}/

مثال:
   Books/DHL EXPRESS/إفراج جمركي 2026/251230000/
   Books/DHL EXPRESS/تصدير شحنة 2026/251230001/

3) إذا لم يتم تعبئة حقول التصنيف، يبقى مسار الحفظ القديم كما هو:
   documents/{year}/{reference_number}/

4) يتم حفظ بيانات التصنيف ومسار المجلد داخل سجل المرفق.

مهم
---
- هذا التحديث يطبق على المرفقات الجديدة فقط.
- لا ينقل المرفقات القديمة تلقائيًا.
- المذكرات غير مشمولة بهذا التحديث.
- يحتاج php artisan migrate.
- لا يحتاج npm run build.

طريقة التركيب
-------------
فك الضغط داخل:
E:\LaravelProjects

ثم نفذ:
cd E:\LaravelProjects\DocumentArchive
php artisan migrate
php scripts/check_book_attachment_smart_classification_v59.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

طريقة الاختبار
--------------
1) افتح إضافة كتاب جديد.
2) اكتب شركة / جهة مثل: DHL EXPRESS.
3) اكتب نوع العملية مثل: إفراج جمركي.
4) ارفع مرفقًا.
5) افتح الكتاب وتحقق من جدول المرفقات، سيظهر مسار الحفظ.
6) تحقق من وجود الملف داخل storage/app/Books/DHL EXPRESS/إفراج جمركي 2026/{رقم الكتاب}/

أوامر Git المقترحة
------------------
git status --short
git add app/Http/Controllers/DocumentController.php
git add app/Models/Document.php
git add app/Models/DocumentAttachment.php
git add app/Models/BookAttachmentCompany.php
git add app/Models/BookAttachmentOperation.php
git add app/Services/BookAttachmentSmartPathService.php
git add database/migrations/2026_07_13_070000_add_book_attachment_smart_classification_v59.php
git add resources/views/documents/create.blade.php
git add resources/views/documents/edit.blade.php
git add resources/views/documents/show.blade.php
git add scripts/apply_book_attachment_smart_classification_v59.php
git add scripts/check_book_attachment_smart_classification_v59.php
git add README_BOOK_ATTACHMENT_SMART_CLASSIFICATION_V59.txt
git commit -m "Add smart folder classification for book attachments v59"
git tag v0.6.56-book-attachment-smart-classification-v59
git push origin master
git push origin v0.6.56-book-attachment-smart-classification-v59

تحديث V30 - تعريب وتوضيح واجهة بحث PDF/OCR

الهدف:
تحسين صفحة بحث PDF/OCR حتى تكون مفهومة للمستخدم العربي غير التقني.

ما تم تعديله:
- تعريب مصطلحات حالة الأدوات داخل صفحة /pdf-search.
- توضيح معنى أدوات:
  - pdftotext: استخراج النص من PDF.
  - pdftoppm: تحويل PDF إلى صور.
  - Tesseract: التعرف الضوئي على النصوص.
- تغيير كلمة OCR وحدها إلى: التعرف الضوئي على النصوص.
- تعريب حالات الفهرسة مثل:
  - مفهرس بنجاح.
  - يحتاج تعرفًا ضوئيًا.
  - فشلت الفهرسة.
  - الملف غير موجود.
- تعريب طريقة المعالجة بدل القيم التقنية native / mixed / ocr / none.
- إظهار رسائل عربية أوضح عند عدم تثبيت أدوات Poppler أو Tesseract.
- الإبقاء على الاسم التقني للأداة بخط صغير فقط لأغراض الدعم والصيانة.

طريقة التركيب:
1) فك الضغط داخل مجلد المشروع مع الاستبدال.
2) نفذ الأوامر:

php scripts/check_pdf_ocr_arabic_ui_v30.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

الاختبار:
افتح:
http://127.0.0.1:8000/pdf-search

ملاحظة:
هذا التحديث واجهي/توضيحي فقط، ولا يحتاج migration جديد.

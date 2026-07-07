DocumentArchive PDF/OCR Search Update V28
=========================================

هذا التحديث يضيف:
- صفحة بحث داخل محتوى مرفقات PDF: /pdf-search
- جدول فهرسة نصوص المرفقات attachment_text_indexes
- استخراج نص PDF النصي عبر pdftotext
- OCR اختياري للـ PDF الممسوح ضوئياً عبر pdftoppm + Tesseract
- أمر Artisan للفهرسة: php artisan archive:index-pdfs
- حالة فهرسة PDF داخل صفحة عرض الكتاب والمذكرة
- إعدادات مسارات أدوات PDF/OCR داخل صفحة الإعدادات

التركيب:
1) فك الضغط داخل جذر المشروع مع الاستبدال.
2) نفذ:

php artisan migrate
php scripts/check_pdf_ocr_search_update_v28.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

الاستخدام السريع:
- افتح: http://127.0.0.1:8000/pdf-search
- اضغط تشغيل الفهرسة الآن لملفات PDF النصية.
- أو من PowerShell:

php artisan archive:index-pdfs --limit=25

لتشغيل OCR:

php artisan archive:index-pdfs --limit=5 --ocr

إعدادات الأدوات على Windows:
- pdftotext و pdftoppm تأتي من Poppler.
- tesseract يأتي من Tesseract OCR.
- إذا لم تكن الأدوات ضمن PATH، افتح صفحة الإعدادات وضع المسارات الكاملة، مثل:
  C:\Tools\poppler\Library\bin\pdftotext.exe
  C:\Tools\poppler\Library\bin\pdftoppm.exe
  C:\Program Files\Tesseract-OCR\tesseract.exe
- لغة OCR المقترحة: ara+eng

ملاحظة:
ابدأ أولاً بدون OCR لأن فهرسة PDF النصية أسرع. بعد ظهور ملفات بحالة "يحتاج OCR"، شغل OCR على دفعات صغيرة.

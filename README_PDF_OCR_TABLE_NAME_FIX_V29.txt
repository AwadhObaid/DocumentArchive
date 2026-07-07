DocumentArchive - PDF/OCR table name fix V29

Purpose:
- Fix /pdf-search QueryException:
  Table 'document_archive.attachment_text_indices' doesn't exist
- Laravel pluralizes AttachmentTextIndex to attachment_text_indices by default.
- The migration creates attachment_text_indexes, so the model must explicitly use that table.

Changed file:
- app/Models/AttachmentTextIndex.php

Added check script:
- scripts/check_pdf_ocr_table_name_fix_v29.php

Install:
1) Extract this ZIP into the project root with overwrite.
2) Run:

   cd E:\LaravelProjects\DocumentArchive
   php artisan migrate
   php scripts/check_pdf_ocr_table_name_fix_v29.php
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear
   php artisan serve

Test:
- Open http://127.0.0.1:8000/pdf-search

Notes:
- This fix does not change the database structure.
- It only aligns the Eloquent model with the existing migration table name.

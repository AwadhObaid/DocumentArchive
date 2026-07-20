DocumentArchive - Existing Legacy Attachment Repair V81.5
================================================================

Problem
-------
A repeated CSV dry run showed all old rows as "already imported" merely because
their legacy_record_id already existed. That prevented the importer from
checking whether each existing book had a physically readable attachment.

The real archive also uses a Windows share whose name contains meaningful
Unicode direction marks:

\\Server-1\‏‏ارشيف 2026

Those marks must be preserved when resolving OriginalFilePath.

Fix
---
1. Existing legacy ID no longer means automatic duplicate.
2. The importer checks for a physically readable attachment first.
3. Existing book + usable attachment:
   duplicate_legacy
4. Existing book + no usable attachment + source file available:
   attachment_repair_ready
5. Existing book + no usable attachment + source unavailable:
   attachment_repair_missing_file
6. Exact FilePath and OriginalFilePath are tried before cleaned fallbacks.
7. Dry-run items retain document_id so the existing book can be opened.
8. Execution copies only the missing attachment; it does not recreate the book.

Install
-------
php scripts/apply_legacy_existing_attachment_repair_v81_5.php
composer dump-autoload
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php scripts/check_legacy_existing_attachment_repair_v81_5.php

Diagnose book 251230306
-----------------------
php scripts/diagnose_legacy_attachment_repair_v81_5.php --reference=251230306

Test in the interface
---------------------
Upload the same full CSV and run Dry Run first.

Expected for a book that exists without a valid attachment but whose old source
file is accessible:
- Status: جاهز لاستكمال المرفق
- File: موجود

Execute only after reviewing the Dry Run. Existing books are not recreated.
The old source file is never deleted.

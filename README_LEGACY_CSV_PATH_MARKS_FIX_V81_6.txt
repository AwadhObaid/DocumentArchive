DocumentArchive - Legacy CSV Path Marks Fix V81.6
======================================================

Confirmed CSV format
--------------------
The reviewed export is UTF-8 with BOM, semicolon-delimited, has no header row,
and every row contains exactly 19 columns.

Important finding
-----------------
The real OriginalFilePath values contain meaningful Unicode direction marks
inside the Windows share name:

\\Server-1\‏‏ارشيف 2026

LegacyArchiveCsvReader previously removed those marks from every CSV cell
before LegacyArchiveImportService could inspect the exact path. That changed
the real share name and caused valid attachments to appear missing.

Fix
---
- Preserve direction marks in:
  ArchiveFolder
  FileName
  FilePath
  OriginalFilePath
  OriginalFileName
- Continue cleaning direction marks from ordinary non-path fields.
- Keep comma, tab, and semicolon delimiter detection.
- No migration is required.
- No book or attachment is deleted.

Install
-------
php scripts/apply_legacy_csv_path_marks_fix_v81_6.php
composer dump-autoload
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php scripts/check_legacy_csv_path_marks_fix_v81_6.php

Inspect one CSV row
-------------------
php scripts/inspect_legacy_csv_v81_6.php "C:\Users\Awadh\Desktop\TbArchive_Full_399.csv" 251230306

Then upload the same CSV and run Dry Run again.

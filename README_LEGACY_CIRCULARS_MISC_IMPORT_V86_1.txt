DocumentArchive - Actual Legacy Circular/Misc Import V86.1
================================================================

Purpose
-------
Imports selected files from completed V86 scans into:
- Circulars
- Miscellaneous books

Safety guarantees
-----------------
- Verifies the source size and SHA-256 again before import.
- Copies the source into local staging and verifies the copied file.
- Moves only the verified local copy into the final storage folder.
- Never moves, edits, renames, or deletes the Server-1 source file.
- Rechecks duplicates immediately before database creation.
- Allocates the final internal number transactionally at import time.
- Supports partial batches and retrying import_failed items.
- Keeps imported scan reports as an audit trail.

Numbering
---------
- Circulars: current-year circular sequence, starting at 2610000 in 2026.
- Miscellaneous books: current-year misc sequence, starting at 2620000 in 2026.
- The original document date remains unchanged.

Install
-------
Extract into E:\LaravelProjects and run:

cd E:\LaravelProjects\DocumentArchive
php scripts\apply_legacy_circulars_misc_import_v86_1.php
php artisan migrate
php artisan optimize:clear
php scripts\check_legacy_circulars_misc_import_v86_1.php

Confirmation phrase
-------------------
استيراد التعاميم والمتفرقات

Recommended first test
----------------------
Import only 2-3 files, then verify:
- Internal number
- Original date and subject
- Preview and download
- SHA-256 and size
- The original Server-1 file still exists

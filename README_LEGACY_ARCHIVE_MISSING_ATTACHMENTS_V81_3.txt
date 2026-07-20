DocumentArchive — Legacy Missing Attachments Completion V81.3
================================================================

Purpose
-------
Allow the same legacy CSV file to be imported again safely so that only
missing attachments are completed for already imported ESIS_TbArchive books.

Behavior
--------
- Existing imported book with a usable attachment: skipped without duplication.
- Existing imported book without a usable attachment + source file exists:
  the attachment is copied and linked to the existing book.
- Existing imported book without a usable attachment + source file missing:
  reported as still missing.
- New legacy row not imported before: normal V81 import behavior continues.
- A reference-number collision not linked to the same legacy ID is skipped.
- Deleted books are not modified.
- Source files are never deleted.
- Copied files are checked with SHA-256 and size comparison.

Install
-------
php scripts/apply_legacy_archive_missing_attachments_v81_3.php
composer dump-autoload
php artisan optimize:clear
php scripts/check_legacy_archive_missing_attachments_v81_3.php

Usage
-----
1. Open the Legacy Archive Import page.
2. Upload the same full CSV file.
3. Enter a correct source-root override when the old server/path changed.
4. Run Dry Run first.
5. Look for status: جاهز لاستكمال المرفق
6. Execute the import with the normal confirmation phrase.

No migration and no npm build are required.

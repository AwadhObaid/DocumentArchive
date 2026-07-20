DocumentArchive - Legacy Unicode Network Share Path Fix V81.4
===================================================================

Confirmed problem
-----------------
The old archive's real Windows share is:

\\Server-1\‏‏ارشيف 2026

The share name contains two real Unicode U+200F direction marks. V81/V81.3
removed those characters while normalizing paths, changing the share name to:

\\Server-1\ارشيف 2026

That altered share does not exist, so valid files were reported as missing.

Fix
---
- Preserve the exact FilePath and OriginalFilePath first.
- Preserve Unicode direction marks in real Windows share/folder names.
- Also generate a cleaned fallback for old rows where direction marks were
  accidental rather than part of the real path.
- Apply the same behavior to the optional source-root override.
- No database migration is required.
- No book or attachment is deleted.

Install
-------
php scripts/apply_legacy_unicode_share_path_fix_v81_4.php
composer dump-autoload
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php scripts/check_legacy_unicode_share_path_fix_v81_4.php

Test
----
Re-run the full CSV as a Dry Run. The old OriginalFilePath for book 251230306
should resolve to the real file on Server-1. Then execute the import again to
complete missing attachments for existing books.

The importer will not recreate books that already exist. It will attach only
the missing files when V81.3 is installed.

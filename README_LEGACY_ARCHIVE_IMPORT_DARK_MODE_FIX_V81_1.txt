DocumentArchive — Legacy Archive Importer V81.1
================================================

Purpose
-------
Fix unreadable text and white cards in the dark theme on the Legacy Archive Importer page.

Apply
-----
php scripts/apply_legacy_archive_import_dark_mode_fix_v81_1.php
php scripts/check_legacy_archive_import_dark_mode_fix_v81_1.php
php artisan optimize:clear
php artisan view:clear
php artisan cache:clear

Notes
-----
- No database migration is required.
- No npm build is required.
- The script creates a timestamped backup of the current CSS file.

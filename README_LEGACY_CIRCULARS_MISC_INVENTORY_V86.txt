DocumentArchive - Legacy Circulars and Miscellaneous Books Inventory V86
============================================================================

Purpose
-------
Dry-run inventory of old circulars and miscellaneous books stored on Server-1.
This stage does not copy, move, delete, or import files.

Features
--------
- Multiple source folders in one scan run.
- Map every source to Circulars or Miscellaneous Books.
- Map every source to an archive category.
- Recursive or direct-folder scanning.
- Default direction and military/civil nature for miscellaneous books.
- SHA-256 duplicate detection across all selected sources.
- Detect files already stored in circular_attachments or misc_book_attachments.
- Proposed internal numbers beginning from current safe previews.
- Proposed date from file name, then file modified date.
- Proposed subject from file name.
- Statuses: ready, needs review, existing, duplicate, unreadable, unsupported.
- Preserve Unicode and UNC paths including direction marks.

Permission
----------
The screen uses the existing archive_categories.manage permission.

Install
-------
php scripts/apply_legacy_circulars_misc_inventory_v86.php
php artisan migrate
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php scripts/check_legacy_circulars_misc_inventory_v86.php

Expected
--------
Legacy circulars and miscellaneous books inventory V86 check passed.

Test
----
Open:
استيراد التعاميم والمتفرقات القديمة

Add sources such as:
\\Server-1\...\التعاميم
\\Server-1\...\كتب الموظفين
\\Server-1\...\كتب الهيئات
\\Server-1\...\مخاطبات أخرى\عسكرية
\\Server-1\...\مخاطبات أخرى\مدنية

No npm build is required.

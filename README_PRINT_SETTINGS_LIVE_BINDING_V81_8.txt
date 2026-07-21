DocumentArchive - Print Settings Live Binding V81.8
=========================================================

Confirmed cause
---------------
The settings page saved print_top_mm, print_left_mm, and print_department_title
correctly in the settings table.

However, every document also stored old snapshot values in:
- documents.print_title
- documents.print_top_mm
- documents.print_left_mm

The print page used those document snapshot values before the current global
settings. Therefore, changing the settings did not move or rename the print
block for previously created books unless the optional mass-update checkbox
was selected.

Fix
---
- Current global print settings are now the source of truth for every book.
- Old per-document values are retained only as fallback when a global setting
  is missing.
- Zero is accepted as a valid top/left value.
- The print response disables browser/proxy caching.
- The confusing "apply to existing documents" checkbox and mass database
  update are removed.
- No database migration is required.

Install
-------
php scripts/apply_print_settings_live_binding_v81_8.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php scripts/check_print_settings_live_binding_v81_8.php

Optional diagnosis
-------------------
php scripts/diagnose_print_settings_v81_8.php 251230306

Test
----
1. Change print_top_mm to an obviously different value, such as 80.
2. Save settings.
3. Open the print page for an old book.
4. Refresh with Ctrl+F5.
5. The block must immediately move to 80 mm from the top.

No migration and no npm build are required.

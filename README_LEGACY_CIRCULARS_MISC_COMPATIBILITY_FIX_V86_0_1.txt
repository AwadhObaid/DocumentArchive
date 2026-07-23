DocumentArchive - Legacy Circular/Misc Compatibility Fix V86.0.1
==================================================================

Cause
-----
V86 reused the V81 controller, models, views, route names, and database table
names. V81 already owns legacy_archive_import_runs, where the column is
source_name rather than name. Therefore V86 failed with:

Unknown column 'name' in 'field list'

Resolution
----------
- Restores the V81 legacy-book import files.
- Moves V86 to separate controllers, models, service, views, routes, and tables.
- Preserves V81 history and functionality.
- Uses:
  legacy_circular_misc_import_runs
  legacy_circular_misc_import_sources
  legacy_circular_misc_import_items
- Uses route names:
  legacy-circular-misc-import.*
- Uses URL:
  /tools/legacy-circular-misc-import
- Adds a separate sidebar link.
- Removes the obsolete conflicting V86 source model and service.
- Drops the accidental empty legacy_archive_import_sources table when safe.

Install
-------
php scripts/apply_legacy_circulars_misc_compatibility_fix_v86_0_1.php
php artisan migrate

php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear

php scripts/check_legacy_circulars_misc_inventory_v86.php

No npm build is required.

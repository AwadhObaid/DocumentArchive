DocumentArchive - Legacy Circulars/Misc Migration Fix V86.0.2
================================================================

Problem
-------
MySQL rejected the automatically generated index name:

legacy_circular_misc_import_items_target_module_category_id_index

because MySQL identifier names are limited to 64 characters.

The failed migration may already have created the three V86 tables because
MySQL schema changes are not fully rolled back after this type of failure.

Fix
---
- Uses explicit short index names.
- Detects indexes by their column sequence, not only by name.
- Safely completes an interrupted migration without dropping V86 tables.
- Preserves any rows that may already exist.
- Creates a backup of the replaced migration file.

Install
-------
1. Extract the package into E:\LaravelProjects
2. Run:

   cd E:\LaravelProjects\DocumentArchive
   php scripts\apply_legacy_circulars_misc_migration_fix_v86_0_2.php
   php artisan migrate
   php artisan optimize:clear
   php scripts\check_legacy_circulars_misc_migration_fix_v86_0_2.php

Expected
--------
Legacy circular/misc migration V86.0.2 check passed.

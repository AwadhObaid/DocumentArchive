DocumentArchive — V75/V80 Route Registration Fix V2

Purpose
-------
Fixes both errors:
- Target class [AttachmentRelocationController] does not exist.
- Target class [ScannerInboxController] does not exist.

The fix rebuilds the full marked V75/V80 route block and uses fully-qualified controller class handlers.
It also removes stale compiled route cache files.

Apply
-----
php scripts/apply_attachments_scanner_route_registration_fix_v2.php
composer dump-autoload
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php scripts/check_attachments_scanner_route_registration_fix_v2.php

No migration and no npm build are required.

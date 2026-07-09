DocumentArchive Friendly Database Error V48
==========================================

Purpose
-------
Show a friendly Arabic page when MySQL/database connection is unavailable instead of exposing a Laravel stack trace to the user.

What it adds
------------
- app/Http/Middleware/FriendlyDatabaseConnectionErrors.php
- resources/views/errors/database-unavailable.blade.php
- public/css/friendly-database-error-v48.css
- scripts/apply_friendly_database_error_v48.php
- scripts/check_friendly_database_error_v48.php

What it changes
---------------
The apply script updates bootstrap/app.php and prepends the middleware globally so it can catch database connection errors that happen during auth middleware or page rendering.

Install
-------
Extract the ZIP into the parent folder of DocumentArchive:
- Development: E:\LaravelProjects
- Server: C:\laragon\www

Then run:
php scripts/apply_friendly_database_error_v48.php
php scripts/check_friendly_database_error_v48.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

Notes
-----
This update does not require php artisan migrate or npm run build.

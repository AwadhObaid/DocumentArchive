DocumentArchive Friendly Database Error Fix V49
=================================================

Purpose
-------
V48 middleware appeared in the stack trace, but Laravel still rendered the debug exception page.
V49 adds a Laravel exception renderer in bootstrap/app.php, so database connection failures are converted into a friendly Arabic page even when APP_DEBUG=true.

Install
-------
Unzip into the parent folder of DocumentArchive, then run:

php scripts/apply_friendly_database_error_fix_v49.php
php scripts/check_friendly_database_error_fix_v49.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

Test
----
Stop MySQL in Laragon, then open http://127.0.0.1:8000/
You should see a friendly Arabic database connection page.

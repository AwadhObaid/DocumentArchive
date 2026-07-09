DocumentArchive - Auth No Autofill V44
======================================

Purpose
-------
Reduce the risk of another person opening the system and seeing/sending saved login credentials after logout.

What this update does
---------------------
1) Adds app/Http/Middleware/PreventBrowserCacheV44.php
   - Adds no-store/no-cache headers for login/logout/authenticated pages.
   - Adds Clear-Site-Data for logout cache/storage cleanup.

2) Adds public/js/auth-no-autofill-v44.js
   - Clears username/password fields on login page load.
   - Sets autocomplete="new-password" on login fields.
   - Adds readonly-until-focus to reduce aggressive browser autofill.
   - Disables Remember Me checkbox if it exists.

3) Adds scripts/apply_auth_no_autofill_v44.php
   - Registers the middleware in bootstrap/app.php web middleware group.
   - Patches detected login Blade view(s) to disable autocomplete and load the JS file.

4) Adds scripts/check_auth_no_autofill_v44.php
   - Verifies that files and patches are applied.

Important limitation
--------------------
No website can reliably delete passwords already saved inside Chrome/Edge password manager.
Users should remove already-saved passwords manually from the browser settings if they exist.
This update prevents/limits future autofill and clears fields/cache after logout.

Installation
------------
Unzip inside:
C:\laragon\www

Then run:
cd C:\laragon\www\DocumentArchive
php scripts/apply_auth_no_autofill_v44.php
php scripts/check_auth_no_autofill_v44.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

After browser opens, press Ctrl + F5.

Git
---
git status --short
git add .
git commit -m "Disable browser login autofill after logout v44"
git tag v0.6.34-auth-no-autofill-v44
git push origin master
git push origin v0.6.34-auth-no-autofill-v44

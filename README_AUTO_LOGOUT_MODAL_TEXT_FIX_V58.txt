DocumentArchive Auto Logout Modal Text Fix V58

Purpose:
- Fix broken Arabic text in the auto logout modal button.
- Replace the auto logout modal with clean UTF-8 Arabic strings.
- Add CSS guard to prevent Arabic button text from splitting.

Install:
1. Extract this ZIP into E:\LaravelProjects so it merges into DocumentArchive.
2. Run:

cd E:\LaravelProjects\DocumentArchive
php scripts/apply_auto_logout_modal_text_fix_v58.php
php scripts/check_auto_logout_modal_text_fix_v58.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

No migrate is required.
No npm run build is required.

DocumentArchive - Book Subjects Pagination Fix V54

Purpose:
- Fix oversized Laravel pagination SVG arrows on the Book Subjects page.
- Normalize previous/next/page-number links.
- Keep text readable in light and dark modes.

Install:
1) Extract this ZIP inside E:\LaravelProjects on development, or C:\laragon\www on server.
2) Run:

cd E:\LaravelProjects\DocumentArchive
php scripts/apply_book_subjects_pagination_fix_v54.php
php scripts/check_book_subjects_pagination_fix_v54.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

No migrate required.
No npm run build required.

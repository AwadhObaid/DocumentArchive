DocumentArchive - Trash Layout Fix V52
=====================================

Purpose:
- Fix overlapping text in the Trash page.
- Separate the summary cards from the generic .stat-card layout.
- Improve spacing, line-height, wrapping, and table readability.
- Keep the fix scoped to .trash-page only.

Files:
- resources/views/documents/trash.blade.php
- public/css/trash-layout-fix-v52.css
- scripts/apply_trash_layout_fix_v52.php
- scripts/check_trash_layout_fix_v52.php

Install on development:
cd E:\LaravelProjects\DocumentArchive
php scripts/apply_trash_layout_fix_v52.php
php scripts/check_trash_layout_fix_v52.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

Install on server after Git push:
cd C:\laragon\www\DocumentArchive
git pull origin master
php scripts/check_trash_layout_fix_v52.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

Notes:
- No migration required.
- No npm run build required.
- Press Ctrl+F5 after installation.

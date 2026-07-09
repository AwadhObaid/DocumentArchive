DocumentArchive - Sidebar Scroll Fix V41
=============================================

Purpose
-------
Fixes the authenticated sidebar when the menu becomes longer than the screen.
The sidebar remains fixed, the user/footer area remains visible, and the
navigation area scrolls vertically.

Files
-----
public/css/sidebar-scroll-fix-v41.css
resources/views/layouts/app.blade.php
scripts/check_sidebar_scroll_fix_v41.php

Development install
-------------------
cd E:\LaravelProjects\DocumentArchive
php scripts/check_sidebar_scroll_fix_v41.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

Server install
--------------
cd C:\laragon\www\DocumentArchive
php scripts/check_sidebar_scroll_fix_v41.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

Notes
-----
No migration is required.
No npm build is required because this update uses a public CSS file loaded
directly from the Blade layout.
After installing, refresh with Ctrl + F5.

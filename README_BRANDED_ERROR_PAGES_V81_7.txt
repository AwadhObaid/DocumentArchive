DocumentArchive - Branded Error Pages V81.7
================================================

Adds branded Arabic RTL pages for HTTP 500, 502, and 503.

Features:
- Matches the dark-blue DocumentArchive identity
- Responsive layout
- Retry button and automatic retry after 20 seconds
- Link back to /login
- Inline CSS only; no Vite, database, or external assets required

Important:
These pages work when Apache/PHP/Laravel can still return an HTTP response.
If Laragon or Apache is completely stopped, the browser's own network error
page appears and cannot be customized by Laravel.

Custom production error pages normally require:
APP_DEBUG=false

Install:
php scripts/apply_branded_error_pages_v81_7.php
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan optimize:clear
php scripts/check_branded_error_pages_v81_7.php

No migration and no npm build are required.

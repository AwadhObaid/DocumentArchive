DocumentArchive - Internal Chat Routes Restore V51
=================================================

Purpose:
- Restores the advanced internal chat route names that are used by resources/views/partials/internal-chat-widget.blade.php.
- Fixes: Route [internal-chat.conversations.messages] not defined.
- This can happen if a later update overwrites routes/web.php with an older internal chat route group.

Files:
- scripts/apply_internal_chat_routes_restore_v51.php
- scripts/check_internal_chat_routes_restore_v51.php

Usage:
cd E:\LaravelProjects\DocumentArchive
php scripts/apply_internal_chat_routes_restore_v51.php
php scripts/check_internal_chat_routes_restore_v51.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

No migration is required.
No npm build is required.

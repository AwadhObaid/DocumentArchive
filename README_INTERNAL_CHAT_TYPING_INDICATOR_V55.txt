DocumentArchive - Internal Chat Typing Indicator V55

Purpose:
- Adds a lightweight typing indicator to the floating internal chat.
- When a participant starts typing, other participants in the same conversation see: "فلان يكتب الآن...".
- Uses Laravel Cache with short TTL; no migration and no permanent database writes.

Files patched by apply script:
- app/Http/Controllers/InternalChatController.php
- routes/web.php
- app/Http/Middleware/ApplyRoutePermissions.php
- resources/views/partials/internal-chat-widget.blade.php
- public/js/internal-chat.js
- public/css/internal-chat.css

How to apply:
php scripts/apply_internal_chat_typing_indicator_v55.php
php scripts/check_internal_chat_typing_indicator_v55.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

No migrate is required.
No npm run build is required.

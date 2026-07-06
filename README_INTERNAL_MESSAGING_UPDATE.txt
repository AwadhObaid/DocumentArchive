DocumentArchive - Internal Messaging Update

This update adds an internal messaging module for system users.

Main features:
- Internal inbox and sent box.
- Send messages between users.
- Link a message to a document.
- Link a message to a memo.
- Upload optional internal message attachments.
- Inline preview and optional download for internal message attachments.
- Notification Center integration for new internal messages.
- Sidebar unread count badge.
- Send internally buttons from documents and memos.

Install:
1. Extract into project root with replace.
2. Run:
   php artisan migrate
   php scripts/check_internal_messaging_update.php
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear
   php artisan serve

Server deployment:
   git pull origin master
   php artisan migrate
   php scripts/check_internal_messaging_update.php
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear

Routes:
- /internal-messages
- /internal-messages/create

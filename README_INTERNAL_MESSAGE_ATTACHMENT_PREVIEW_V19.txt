DocumentArchive - Internal Message Attachment Preview V19

Purpose:
- Fix internal message attachment "عرض" button.
- Make "عرض" open an in-system preview page instead of triggering browser download.
- Keep "تنزيل" as the only explicit download action.
- Add data endpoint for PDF/image preview using blob/object URL like memo attachment preview.

Install:
1) Extract into project root with replace.
2) Run:
   php scripts/check_internal_message_attachment_preview_v19.php
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear
   php artisan serve

No migration required.

Secure Attachment Links Update

This update adds secure temporary sharing links for document attachments.

Main features:
- New sidebar page: مشاركة المرفقات
- Create temporary public links for selected document attachments
- Optional password protection
- Expiry periods: 1h, 3h, 12h, 24h, 3d, 7d
- Optional download limit
- Public download page without login
- View/download counters
- Revoke and delete links
- Integration with email compose page
- Integration with WhatsApp compose page
- Button from documents list/show page to create a share link

Install:
1. Extract files into the project root.
2. Run:
   php artisan migrate
   php scripts/check_secure_attachment_links_update.php
3. Clear caches and run the server:
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear
   php artisan serve

Permissions:
- attachment_shares.view
- attachment_shares.create
- attachment_shares.revoke

Suggested Git:
git add app/Http/Controllers/SharedAttachmentLinkController.php
git add app/Models/SharedAttachmentLink.php
git add app/Models/SharedAttachmentLinkItem.php
git add app/Models/Document.php
git add app/Models/DocumentAttachment.php
git add app/Models/User.php
git add app/Services/SecureAttachmentLinkService.php
git add app/Support/PermissionRegistry.php
git add app/Http/Middleware/ApplyRoutePermissions.php
git add app/Http/Controllers/EmailController.php
git add app/Http/Controllers/WhatsappController.php
git add app/Models/MessageTemplate.php
git add database/migrations/2026_07_01_120500_create_shared_attachment_links_table.php
git add public/css/shared-attachments.css
git add public/js/shared-attachments.js
git add resources/views/shared-attachment-links
git add resources/views/documents/index.blade.php
git add resources/views/documents/show.blade.php
git add resources/views/emails/compose.blade.php
git add resources/views/whatsapp/compose.blade.php
git add resources/views/layouts/app.blade.php
git add routes/web.php
git add scripts/check_secure_attachment_links_update.php
git add README_SECURE_ATTACHMENT_LINKS_UPDATE.txt

git commit -m "Add secure attachment sharing links"
git tag -a v0.6.13-secure-attachment-links -m "Add secure attachment sharing links"

DocumentArchive - Attachment Management V83
===========================================

Features
--------
- Replace an incorrect document attachment.
- Keep the old physical file and database record as a saved version.
- Require a written replacement reason.
- Soft-delete an attachment without deleting its physical file.
- Require a written deletion reason.
- Restore manually deleted attachments from the attachment history.
- Download preserved old versions from the history page.
- Log replace, delete, restore, and archived-version download actions.
- Respect final workflow locking:
  finalized books require workflow.override in addition to documents.edit.
- Force-deleting a book now removes files belonging to active and soft-deleted
  attachment versions.

Permissions
-----------
- View history: documents.view
- Replace/delete/restore/download archived version: documents.edit
- Finalized books additionally require workflow.override through the existing
  Document::canBeModifiedBy() rule.

Important behavior
------------------
Deleting an attachment does NOT delete its physical file.
Replacing an attachment does NOT delete the old physical file.
The old version remains available in:
"سجل المرفقات والإصدارات"

Install
-------
php scripts/apply_attachment_management_v83.php
php artisan migrate

php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear

php scripts/check_attachment_management_v83.php

Test
----
1. Open a book with an attachment.
2. Replace the attachment and enter a reason.
3. Confirm the new file appears on the book page.
4. Open "سجل المرفقات والإصدارات".
5. Confirm the old version is marked "إصدار مستبدل" and can be downloaded.
6. Delete the current attachment with a reason.
7. Confirm it disappears from the book page but remains in history.
8. Restore a manually deleted attachment from history.

No npm build is required.

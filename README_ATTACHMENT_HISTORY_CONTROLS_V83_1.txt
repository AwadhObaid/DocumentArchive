DocumentArchive - Attachment History Controls V83.1
=====================================================

Adds to the attachment-history page:
- Preview button for current, deleted, and replaced attachment versions.
- Permanent-delete button for authorized users.

Permanent-delete security
-------------------------
- Requires the existing documents.force_delete permission.
- A finalized/workflow-locked book still requires workflow.override through
  the existing Document::canBeModifiedBy() rule.
- Requires a written deletion reason.
- Requires typing the exact Arabic phrase:
  حذف نهائي
- Deletes the database record permanently.
- Deletes the physical file only when no other attachment record uses the same
  disk, storage root, and file path.
- Reassigns the main attachment when the permanently deleted version was main.
- Deletes the related OCR text-index record.
- Logs the user, reason, document, attachment, and physical-file result.

Preview
-------
- Uses the existing attachments.preview permission.
- Supports active, soft-deleted, and replaced versions.
- Opens PDFs and supported images inline in a new tab.
- Logs preview activity.

Install
-------
V83 must already be installed and migrated.

php scripts/apply_attachment_history_controls_v83_1.php

php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear

php scripts/check_attachment_history_controls_v83_1.php

No migration and no npm build are required.

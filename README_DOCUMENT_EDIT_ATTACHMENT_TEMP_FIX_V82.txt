DocumentArchive - Document Edit Attachment Temporary File Fix V82
==================================================================

Problem
-------
Editing a book and uploading/replacing its main attachment caused:
The PHP temporary upload file does not exist or is not readable.

Cause
-----
BookAttachmentSmartPathService moves the UploadedFile to the archive path.
DocumentController then called getMimeType() and getSize() on the now-removed
PHP temporary file.

Fix
---
- Capture original name, extension, size and MIME type before moving the file.
- Use the captured metadata when creating document_attachments.
- Delete the newly stored physical file if the database row or activity log
  fails, because filesystem operations are not rolled back with DB transactions.
- Back up the current DocumentController.php before installation.

Install
-------
php scripts/apply_document_edit_attachment_temp_fix_v82.php
composer dump-autoload
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php scripts/check_document_edit_attachment_temp_fix_v82.php

Development server
------------------
php artisan serve --host=127.0.0.1 --port=8011

No migration or npm build is required.

DocumentArchive - Circulars and Miscellaneous Books V85
=========================================================

Scope
-----
This phase creates the foundational pages and database structure for:

1. Circulars
2. Miscellaneous books:
   - Employee books
   - Authority books
   - Other correspondence
     - Military
     - Civil

Numbering
---------
The numbering is independent and year-aware:

- Circulars in 2026 start at: 2610000
- Miscellaneous books in 2026 start at: 2620000

For a later year, the first two digits follow the year.
Example for 2027:
- Circulars: 2710000
- Miscellaneous books: 2720000

The number is allocated transactionally when the record is saved.
Deleted numbers are not reused.

Included
--------
- Database tables and counters.
- Hierarchical archive categories.
- Default categories.
- List, search, filters, create, show, edit.
- Soft delete, trash, restore, and permanent delete.
- Multiple attachments.
- Attachment preview and download.
- SHA-256 stored for newly uploaded attachments.
- Activity logging.
- New permissions and sidebar links.
- Workflow-ready database columns for later approval integration.
- Responsive light/dark compatible styling.

Default categories
------------------
Circulars:
- Administrative
- Financial
- Human Resources
- Security
- Operational
- Other

Miscellaneous books:
- Employee books
- Authority books
- Other correspondence
  - Military
  - Civil

Not included in this phase
--------------------------
These are planned for the next polishing phases after manual-entry testing:

- Workflow action buttons and approval timeline for the new modules.
- Attachment replacement, attachment-version history, and attachment soft delete.
- Import from Server-1.
- Unified reporting and OCR integration.
- Email, WhatsApp, internal-message, and public-share integration.
- Rich category editing UI. V85 supports add, enable, disable, and safe delete.

Installation
------------
1. Apply the update:

php scripts/apply_circulars_misc_books_v85.php

2. Run the migration:

php artisan migrate

3. Clear caches:

php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear

4. Verify:

php scripts/check_circulars_misc_books_v85.php

Expected:
Circulars and miscellaneous books V85 check passed.

5. Run development:

php artisan serve --host=127.0.0.1 --port=8011

Testing
-------
Circulars:
- Open "التعاميم".
- Add one circular and one PDF attachment.
- Confirm the first 2026 number is 2610000 when the table is empty.
- Test show, edit, preview, download, soft delete, restore.

Miscellaneous books:
- Open "الكتب المتفرقة".
- Add examples for employee, authority, military, and civil categories.
- Confirm the first 2026 number is 2620000 when the table is empty.
- Test search, filters, preview, download, trash, and restore.

Permissions
-----------
Circulars:
circulars.view
circulars.create
circulars.edit
circulars.delete
circulars.restore
circulars.force_delete
circulars.attachments

Miscellaneous books:
misc_books.view
misc_books.create
misc_books.edit
misc_books.delete
misc_books.restore
misc_books.force_delete
misc_books.attachments
archive_categories.manage

No npm build is required.

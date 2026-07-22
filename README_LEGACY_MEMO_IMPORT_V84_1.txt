DocumentArchive - Legacy Memo Import V84.1
================================================

Purpose
-------
Import selected legacy memo files from the completed V84 scan into the
existing Memos module.

Numbering
---------
- Final memo numbers are allocated transactionally at import time.
- The configured sequence starts at 2600000.
- Proposed scan numbers remain previews only.
- Existing and soft-deleted memo numbers are never reused.

Safety
------
- Files are COPIED from Server-1; never moved.
- Source files are never deleted or modified.
- Before import, the source size and SHA-256 must still match the V84 scan.
- The copied file is staged on local storage.
- The staged copy is verified again using size and SHA-256.
- The memo and attachment are created inside a database transaction.
- Failed database work removes the staged/final local copy.
- Existing attachment SHA-256 values are checked again at execution time.
- Re-running an imported item is idempotent and does not create a second memo.

User workflow
-------------
1. Open Memos -> Import old memos.
2. Open the completed scan report.
3. Review each proposed memo date and subject.
4. Select the ready files on the current page.
5. Type the exact confirmation phrase:
   استيراد المذكرات
6. Run the import.
7. Imported rows link directly to the created memo.
8. Failed rows can be corrected and retried.

Imported memo defaults
----------------------
- status: active
- workflow_status: draft
- one main attachment
- original UNC source path saved in memo_attachments.source_path
- SHA-256 saved in memo_attachments.sha256
- import user, time, run, item, destination path and verification result logged

Install
-------
php scripts/apply_legacy_memo_import_v84_1.php
php artisan migrate

php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear

php scripts/check_legacy_memo_import_v84_1.php

No npm build is required.

Recommended first test
----------------------
Import only 2 or 3 memos first.
Verify:
- numbering starts at 2600000
- memo appears in the Memos page
- preview/download works
- attachment exists on local storage
- source file remains on Server-1
- imported row links to the new memo

Then import the remaining memos.

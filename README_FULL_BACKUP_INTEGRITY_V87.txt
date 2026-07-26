DocumentArchive - Full Backup Integrity V87
================================================

Problem confirmed
-----------------
The old full-backup implementation copied only:

storage/app/private/documents

and the database dump contained only the original archive tables.

On the reviewed server:
- document_attachments: 404
- memo_attachments: 53
- circular_attachments: 43
- misc_book_attachments: 35
- business storage folders: 811 files / about 2.28 GB

The inspected old full backup contained one attachment only.

What V87 changes
----------------
1. Files and full backups include:
   - Books
   - documents
   - memos
   - circulars
   - misc-books

2. Database backups contain all MySQL base tables.

3. Web restore preserves:
   - users and passwords
   - roles and permissions
   - migrations
   - sessions, cache, queues, and runtime tables

4. Every new backup includes BACKUP_MANIFEST.json with:
   - database table list and SQL size
   - attachment database row counts
   - file count and total bytes for every storage directory

5. A backup is verified after ZIP creation. A mismatching ZIP is deleted and
   reported as failed instead of being shown as a successful backup.

6. Full restore is blocked for:
   - old full backups without Manifest
   - missing SQL
   - missing storage directories
   - count or size mismatches

7. Partial file restore remains compatible with legacy ZIP files and restores
   only the supported folders actually present inside the ZIP.

8. The CLI full restore now requires a verified Manifest and supports all
   attachment folders.

Install
-------
Extract the ZIP into:

E:\LaravelProjects

Then run:

cd E:\LaravelProjects\DocumentArchive
php scripts\apply_full_backup_integrity_v87.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php scripts\check_full_backup_integrity_v87.php

No migration and no npm build are required.

Validation
----------
Create a NEW full backup after installing V87.

The backup may be around 2.3 GB and can take several minutes. Do not refresh
the page or click the create button more than once.

Inspect the new backup. It should show:
- Manifest: موجود
- verified integrity status
- all five attachment directories
- database table count greater than the old seven-table dump
- attachment file total close to the current business storage total

Do not use the previously created full-backup ZIP for full restoration.

DocumentArchive — V81 Legacy Archive Importer
================================================

Purpose
-------
Import the old SQL Server TbArchive export into the Laravel DocumentArchive system.

Included source file
--------------------
Results.csv
- 97 rows
- 19 columns
- UTF-8 CSV
- No header row
- Extracted from dbo.TbArchive

Safety guarantees
-----------------
1. Dry Run does not create documents or copy files.
2. Execute uses COPY only. It never deletes old files.
3. Every copied file is verified with:
   - SHA-256 source/target comparison
   - Source/target size comparison
4. Duplicate protection:
   - ESIS_TbArchive + old ID
   - Existing reference number in the same year
5. Missing files can be imported as document data only and reviewed later.
6. Reference counters are synchronized after a successful import.

Expected old columns
--------------------
ID, ArchiveNum, ArchiveDate, ArchiveSubject, ArchiveFolder, FileName,
FileType, FilePath, OriginalFilePath, ArchiveWillMaster, ArchiveWillSub,
UserName, ArchiveNotes, IsDeleted, DeletedDate, DeletedBy, SaderID,
OriginalSaderID, OriginalFileName

Installation
------------
Extract the ZIP inside:
E:\LaravelProjects

Then run:
cd E:\LaravelProjects\DocumentArchive
php scripts/apply_legacy_archive_import_v81.php
php artisan migrate
composer dump-autoload
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php scripts/check_legacy_archive_import_v81.php
php artisan serve --host=127.0.0.1 --port=8011

Open:
http://127.0.0.1:8011/tools/legacy-archive-import

Recommended workflow
--------------------
1. Keep "Use bundled Results.csv" enabled.
2. Enter a source root override only if needed:
   \\SERVER-3\ESIS_Archive
   or E:\ESIS_Archive
3. Run Dry Run.
4. Review duplicates and missing attachments.
5. Type exactly:
   استيراد الأرشيف القديم
6. Execute import.
7. Verify imported documents and copied attachments.
8. Keep the old archive untouched until full verification and backup.

Requirements
------------
- V75/V80 Scanner/Attachment workflow installed.
- V3 path sanitizer fix installed and passing.
- PHP process must have read access to \\SERVER-3\ESIS_Archive.
- The configured new attachment root must be writable.

DocumentArchive - V62
Book Attachment Path Regex Fix

Purpose:
Fix the error that appears when saving the default book attachment path in settings:
preg_match(): Unknown modifier ']'

Cause:
A fragile regular expression was used to detect Windows absolute paths such as:
D:\DocumentArchiveFiles

On some PHP/PCRE runtimes, the pattern used for [backslash or slash] can be parsed incorrectly.

What this update does:
- Removes the fragile Windows path preg_match checks from BookAttachmentSmartPathService.
- Uses safe string-based checks for Windows drive roots and absolute paths.
- Hardens SettingsController path browser methods.
- Keeps the feature limited to book attachments only.
- Does not require migration.
- Does not require npm build.

Install:
1. Extract this ZIP inside E:\LaravelProjects
2. Run:
   cd E:\LaravelProjects\DocumentArchive
   php scripts/apply_book_attachment_path_regex_fix_v62.php
   php scripts/check_book_attachment_path_regex_fix_v62.php
   php artisan optimize:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan config:clear
   php artisan serve

Test:
- Open Settings.
- Choose or type: D:\DocumentArchiveFiles
- Save settings.
- The preg_match unknown modifier error should disappear.

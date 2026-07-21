DocumentArchive - Reference Print Choice V81.9
=================================================

Purpose
-------
Add two clear options to the "طباعة رقم الكتاب" action:

1. طباعة مباشرة
   Opens the browser's native print dialog automatically without requiring the
   user to stop on the application's preview page.

2. عرض صفحة الطباعة
   Opens the existing A4 preview page so the position can be reviewed first.

User preference
---------------
The user can enable:
"تذكّر آخر اختيار على هذا الجهاز"

The preference is stored only in the browser's localStorage:
- No database migration
- No user-table changes
- Each browser/device can keep its own preference

Important browser limitation
----------------------------
"Direct print" skips the application's preview page, but normal browsers still
show their own print dialog for security. Completely silent printing requires
external kiosk/silent-print configuration or the legacy desktop print helper.

Install
-------
php scripts/apply_reference_print_choice_v81_9.php

php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear

php scripts/check_reference_print_choice_v81_9.php

Test
----
1. Open an existing book.
2. Open the arrow next to "طباعة رقم الكتاب".
3. Choose "عرض صفحة الطباعة":
   - The normal A4 preview must appear.
4. Choose "طباعة مباشرة":
   - A new tab opens.
   - The browser print dialog opens automatically.
   - The tab closes after printing/cancelling when the browser permits it.
5. Enable "تذكّر آخر اختيار" and confirm that the main print button follows the
   saved mode on the next book.

No migration and no npm build are required.

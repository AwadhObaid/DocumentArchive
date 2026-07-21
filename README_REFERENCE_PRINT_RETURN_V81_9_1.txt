DocumentArchive - Reference Print Return V81.9.1
======================================================

Purpose
-------
Complete the direct-print workflow after the native browser print dialog ends.

Behavior
--------
1. Direct print selected from the book page:
   - Opens in a JavaScript-created popup/window.
   - Browser print dialog appears automatically.
   - After printing or cancelling, the direct-print window closes.
   - Focus returns to the existing book page.

2. Direct-print URL opened independently, or browser blocks window.close():
   - After printing/cancelling, the same tab redirects to the book display page.

Why this update is needed
-------------------------
V81.9 opened direct printing using a normal target="_blank" link with
rel="noopener". That removed window.opener, while its afterprint code only
closed the page when an opener existed.

V81.9.1 creates the window using window.open() during the user's click and adds
a safe fallback redirect to the current book page.

Browser limitation
------------------
The browser's native print dialog still appears. Normal Chrome/Edge do not
permit completely silent printing without kiosk/silent-print configuration.

Install
-------
php scripts/apply_reference_print_return_v81_9_1.php

php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear

php scripts/check_reference_print_return_v81_9_1.php

Test
----
1. Open a book.
2. Select "طباعة مباشرة".
3. Print or cancel the browser print dialog.
4. The direct-print popup must close automatically.
5. The original book page remains visible and receives focus.
6. Open a direct-print URL manually:
   - After print/cancel it must return to that book page.

No migration and no npm build are required.

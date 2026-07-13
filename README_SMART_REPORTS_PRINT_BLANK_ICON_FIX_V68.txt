DocumentArchive - Smart Reports Print Blank/Icon Fix V68

Purpose:
- Fix blank first page when printing smart reports from the browser.
- Remove the small stray orange/global UI icon that appears in the top-left of printed pages.
- Print only the official report content (#smartPrintableReport) by creating a temporary print clone.

Why this fix is needed:
- Browser printing was printing the full Laravel layout, then hiding many UI blocks with CSS.
- Some fixed/global UI elements from the application layout could still leak into print.
- Hidden layout areas could reserve space and push the actual report to page 2.

Files:
- public/css/smart-reports-v68-print-page-fix.css
- public/js/smart-reports-v68-print-page-fix.js
- scripts/apply_smart_reports_print_blank_icon_fix_v68.php
- scripts/check_smart_reports_print_blank_icon_fix_v68.php

Install:
cd E:\LaravelProjects\DocumentArchive
php scripts/apply_smart_reports_print_blank_icon_fix_v68.php
php scripts/check_smart_reports_print_blank_icon_fix_v68.php
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan serve

No migrate required.
No npm build required.

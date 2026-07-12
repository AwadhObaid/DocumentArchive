DocumentArchive - Internal Chat Admin Confirm UI V57

Purpose:
- Replace browser confirm dialogs in internal chat administration settings with a styled system modal.
- Show a clear inline error when the final-delete confirmation phrase is wrong.
- Keep server-side validation for the dangerous purge action.

Install:
1) Extract the ZIP into E:\LaravelProjects or C:\laragon\www.
2) Run:
   php scripts/apply_internal_chat_admin_confirm_ui_v57.php
   php scripts/check_internal_chat_admin_confirm_ui_v57.php

No migration and no npm build are required.

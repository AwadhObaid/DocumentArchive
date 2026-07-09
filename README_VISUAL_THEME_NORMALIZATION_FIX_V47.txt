DocumentArchive Visual Theme Normalization Fix V47

Purpose:
- Fix warnings in apply_visual_theme_normalization_v46.php caused by an invalid regex character class.
- Remove duplicated visual-theme-normalization-v46.css includes from app.blade.php.
- Keep the V46 visual theme CSS active exactly once from the <head> section.
- Keep V45 visual-theme-polish include removed.

Run:
php scripts/apply_visual_theme_normalization_fix_v47.php
php scripts/check_visual_theme_normalization_fix_v47.php

No migration required.
No npm build required.

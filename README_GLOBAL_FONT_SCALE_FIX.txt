# Global Font Scale Fix

This update adds a dedicated CSS override file to reduce the visual font scale across the authenticated DocumentArchive interface.

## Files

- `public/css/global-font-scale-fix.css`
- `resources/views/layouts/app.blade.php`
- `scripts/check_global_font_scale_fix.php`

## Scope

The CSS is loaded after the Arabic UI guard styles and reduces the size of:

- Sidebar items
- Topbar title/subtitle
- Dashboard statistic cards
- Buttons
- Forms and fields
- Tables
- Form links cards
- Notification center text

It does not edit print templates directly.

## Check

Run:

```powershell
php scripts/check_global_font_scale_fix.php
```

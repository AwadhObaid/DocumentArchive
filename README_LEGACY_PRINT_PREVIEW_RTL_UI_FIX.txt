Legacy Print Preview RTL UI Fix

This update improves the DocArchivePrintPreview Windows helper UI.

Changes:
- Moves action buttons to the right side.
- Uses manual RTL layout instead of FlowLayoutPanel to prevent buttons from starting on the left.
- Improves header distribution between DocArchive branding, form title, status, and URL.
- Keeps the existing legacy WebBrowser print preview workflow unchanged.

Main file:
- tools/DocArchivePrintPreview/Program.cs

After applying:
1. Run: php scripts/check_legacy_print_preview_rtl_ui_fix.php
2. Rebuild: tools/DocArchivePrintPreview/build_release.ps1
3. Reinstall protocol: tools/DocArchivePrintPreview/install_protocol.ps1
4. Test: tools/DocArchivePrintPreview/test_protocol.ps1

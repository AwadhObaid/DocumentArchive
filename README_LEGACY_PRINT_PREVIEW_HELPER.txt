Legacy Print Preview Helper

This file documents the legacy print preview helper integration.

Main files:
- resources/views/form-links/index.blade.php
- resources/views/form-links/print.blade.php
- tools/DocArchivePrintPreview/
- scripts/check_legacy_print_preview_helper.php

Purpose:
Adds a local Windows helper application that opens external form links using a WebBrowser control and supports the old Internet Explorer print preview workflow through a custom protocol: docarchive-print://

<?php

$root = dirname(__DIR__);
$requiredFiles = [
    'resources/views/form-links/index.blade.php',
    'resources/views/form-links/print.blade.php',
    'tools/DocArchivePrintPreview/DocArchivePrintPreview.csproj',
    'tools/DocArchivePrintPreview/Program.cs',
    'tools/DocArchivePrintPreview/build_release.ps1',
    'tools/DocArchivePrintPreview/install_protocol.ps1',
    'tools/DocArchivePrintPreview/uninstall_protocol.ps1',
    'tools/DocArchivePrintPreview/test_protocol.ps1',
    'README_LEGACY_PRINT_PREVIEW_HELPER.txt',
];

$errors = [];

foreach ($requiredFiles as $file) {
    if (! is_file($root . DIRECTORY_SEPARATOR . $file)) {
        $errors[] = "Missing file: {$file}";
    }
}

$index = @file_get_contents($root . '/resources/views/form-links/index.blade.php') ?: '';
$print = @file_get_contents($root . '/resources/views/form-links/print.blade.php') ?: '';
$program = @file_get_contents($root . '/tools/DocArchivePrintPreview/Program.cs') ?: '';

if (! str_contains($index, 'docarchive-print://preview?url=')) {
    $errors[] = 'index.blade.php does not contain the docarchive-print protocol link.';
}

if (! str_contains($index, 'معاينة الطباعة القديمة')) {
    $errors[] = 'index.blade.php does not contain the legacy preview button label.';
}

if (! str_contains($print, 'فتح ببرنامج المعاينة القديمة')) {
    $errors[] = 'print.blade.php does not contain the helper button label.';
}

if (! str_contains($program, 'ShowPrintPreviewDialog')) {
    $errors[] = 'Program.cs does not call ShowPrintPreviewDialog.';
}

if (! str_contains($program, 'FEATURE_BROWSER_EMULATION')) {
    $errors[] = 'Program.cs does not configure FEATURE_BROWSER_EMULATION.';
}

if ($errors) {
    echo "Legacy print preview helper check failed:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "Legacy print preview helper check passed.\n";
echo "Next: build tools/DocArchivePrintPreview and run install_protocol.ps1 on each Windows client that needs old print preview.\n";

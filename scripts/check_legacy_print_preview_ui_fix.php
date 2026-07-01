<?php

$root = dirname(__DIR__);
$requiredFiles = [
    'tools/DocArchivePrintPreview/Program.cs',
    'tools/DocArchivePrintPreview/DocArchivePrintPreview.csproj',
    'tools/DocArchivePrintPreview/build_release.ps1',
    'tools/DocArchivePrintPreview/install_protocol.ps1',
    'tools/DocArchivePrintPreview/test_protocol.ps1',
    'tools/DocArchivePrintPreview/uninstall_protocol.ps1',
    'README_LEGACY_PRINT_PREVIEW_UI_FIX.txt',
];

$missing = [];
foreach ($requiredFiles as $file) {
    if (! is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $missing[] = $file;
    }
}

$programPath = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'DocArchivePrintPreview' . DIRECTORY_SEPARATOR . 'Program.cs';
$program = is_file($programPath) ? file_get_contents($programPath) : '';

$markers = [
    'BuildHeaderPanel',
    'BuildActionPanel',
    'CreateActionButton',
    'معاينة الطباعة القديمة',
    'فتح في المتصفح',
    'DocArchive',
];

foreach ($markers as $marker) {
    if ($program !== '' && strpos($program, $marker) === false) {
        $missing[] = 'Program.cs marker: ' . $marker;
    }
}

if ($missing) {
    echo "Legacy print preview UI fix check failed:\n";
    foreach ($missing as $item) {
        echo "- Missing: {$item}\n";
    }
    exit(1);
}

echo "Legacy print preview UI fix check passed.\n";
echo "Next steps:\n";
echo "1) cd tools\\DocArchivePrintPreview\n";
echo "2) Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass\n";
echo "3) .\\build_release.ps1\n";
echo "4) .\\install_protocol.ps1\n";
echo "5) .\\test_protocol.ps1\n";

<?php

$root = dirname(__DIR__);
$viewsRoot = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';

if (!is_dir($viewsRoot)) {
    fwrite(STDERR, "resources/views directory was not found.\n");
    exit(1);
}

$needles = [
    "hasPermission('documents.view')",
    "hasPermission('documents.create')",
    "hasPermission('documents.update')",
    "hasPermission('documents.delete')",
    "hasPermission('documents.restore')",
    "hasPermission('reports.view')",
    "hasPermission('backups.view')",
    "hasPermission('users.manage')",
];

$allContent = '';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsRoot, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
        $allContent .= "\n" . file_get_contents($file->getPathname());
    }
}

$missing = [];
foreach ($needles as $needle) {
    if (!str_contains($allContent, $needle)) {
        $missing[] = $needle;
    }
}

if ($missing === []) {
    echo "OK: UI permission visibility markers were found in Blade views.\n";
    exit(0);
}

echo "WARNING: Some expected UI permission markers were not found.\n";
foreach ($missing as $needle) {
    echo "- {$needle}\n";
}

echo "This can be normal if the related page/button does not exist yet.\n";
exit(0);

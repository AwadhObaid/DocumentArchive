<?php

$root = dirname(__DIR__);
$checks = [
    'app/Http/Controllers/SettingsController.php' => [
        'bookAttachmentStorageRoots',
        'bookAttachmentStorageDirectories',
        'createBookAttachmentStorageDirectory',
        'availableStorageRoots',
    ],
    'resources/views/settings/edit.blade.php' => [
        'bookAttachmentStorageBrowseBtn',
        'bookStoragePathModal',
        'settings.book-attachment-storage.roots',
        'settings.book-attachment-storage.directories',
        'إنشاء مجلد هنا',
    ],
    'routes/web.php' => [
        'settings.book-attachment-storage.roots',
        'settings.book-attachment-storage.directories',
        'settings.book-attachment-storage.directories.create',
    ],
];

$failed = false;

foreach ($checks as $relativePath => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

    if (! is_file($path)) {
        echo "[FAIL] Missing file: {$relativePath}\n";
        $failed = true;
        continue;
    }

    $content = file_get_contents($path);

    foreach ($needles as $needle) {
        if (! str_contains($content, $needle)) {
            echo "[FAIL] {$relativePath} missing: {$needle}\n";
            $failed = true;
        }
    }
}

if ($failed) {
    echo "\nV61 apply check failed. تأكد أنك فككت الملف المضغوط داخل E:\\LaravelProjects وليس داخل مجلد المشروع نفسه.\n";
    exit(1);
}

echo "Book attachment path browser V61 files are in place.\n";
echo "Run: php scripts/check_book_attachment_path_browser_v61.php\n";

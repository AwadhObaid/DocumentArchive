<?php

$root = dirname(__DIR__);
$errors = [];

function checkFileContains(string $root, string $relativePath, array $needles, array &$errors): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

    if (! is_file($path)) {
        $errors[] = "Missing file: {$relativePath}";
        return;
    }

    $content = file_get_contents($path);

    foreach ($needles as $needle) {
        if (! str_contains($content, $needle)) {
            $errors[] = "{$relativePath} missing: {$needle}";
        }
    }
}

checkFileContains($root, 'app/Http/Controllers/SettingsController.php', [
    'public function bookAttachmentStorageRoots()',
    'public function bookAttachmentStorageDirectories(Request $request)',
    'public function createBookAttachmentStorageDirectory(Request $request)',
    'private function availableStorageRoots(): array',
    'private function normalizeStorageBrowserPath(?string $path): string',
], $errors);

checkFileContains($root, 'resources/views/settings/edit.blade.php', [
    'id="bookAttachmentStorageRootInput"',
    'id="bookAttachmentStorageBrowseBtn"',
    'id="bookStoragePathModal"',
    'id="bookStorageCreateFolderBtn"',
    "route('settings.book-attachment-storage.roots')",
    "route('settings.book-attachment-storage.directories')",
    "route('settings.book-attachment-storage.directories.create')",
], $errors);

checkFileContains($root, 'routes/web.php', [
    "name('settings.book-attachment-storage.roots')",
    "name('settings.book-attachment-storage.directories')",
    "name('settings.book-attachment-storage.directories.create')",
], $errors);

foreach (['app/Http/Controllers/SettingsController.php', 'routes/web.php'] as $relativePath) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($path)) {
        $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        if ($code !== 0) {
            $errors[] = "PHP syntax check failed for {$relativePath}: " . implode(' ', $output);
        }
    }
}

if ($errors !== []) {
    echo "Book attachment path browser V61 check failed:\n";
    foreach ($errors as $error) {
        echo "[FAIL] {$error}\n";
    }
    exit(1);
}

echo "Book attachment path browser V61 check passed.\n";
echo "Routes added: settings.book-attachment-storage.roots / directories / directories.create\n";
echo "No migration required. No npm build required.\n";

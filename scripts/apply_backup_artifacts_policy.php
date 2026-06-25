<?php

$root = dirname(__DIR__);
$gitignore = $root . DIRECTORY_SEPARATOR . '.gitignore';

if (!file_exists($gitignore)) {
    file_put_contents($gitignore, "");
}

$sectionStart = '# DocumentArchive local script backup artifacts';
$section = <<<'TXT'

# DocumentArchive local script backup artifacts
# These folders/files are generated temporarily by update scripts and must not be autoloaded or committed.
app/**/_backup*/
app/**/.backup*/
app/**/*.bak
resources/**/_backup*/
resources/**/.backup*/
resources/**/*.bak
routes/**/_backup*/
routes/**/.backup*/
routes/**/*.bak
*.before-*.bak
*.backup
TXT;

$content = file_get_contents($gitignore);
if (!str_contains($content, $sectionStart)) {
    $content = rtrim($content) . PHP_EOL . $section . PHP_EOL;
    file_put_contents($gitignore, $content);
    echo ".gitignore updated with backup artifact rules.\n";
} else {
    echo ".gitignore already contains backup artifact rules.\n";
}

$targets = [
    $root . DIRECTORY_SEPARATOR . 'app',
    $root . DIRECTORY_SEPARATOR . 'resources',
    $root . DIRECTORY_SEPARATOR . 'routes',
];

$deletedDirs = [];
$deletedFiles = [];

foreach ($targets as $target) {
    if (!is_dir($target)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $name = $item->getFilename();

        if ($item->isDir() && str_starts_with($name, '_backup')) {
            removeDirectory($path);
            $deletedDirs[] = relativePath($root, $path);
            continue;
        }

        if ($item->isFile() && (str_ends_with($name, '.bak') || str_contains($name, '.before-'))) {
            @unlink($path);
            $deletedFiles[] = relativePath($root, $path);
        }
    }
}

if ($deletedDirs === [] && $deletedFiles === []) {
    echo "No old backup artifacts found under app/resources/routes.\n";
} else {
    if ($deletedDirs !== []) {
        echo "Deleted backup directories:\n";
        foreach ($deletedDirs as $dir) {
            echo "- {$dir}\n";
        }
    }
    if ($deletedFiles !== []) {
        echo "Deleted backup files:\n";
        foreach ($deletedFiles as $file) {
            echo "- {$file}\n";
        }
    }
}

echo "Backup artifact policy applied.\n";
echo "Next commands:\n";
echo "composer dump-autoload\n";
echo "php artisan optimize:clear\n";
echo "php scripts/check_backup_artifacts_policy.php\n";

function removeDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($dir);
}

function relativePath(string $root, string $path): string
{
    return ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
}

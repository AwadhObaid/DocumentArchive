<?php
/**
 * Direct Arabic replacement-character repair.
 *
 * Purpose:
 * - Fix corrupted Arabic replacement characters in active project files.
 * - Does NOT use mb_convert_encoding.
 * - Does NOT use complex regex.
 * - Replaces:
 *      U+FFFD replacement character bytes: EF BF BD
 *      Mojibake: ï¿½
 *      HTML entities: &#65533; and &#xFFFD;
 *   with Arabic letter: م
 *
 * Run from Laravel project root:
 *   php scripts/fix_arabic_fffd_only.php
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: شغّل السكربت من داخل جذر مشروع Laravel.\n");
    exit(1);
}

$targets = [
    'resources/views',
    'app',
    'routes',
    'public/js',
    'public/css',
];

$extensions = [
    'php' => true,
    'blade.php' => true,
    'js' => true,
    'css' => true,
];

$timestamp = date('Ymd_His');
$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'arabic-fffd-direct-' . $timestamp;

$replacementChar = "\xEF\xBF\xBD";       // � UTF-8 bytes
$mojibake = "\xC3\xAF\xC2\xBF\xC2\xBD";  // ï¿½ UTF-8 bytes

$search = [
    $replacementChar,
    $mojibake,
    '&#65533;',
    '&#xFFFD;',
    '&#xfffd;',
    '&amp;#65533;',
    '&amp;#xFFFD;',
    '&amp;#xfffd;',
];

$replace = 'م';

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("تعذر إنشاء المجلد: {$dir}");
    }
}

function should_skip_path(string $path): bool
{
    $normalized = str_replace('\\', '/', $path);

    $skipParts = [
        '/vendor/',
        '/node_modules/',
        '/storage/',
        '/bootstrap/cache/',
        '/.git/',
        '/_backup',
        '/patch-backups/',
    ];

    foreach ($skipParts as $part) {
        if (strpos($normalized, $part) !== false) {
            return true;
        }
    }

    return false;
}

function is_target_file(string $file): bool
{
    $name = basename($file);

    if (str_ends_with($name, '.blade.php')) {
        return true;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($ext, ['php', 'js', 'css'], true);
}

$changed = [];
$scanned = 0;

foreach ($targets as $target) {
    $targetPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
    if (!is_dir($targetPath)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($targetPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();

        if ($item->isDir()) {
            continue;
        }

        if (should_skip_path($path) || !is_target_file($path)) {
            continue;
        }

        $scanned++;
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }

        $newContent = str_replace($search, $replace, $content);

        if ($newContent !== $content) {
            $relative = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
            $backupPath = $backupRoot . DIRECTORY_SEPARATOR . $relative;
            ensure_dir(dirname($backupPath));
            copy($path, $backupPath);

            file_put_contents($path, $newContent);
            $changed[] = str_replace('\\', '/', $relative);
        }
    }
}

echo "DONE: تم إصلاح رموز الترميز التالفة بطريقة مباشرة.\n";
echo "Scanned files: {$scanned}\n";
echo "Changed files: " . count($changed) . "\n";

if ($changed) {
    echo "Backup: {$backupRoot}\n";
    echo "Files changed:\n";
    foreach ($changed as $file) {
        echo "- {$file}\n";
    }
} else {
    echo "لا توجد ملفات تحتاج تعديل.\n";
}

echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

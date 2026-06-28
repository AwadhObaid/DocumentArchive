<?php
/**
 * Check for U+FFFD replacement characters in active project files.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: تأكد أنك تشغل السكربت من جذر مشروع Laravel.\n");
    exit(1);
}

$scanDirs = ['resources/views', 'app', 'routes', 'config', 'database', 'public/css', 'public/js'];
$replacementChar = "\xEF\xBF\xBD";
$bad = [];

function shouldSkipEncodingCheck(string $path): bool
{
    $normalized = str_replace('\\', '/', $path);
    foreach (['/vendor/', '/node_modules/', '/storage/', '/bootstrap/cache/', '/_backup', '/patch-backups/', '/.git/'] as $part) {
        if (str_contains($normalized, $part)) {
            return true;
        }
    }
    return false;
}

function allowedEncodingCheckFile(string $path): bool
{
    $base = basename($path);
    if (str_ends_with($base, '.blade.php')) return true;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['php', 'js', 'css', 'json', 'md', 'txt'], true);
}

foreach ($scanDirs as $dir) {
    $fullDir = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);
    if (!is_dir($fullDir)) continue;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        if (!$item->isFile()) continue;
        $path = $item->getPathname();
        if (shouldSkipEncodingCheck($path) || !allowedEncodingCheckFile($path)) continue;
        $content = file_get_contents($path);
        if ($content !== false && str_contains($content, $replacementChar)) {
            $bad[] = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR . '/\\');
        }
    }
}

$layout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
$layoutHasCharset = false;
if (is_file($layout)) {
    $content = file_get_contents($layout) ?: '';
    $layoutHasCharset = (bool) preg_match('/<meta\s+charset=["\']utf-8["\']\s*\/?\s*>/i', $content);
}

if (!empty($bad)) {
    echo "ERROR: ما زالت توجد أحرف تالفة U+FFFD داخل الملفات النشطة:\n";
    foreach ($bad as $file) echo "- {$file}\n";
    exit(1);
}

if (is_file($layout) && !$layoutHasCharset) {
    echo "ERROR: وسم charset=utf-8 غير موجود داخل resources/views/layouts/app.blade.php\n";
    exit(1);
}

echo "OK: لا توجد أحرف تالفة U+FFFD في الملفات النشطة، والترميز UTF-8 مضبوط في layout.\n";

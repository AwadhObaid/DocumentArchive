<?php
/**
 * Restore Arabic UI text corrupted with U+FFFD replacement character.
 * Safe patch: backs up touched files under storage/app/private/patch-backups.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: تأكد أنك تفك الضغط وتشغل السكربت من جذر مشروع Laravel.\n");
    exit(1);
}

$timestamp = date('Ymd_His');
$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'arabic-encoding-restore-' . $timestamp;
if (!is_dir($backupRoot) && !mkdir($backupRoot, 0777, true) && !is_dir($backupRoot)) {
    fwrite(STDERR, "ERROR: تعذر إنشاء مجلد النسخ الاحتياطي: {$backupRoot}\n");
    exit(1);
}

$scanDirs = [
    'resources/views',
    'app',
    'routes',
    'config',
    'database',
    'public/css',
    'public/js',
];

$extensions = [
    'php' => true,
    'blade.php' => true,
    'js' => true,
    'css' => true,
    'json' => true,
    'md' => true,
    'txt' => true,
];

function shouldSkipPath(string $path): bool
{
    $normalized = str_replace('\\', '/', $path);
    $skipParts = [
        '/vendor/', '/node_modules/', '/storage/', '/bootstrap/cache/',
        '/_backup', '/patch-backups/', '/.git/',
    ];
    foreach ($skipParts as $part) {
        if (str_contains($normalized, $part)) {
            return true;
        }
    }
    return false;
}

function isAllowedFile(string $path): bool
{
    $base = basename($path);
    if (str_ends_with($base, '.blade.php')) {
        return true;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['php', 'js', 'css', 'json', 'md', 'txt'], true);
}

function backupFile(string $root, string $backupRoot, string $file): void
{
    $relative = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR . '/\\');
    $target = $backupRoot . DIRECTORY_SEPARATOR . $relative;
    $targetDir = dirname($target);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        throw new RuntimeException("تعذر إنشاء مجلد النسخة الاحتياطية: {$targetDir}");
    }
    if (!copy($file, $target)) {
        throw new RuntimeException("تعذر نسخ الملف احتياطياً: {$file}");
    }
}

function normalizeUtf8(string $content): string
{
    // Remove UTF-8 BOM if present.
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    }
    return $content;
}

$touched = [];
$replacementChar = "\xEF\xBF\xBD"; // U+FFFD

foreach ($scanDirs as $dir) {
    $fullDir = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);
    if (!is_dir($fullDir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }
        $path = $item->getPathname();
        if (shouldSkipPath($path) || !isAllowedFile($path)) {
            continue;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }

        $new = normalizeUtf8($content);

        // In the current corruption, U+FFFD appears where the Arabic letter م was lost.
        // This is deliberately limited to active project source files, not storage/vendor.
        if (str_contains($new, $replacementChar)) {
            $new = str_replace($replacementChar, 'م', $new);
        }

        // Add/repair charset meta in the main layout if needed.
        $normalizedPath = str_replace('\\', '/', $path);
        if (str_ends_with($normalizedPath, 'resources/views/layouts/app.blade.php')) {
            if (!preg_match('/<meta\s+charset=["\']utf-8["\']\s*\/?\s*>/i', $new)) {
                if (preg_match('/<head[^>]*>/i', $new, $m, PREG_OFFSET_CAPTURE)) {
                    $insertPos = $m[0][1] + strlen($m[0][0]);
                    $new = substr($new, 0, $insertPos) . "\n    <meta charset=\"utf-8\">" . substr($new, $insertPos);
                }
            }
        }

        if ($new !== $content) {
            backupFile($root, $backupRoot, $path);
            file_put_contents($path, $new);
            $touched[] = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR . '/\\');
        }
    }
}

if (empty($touched)) {
    echo "DONE: لم يتم العثور على أحرف تالفة U+FFFD داخل الملفات النشطة.\n";
} else {
    echo "DONE: تم إصلاح الترميز العربي في " . count($touched) . " ملف/ملفات.\n";
    echo "Backup: {$backupRoot}\n";
    echo "Files touched:\n";
    foreach ($touched as $file) {
        echo "- {$file}\n";
    }
}

echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

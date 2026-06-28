<?php
/**
 * إصلاح علامات الترميز التالفة التي ظهرت بدل حرف الميم "م".
 * لا يستخدم mb_convert_encoding ولا Regex معقد.
 * يستهدف ملفات المشروع النشطة فقط ويتجاهل vendor/node_modules/storage backups.
 */

$root = realpath(__DIR__ . '/..');
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: شغّل السكربت من جذر مشروع Laravel.\n");
    exit(1);
}

$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'arabic-meem-fix-' . date('Ymd_His');

$targets = [
    'resources/views',
    'app',
    'routes',
    'public/js',
    'public/css',
];

$allowedExtensions = ['php', 'blade.php', 'js', 'css'];

$badPatterns = [
    "\xEF\xBF\xBD",      // U+FFFD replacement character in UTF-8
    'ï¿½',                // mojibake form
    '&#65533;',
    '&#xFFFD;',
    '&amp;#65533;',
    '&amp;#xFFFD;',
];

function normalizePath(string $path): string {
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function relativePath(string $root, string $path): string {
    $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_replace($root, '', $path);
}

function shouldSkip(string $path): bool {
    $p = str_replace('\\', '/', $path);
    $skipParts = [
        '/vendor/', '/node_modules/', '/storage/', '/bootstrap/cache/',
        '/public/build/', '/public/hot', '/.git/', '/_backup', '/patch-backups/'
    ];
    foreach ($skipParts as $part) {
        if (str_contains($p, $part)) {
            return true;
        }
    }
    return false;
}

function allowedFile(string $file, array $allowedExtensions): bool {
    $name = basename($file);
    foreach ($allowedExtensions as $ext) {
        if ($ext === 'blade.php' && str_ends_with($name, '.blade.php')) return true;
        if ($ext !== 'blade.php' && str_ends_with($name, '.' . $ext)) return true;
    }
    return false;
}

function backupFile(string $root, string $backupRoot, string $file): void {
    $rel = relativePath($root, $file);
    $dest = $backupRoot . DIRECTORY_SEPARATOR . normalizePath($rel);
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    copy($file, $dest);
}

$changed = [];
$scanned = 0;

foreach ($targets as $target) {
    $full = $root . DIRECTORY_SEPARATOR . normalizePath($target);
    if (!is_dir($full)) continue;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $item) {
        if (!$item->isFile()) continue;
        $file = $item->getPathname();
        if (shouldSkip($file)) continue;
        if (!allowedFile($file, $allowedExtensions)) continue;

        $scanned++;
        $content = file_get_contents($file);
        if ($content === false) continue;

        $new = $content;
        foreach ($badPatterns as $pattern) {
            $new = str_replace($pattern, 'م', $new);
        }

        if ($new !== $content) {
            backupFile($root, $backupRoot, $file);
            file_put_contents($file, $new);
            $changed[] = relativePath($root, $file);
        }
    }
}

// تأكيد charset في layout الرئيسي إذا كان موجوداً
$layout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
if (is_file($layout)) {
    $content = file_get_contents($layout);
    if ($content !== false && !preg_match('/<meta\s+charset=["\']?utf-8["\']?/i', $content)) {
        backupFile($root, $backupRoot, $layout);
        $content = preg_replace('/<head[^>]*>/i', "$0\n    <meta charset=\"UTF-8\">", $content, 1, $count);
        if ($count > 0) {
            file_put_contents($layout, $content);
            $changed[] = relativePath($root, $layout) . ' (meta charset)';
        }
    }
}

if (empty($changed)) {
    echo "INFO: لم يتم العثور على رموز تالفة من الأنواع المعروفة داخل الملفات النشطة.\n";
} else {
    echo "DONE: تم إصلاح رموز الترميز التالفة باستبدالها بحرف الميم داخل الملفات النشطة.\n";
    echo "Scanned files: {$scanned}\n";
    echo "Changed files: " . count($changed) . "\n";
    echo "Backup: {$backupRoot}\n";
    echo "Files touched:\n";
    foreach ($changed as $file) {
        echo "- {$file}\n";
    }
}

echo "NEXT: php artisan view:clear && php artisan optimize:clear && php scripts/check_arabic_replacement_char_meem_fix.php\n";

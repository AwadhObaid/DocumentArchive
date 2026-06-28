<?php
/**
 * Arabic encoding deep restore V2 for DocumentArchive
 * - Fixes corrupted replacement markers that appeared in Arabic UI text (mostly the letter Meem: م)
 * - Adds/ensures UTF-8 meta tags in layouts
 * - Backs up touched files outside active Laravel paths
 */

$root = realpath(__DIR__ . '/..');
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: شغّل السكربت من داخل مشروع Laravel من المسار scripts.\n");
    exit(1);
}

$timestamp = date('Ymd_His');
$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'arabic-encoding-restore-v2-' . $timestamp;

$scanDirs = [
    'resources/views',
    'routes',
    'app/Http/Controllers',
    'app/Http/Middleware',
    'app/Providers',
    'app/Services',
    'app/View',
    'public/js',
    'public/css',
];

$textExtensions = ['php', 'blade.php', 'js', 'css', 'json', 'md', 'txt'];

function normalize_path(string $path): string {
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function is_text_file_to_process(string $file): bool {
    $base = basename($file);
    if (str_contains($base, '.bak') || str_contains($file, DIRECTORY_SEPARATOR . '_backup')) {
        return false;
    }
    $allowed = ['.php', '.blade.php', '.js', '.css', '.json', '.md', '.txt'];
    foreach ($allowed as $suffix) {
        if (str_ends_with(strtolower($file), $suffix)) {
            return true;
        }
    }
    return false;
}

function backup_file(string $root, string $backupRoot, string $file): void {
    $relative = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);
    $target = $backupRoot . DIRECTORY_SEPARATOR . $relative;
    $dir = dirname($target);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    copy($file, $target);
}

function repair_content(string $content): string {
    // If file contains invalid UTF-8 bytes, try a conservative Windows-1256 conversion fallback.
    if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
        $converted = @mb_convert_encoding($content, 'UTF-8', 'Windows-1256,UTF-8,ISO-8859-1');
        if (is_string($converted) && $converted !== '') {
            $content = $converted;
        }
    }

    // Common replacement-char representations produced by broken encoding conversions.
    $badMarkers = [
        "\xEF\xBF\xBD", // U+FFFD UTF-8 bytes
        '�',
        'ï¿½',
        '&#65533;',
        '&#xFFFD;',
        '&#xfffd;',
        '&amp;#65533;',
        '&amp;#xFFFD;',
        '&amp;#xfffd;',
    ];
    $content = str_replace($badMarkers, 'م', $content);

    // Extra targeted cleanup for phrases that commonly got damaged.
    $phraseMap = [
        'نظاام' => 'نظام',
        'نظامم' => 'نظام',
        'التحكمم' => 'التحكم',
        'الملفف' => 'الملف',
        'المرفقاتت' => 'المرفقات',
        'المحذوفاتت' => 'المحذوفات',
        'رقمم' => 'رقم',
        'الرسميةة' => 'الرسمية',
        'التأمينن' => 'التأمين',
        'قسمم' => 'قسم',
    ];
    $content = str_replace(array_keys($phraseMap), array_values($phraseMap), $content);

    return $content;
}

function ensure_utf8_meta(string $content): string {
    if (stripos($content, '<head') === false) {
        return $content;
    }
    if (preg_match('/<meta\s+charset\s*=\s*["\']?utf-8["\']?\s*\/?\s*>/i', $content)) {
        return $content;
    }
    return preg_replace('/(<head[^>]*>)/i', "$1\n    <meta charset=\"UTF-8\">", $content, 1) ?? $content;
}

$touched = [];
$scanned = 0;

foreach ($scanDirs as $dirRel) {
    $dir = $root . DIRECTORY_SEPARATOR . normalize_path($dirRel);
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $file = $fileInfo->getPathname();
        if (!is_text_file_to_process($file)) {
            continue;
        }
        $scanned++;
        $original = file_get_contents($file);
        if ($original === false) {
            continue;
        }
        $new = repair_content($original);
        if (str_contains(str_replace('\\', '/', $file), '/resources/views/layouts/')) {
            $new = ensure_utf8_meta($new);
        }
        if ($new !== $original) {
            backup_file($root, $backupRoot, $file);
            file_put_contents($file, $new);
            $touched[] = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);
        }
    }
}

// Also ensure the main layout has a UTF-8 meta tag if it exists.
$mainLayout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
if (is_file($mainLayout)) {
    $original = file_get_contents($mainLayout);
    $new = ensure_utf8_meta(repair_content($original));
    if ($new !== $original) {
        backup_file($root, $backupRoot, $mainLayout);
        file_put_contents($mainLayout, $new);
        $rel = ltrim(str_replace($root, '', $mainLayout), DIRECTORY_SEPARATOR);
        if (!in_array($rel, $touched, true)) {
            $touched[] = $rel;
        }
    }
}

echo "DONE: تم تنفيذ إصلاح الترميز العربي V2.\n";
echo "Scanned files: {$scanned}\n";
echo "Touched files: " . count($touched) . "\n";
if ($touched) {
    echo "Backup: {$backupRoot}\n";
    echo "Files touched:\n";
    foreach ($touched as $rel) {
        echo "- {$rel}\n";
    }
}
echo "NEXT: php artisan view:clear && php artisan optimize:clear && php scripts/check_arabic_encoding_restore_v2_deep_fix.php\n";

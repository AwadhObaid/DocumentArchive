<?php
$root = realpath(__DIR__ . '/..');
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: شغّل السكربت من داخل مشروع Laravel من المسار scripts.\n");
    exit(1);
}

$scanDirs = ['resources/views', 'routes', 'app/Http/Controllers', 'app/Http/Middleware', 'app/Providers', 'app/Services', 'app/View', 'public/js', 'public/css'];
$badPatterns = ['�', 'ï¿½', '&#65533;', '&#xFFFD;', '&amp;#65533;', '&amp;#xFFFD;'];
$badFiles = [];
$missingMeta = [];

function should_check_file(string $file): bool {
    if (str_contains($file, DIRECTORY_SEPARATOR . '_backup') || str_contains($file, DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR)) {
        return false;
    }
    foreach (['.php', '.blade.php', '.js', '.css', '.json', '.md', '.txt'] as $suffix) {
        if (str_ends_with(strtolower($file), $suffix)) return true;
    }
    return false;
}

foreach ($scanDirs as $dirRel) {
    $dir = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dirRel);
    if (!is_dir($dir)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $info) {
        if (!$info->isFile()) continue;
        $file = $info->getPathname();
        if (!should_check_file($file)) continue;
        $content = file_get_contents($file);
        if ($content === false) continue;
        foreach ($badPatterns as $p) {
            if (str_contains($content, $p)) {
                $badFiles[] = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR) . " يحتوي: {$p}";
                break;
            }
        }
        if (str_contains(str_replace('\\','/',$file), '/resources/views/layouts/') && stripos($content, '<head') !== false) {
            if (!preg_match('/<meta\s+charset\s*=\s*["\']?utf-8["\']?\s*\/?\s*>/i', $content)) {
                $missingMeta[] = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);
            }
        }
    }
}

if ($badFiles || $missingMeta) {
    echo "ERROR: لم يكتمل إصلاح الترميز العربي V2.\n";
    foreach ($badFiles as $f) echo "- ما زال يوجد رمز ترميز تالف في: {$f}\n";
    foreach ($missingMeta as $f) echo "- ناقص meta charset UTF-8 في: {$f}\n";
    exit(1);
}

echo "OK: تم إصلاح رموز الترميز التالفة وإضافة UTF-8 في ملفات layout.\n";

<?php
/**
 * فحص بقاء رموز الترميز التالفة في ملفات المشروع النشطة.
 */
$root = realpath(__DIR__ . '/..');
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: شغّل السكربت من جذر مشروع Laravel.\n");
    exit(1);
}

$targets = ['resources/views', 'app', 'routes', 'public/js', 'public/css'];
$patterns = ["\xEF\xBF\xBD", 'ï¿½', '&#65533;', '&#xFFFD;', '&amp;#65533;', '&amp;#xFFFD;'];

function normalizePath(string $path): string { return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path); }
function relativePath(string $root, string $path): string {
    $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_replace($root, '', $path);
}
function shouldSkip(string $path): bool {
    $p = str_replace('\\', '/', $path);
    foreach (['/vendor/', '/node_modules/', '/storage/', '/bootstrap/cache/', '/public/build/', '/.git/', '/_backup', '/patch-backups/'] as $part) {
        if (str_contains($p, $part)) return true;
    }
    return false;
}
function allowedFile(string $file): bool {
    $name = basename($file);
    return str_ends_with($name, '.blade.php') || str_ends_with($name, '.php') || str_ends_with($name, '.js') || str_ends_with($name, '.css');
}

$found = [];
foreach ($targets as $target) {
    $full = $root . DIRECTORY_SEPARATOR . normalizePath($target);
    if (!is_dir($full)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $item) {
        if (!$item->isFile()) continue;
        $file = $item->getPathname();
        if (shouldSkip($file) || !allowedFile($file)) continue;
        $content = file_get_contents($file);
        if ($content === false) continue;
        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                $found[] = relativePath($root, $file);
                break;
            }
        }
    }
}

$found = array_values(array_unique($found));
if (!empty($found)) {
    echo "ERROR: ما زالت توجد رموز ترميز تالفة في الملفات التالية:\n";
    foreach (array_slice($found, 0, 80) as $file) {
        echo "- {$file}\n";
    }
    if (count($found) > 80) {
        echo "... وعدد إضافي: " . (count($found) - 80) . "\n";
    }
    exit(1);
}

$layout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
if (is_file($layout)) {
    $content = file_get_contents($layout) ?: '';
    if (!preg_match('/<meta\s+charset=["\']?utf-8["\']?/i', $content)) {
        echo "ERROR: meta charset=UTF-8 غير موجود داخل resources/views/layouts/app.blade.php\n";
        exit(1);
    }
}

echo "OK: لا توجد رموز ترميز تالفة من الأنواع المعروفة داخل الملفات النشطة.\n";

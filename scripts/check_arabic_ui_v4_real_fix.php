<?php
/** Check Arabic UI V4 repair. Binary-safe. */
$root = dirname(__DIR__);

function normalize_path($path) { return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path); }
function has_bad_bytes($content) {
    $patterns = [
        "\xD8\xB8\xE2\x80\xA6", // ظ…
        "\xD8\xB8...",
        "\xEF\xBF\xBD",
        "\xC3\xAF\xC2\xBF\xC2\xBD",
        '&#65533;', '&#xFFFD;', '&amp;#65533;', '&amp;#xFFFD;',
    ];
    foreach ($patterns as $p) if (strpos($content, $p) !== false) return true;
    return false;
}
function file_should_scan($path) {
    $pathNorm = str_replace('\\', '/', $path);
    if (strpos($pathNorm, '/vendor/') !== false || strpos($pathNorm, '/storage/') !== false || strpos($pathNorm, '/node_modules/') !== false) return false;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['php','css','js','json','env'], true)) return true;
    if (str_ends_with($path, '.blade.php')) return true;
    return false;
}
$dirs = ['resources', 'app', 'routes', 'config', 'public/css', 'public/js'];
$bad = [];
$scanned = 0;
foreach ($dirs as $dir) {
    $full = $root . DIRECTORY_SEPARATOR . normalize_path($dir);
    if (!is_dir($full)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) continue;
        $file = $fileInfo->getPathname();
        if (!file_should_scan($file)) continue;
        $scanned++;
        $content = file_get_contents($file);
        if (has_bad_bytes($content)) $bad[] = ltrim(substr($file, strlen($root)), DIRECTORY_SEPARATOR);
    }
}
$layout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
$layoutOk = file_exists($layout) && strpos(file_get_contents($layout), 'arabic-no-truncate-v4.css') !== false && strpos(file_get_contents($layout), 'arabic-text-mojibake-v4.js') !== false;
$assetOk = file_exists($root . DIRECTORY_SEPARATOR . 'public/css/arabic-no-truncate-v4.css') && file_exists($root . DIRECTORY_SEPARATOR . 'public/js/arabic-text-mojibake-v4.js');

if (!$bad && $layoutOk && $assetOk) {
    echo "OK: Arabic UI V4 file check passed. Scanned files: {$scanned}\n";
    echo "NOTE: If Windows PowerShell Select-String still displays 'ظ…', run it with -Encoding UTF8 or use this PHP check as source of truth.\n";
    exit(0);
}

echo "ERROR: Arabic UI V4 check failed.\n";
if (!$assetOk) echo "- Missing V4 CSS/JS assets.\n";
if (!$layoutOk) echo "- V4 assets are not injected into resources/views/layouts/app.blade.php.\n";
if ($bad) {
    echo "- Files still containing bad byte patterns:\n";
    foreach ($bad as $f) echo "  * {$f}\n";
}
exit(1);

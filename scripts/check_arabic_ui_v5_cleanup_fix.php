<?php
/**
 * Check Arabic UI V5 cleanup.
 * Run from Laravel project root: php scripts/check_arabic_ui_v5_cleanup_fix.php
 */
$root = dirname(__DIR__);

function collect_files_check_v5(string $root): array
{
    $dirs = [
        $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
        $root . DIRECTORY_SEPARATOR . 'app',
        $root . DIRECTORY_SEPARATOR . 'routes',
        $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js',
        $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css',
    ];
    $files = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $path = $fileInfo->getPathname();
            $name = strtolower($fileInfo->getFilename());
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (str_ends_with($name, '.blade.php') || in_array($ext, ['php', 'js', 'css'], true)) {
                $files[] = $path;
            }
        }
    }
    return $files;
}

function rel_check_v5(string $root, string $path): string
{
    $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
    $path = str_replace('\\', '/', realpath($path) ?: $path);
    return ltrim(str_replace($root, '', $path), '/');
}

$bad = [
    "\xEF\xBF\xBD" => 'replacement-char',
    "\xC3\xAF\xC2\xBF\xC2\xBD" => 'latin-mojibake',
    "\xD8\xB8\xE2\x80\xA6" => 'zah-ellipsis',
    "\xD8\xB8" . chr(46) . chr(46) . chr(46) => 'zah-three-dots',
    '&' . '#65533;' => 'html-entity-decimal',
    '&' . '#xFFFD;' => 'html-entity-hex',
    '&' . '#xfffd;' => 'html-entity-hex-lower',
];

$found = [];
foreach (collect_files_check_v5($root) as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }
    foreach ($bad as $pattern => $label) {
        if (strpos($content, $pattern) !== false) {
            $found[] = rel_check_v5($root, $file) . ' [' . $label . ']';
            break;
        }
    }
}

$jsFile = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'arabic-text-mojibake-v4.js';
$layout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
$missing = [];
if (!is_file($jsFile)) {
    $missing[] = 'public/js/arabic-text-mojibake-v4.js';
}
if (is_file($layout)) {
    $layoutContent = file_get_contents($layout);
    if ($layoutContent !== false && strpos($layoutContent, 'arabic-text-mojibake-v4.js') === false) {
        $missing[] = 'layout script reference';
    }
}

if ($found || $missing) {
    echo "ERROR: Arabic UI V5 cleanup check failed.\n";
    foreach ($missing as $item) {
        echo "- Missing: {$item}\n";
    }
    if ($found) {
        echo "- Files still containing bad byte patterns:\n";
        foreach ($found as $item) {
            echo "  * {$item}\n";
        }
    }
    exit(1);
}

echo "OK: Arabic UI V5 cleanup passed. No active bad Arabic byte patterns found.\n";

<?php
/**
 * Check remaining replacement-character corruption.
 *
 * Run:
 *   php scripts/check_arabic_fffd_only.php
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

$replacementChar = "\xEF\xBF\xBD";       // �
$mojibake = "\xC3\xAF\xC2\xBF\xC2\xBD";  // ï¿½

$needles = [
    $replacementChar => '�',
    $mojibake => 'ï¿½',
    '&#65533;' => '&#65533;',
    '&#xFFFD;' => '&#xFFFD;',
    '&#xfffd;' => '&#xfffd;',
];

function should_skip_path_check(string $path): bool
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

function is_target_file_check(string $file): bool
{
    $name = basename($file);

    if (str_ends_with($name, '.blade.php')) {
        return true;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($ext, ['php', 'js', 'css'], true);
}

$found = [];

foreach ($targets as $target) {
    $targetPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);
    if (!is_dir($targetPath)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($targetPath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();

        if ($item->isDir() || should_skip_path_check($path) || !is_target_file_check($path)) {
            continue;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }

        foreach ($needles as $needle => $label) {
            if (strpos($content, $needle) !== false) {
                $relative = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
                $found[str_replace('\\', '/', $relative)][] = $label;
            }
        }
    }
}

if (!$found) {
    echo "OK: لا توجد رموز ترميز تالفة متبقية في الملفات النشطة.\n";
    exit(0);
}

echo "ERROR: ما زالت توجد رموز تالفة في الملفات التالية:\n";
foreach ($found as $file => $labels) {
    echo "- {$file}: " . implode(', ', array_unique($labels)) . "\n";
}
exit(1);

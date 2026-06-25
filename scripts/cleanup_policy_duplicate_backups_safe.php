<?php
$root = dirname(__DIR__);
$viewsDir = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';

function rrmdir_safe_policy(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = @scandir($dir);
    if ($items === false) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) rrmdir_safe_policy($path);
        else @unlink($path);
    }
    @rmdir($dir);
}

function collect_safe_policy(string $dir, array &$found): void
{
    $items = @scandir($dir);
    if ($items === false) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (!is_dir($path) || is_link($path)) continue;
        if (str_starts_with($item, '_backup_policy_duplicate')) {
            $found[] = $path;
            continue;
        }
        collect_safe_policy($path, $found);
    }
}

$found = [];
if (is_dir($viewsDir)) collect_safe_policy($viewsDir, $found);
$found = array_values(array_unique($found));
usort($found, fn($a, $b) => strlen($b) <=> strlen($a));
foreach ($found as $dir) rrmdir_safe_policy($dir);
echo "OK: تم حذف مجلدات backup الخاصة بتنبيه البوليصة: " . count($found) . "\n";

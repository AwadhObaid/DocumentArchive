<?php
function project_root(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 6; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan') && is_dir($dir . DIRECTORY_SEPARATOR . 'resources')) return $dir;
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    fwrite(STDERR, "ERROR: تأكد أنك داخل جذر مشروع Laravel.\n");
    exit(1);
}
function blade_files(string $dir): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) $files[] = $file->getPathname();
    }
    return $files;
}
$root = project_root();
$views = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
$found = [];
foreach (blade_files($views) as $file) {
    $c = file_get_contents($file);
    if ($c === false) continue;
    if (str_contains($c, 'DA_QR_SETTINGS_BIND_V2_START')) {
        $found[] = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
    }
}
$errors = [];
if (!$found) $errors[] = 'لم يتم العثور على بلوك DA_QR_SETTINGS_BIND_V2_START داخل أي ملف Blade.';
foreach ($found as $rel) {
    $c = file_get_contents($root . DIRECTORY_SEPARATOR . $rel);
    foreach ([
        "url('/documents/' . \$daQrDocument->id . '/qr.svg')",
        "qr_print_settings",
        "da-qr-settings-v2-box",
        "DA_QR_SETTINGS_BIND_V2_END"
    ] as $needle) {
        if (!str_contains($c, $needle)) $errors[] = "ناقص داخل {$rel}: {$needle}";
    }
}
foreach (['app','resources','routes'] as $rel) {
    $base = $root . DIRECTORY_SEPARATOR . $rel;
    if (!is_dir($base)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isDir() && str_starts_with($file->getFilename(), '_backup')) {
            $errors[] = 'ما زال يوجد مجلد backup: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }
}
if ($errors) {
    echo "ERROR: لم يكتمل ربط إعدادات QR بالطباعة V2.\n";
    foreach ($errors as $e) echo "- {$e}\n";
    exit(1);
}
echo "OK: ربط إعدادات QR بالطباعة V2 مكتمل.\n";
echo "FILES:\n- " . implode("\n- ", $found) . "\n";

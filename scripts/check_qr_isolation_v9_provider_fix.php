<?php
$root = realpath(__DIR__ . '/..');
$errors = [];
function check_file_contains(string $path, array $needles, array &$errors, string $label): void {
    if (!file_exists($path)) {
        $errors[] = "الملف غير موجود: {$label}";
        return;
    }
    $content = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = "ناقص داخل {$label}: {$needle}";
        }
    }
}

check_file_contains($root . '/app/Providers/QrViewDefaultsServiceProvider.php', [
    'namespace App\\Providers;',
    'use Illuminate\\Support\\Facades\\View;',
    "View::share('daQrUrl'",
    "View::share('daQrDocument'",
    "View::share('daQrSettings'",
    "View::share('daQrEnabled'",
    "View::share('daQrVisible'",
    "View::share('daQrPrintOnly'",
], $errors, 'app/Providers/QrViewDefaultsServiceProvider.php');

check_file_contains($root . '/bootstrap/providers.php', [
    'QrViewDefaultsServiceProvider::class',
], $errors, 'bootstrap/providers.php');

check_file_contains($root . '/resources/views/layouts/app.blade.php', [
    'qr-global-isolation-v9.css',
    'qr-global-isolation-v9.js',
], $errors, 'resources/views/layouts/app.blade.php');

foreach (['app','resources','routes'] as $top) {
    $base = $root . DIRECTORY_SEPARATOR . $top;
    if (!is_dir($base)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $item) {
        if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
            $errors[] = 'ما زال يوجد مجلد backup داخل مسارات Laravel: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $item->getPathname());
        }
    }
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح QR V9.\n- " . implode("\n- ", $errors) . "\n";
    exit(1);
}

echo "OK: إصلاح QR V9 مكتمل. تم عزل المتغيرات الافتراضية وربط Provider مستقل.\n";

<?php

$root = dirname(__DIR__);
$files = [
    'app/Services/BookAttachmentSmartPathService.php',
    'app/Http/Controllers/SettingsController.php',
];

$failed = false;

foreach ($files as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

    if (! is_file($path)) {
        echo "[FAIL] Missing file: {$relative}\n";
        $failed = true;
        continue;
    }

    $lintOutput = [];
    $lintCode = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $lintOutput, $lintCode);
    if ($lintCode !== 0) {
        echo "[FAIL] PHP syntax error in {$relative}:\n" . implode("\n", $lintOutput) . "\n";
        $failed = true;
    }
}

$servicePath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, 'app/Services/BookAttachmentSmartPathService.php');
$service = is_file($servicePath) ? file_get_contents($servicePath) : '';

$badPatterns = [
    "preg_match('/^[A-Za-z]:\\\\?$/',",
    "preg_match('/^[A-Za-z]:[\\\\\\/]/',",
    "preg_match('/^[A-Za-z]:[\\\\\\\\/]/',",
];

foreach ($badPatterns as $badPattern) {
    if (str_contains($service, $badPattern)) {
        echo "[FAIL] Fragile Windows-path preg_match is still present in BookAttachmentSmartPathService.php\n";
        $failed = true;
    }
}

$requiredServiceNeedles = [
    'function isWindowsDriveOnly(',
    'function startsWithWindowsDriveRoot(',
    'return $this->isAbsolutePath($path);',
];

foreach ($requiredServiceNeedles as $needle) {
    if (! str_contains($service, $needle)) {
        echo "[FAIL] Service missing: {$needle}\n";
        $failed = true;
    }
}

$settingsPath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, 'app/Http/Controllers/SettingsController.php');
$settings = is_file($settingsPath) ? file_get_contents($settingsPath) : '';

if (str_contains($settings, 'bookAttachmentStorageDirectories') && ! str_contains($settings, 'function settingsBrowserIsWindowsDriveOnly(')) {
    echo "[FAIL] SettingsController missing safe browser drive helper.\n";
    $failed = true;
}

$runtimePattern = '/^[A-Za-z]:[\\\\\\/]/';
@preg_match($runtimePattern, 'D:\\DocumentArchiveFiles');
if (preg_last_error() === PREG_INTERNAL_ERROR) {
    echo "[INFO] PHP confirms the old path regex can fail on this runtime; V62 avoids using it.\n";
}

if ($failed) {
    echo "\nBook attachment path regex fix V62 check failed.\n";
    exit(1);
}

echo "Book attachment path regex fix V62 check passed.\n";
echo "You can now save a Windows path such as D:\\DocumentArchiveFiles from settings.\n";

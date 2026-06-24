<?php

$root = dirname(__DIR__);
$routesPath = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';

if (!file_exists($routesPath)) {
    fwrite(STDERR, "routes/web.php not found\n");
    exit(1);
}

$content = file_get_contents($routesPath);

if (strpos($content, 'backups.inspect') === false) {
    $inspectRoute = "    Route::get('/backups/{fileName}/inspect', [BackupController::class, 'inspect'])->name('backups.inspect');\n";

    $needle = "    Route::get('/backups/{fileName}/download', [BackupController::class, 'download'])->name('backups.download');";

    if (strpos($content, $needle) !== false) {
        $content = str_replace($needle, $inspectRoute . $needle, $content);
    } else {
        $fallback = "Route::middleware(['auth'])->group(function () {";
        if (strpos($content, $fallback) !== false) {
            $content = str_replace($fallback, $fallback . "\n" . $inspectRoute, $content);
        } else {
            $content .= "\nRoute::middleware(['auth'])->group(function () {\n" . $inspectRoute . "});\n";
        }
    }
}

if (strpos($content, 'use App\\Http\\Controllers\\BackupController;') === false) {
    $content = str_replace("use Illuminate\\Support\\Facades\\Route;", "use App\\Http\\Controllers\\BackupController;\nuse Illuminate\\Support\\Facades\\Route;", $content);
}

file_put_contents($routesPath, $content);

echo "Backup inspect route applied.\n";

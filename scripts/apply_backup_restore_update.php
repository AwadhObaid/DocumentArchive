<?php

$root = dirname(__DIR__);
$routesPath = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';

if (!file_exists($routesPath)) {
    fwrite(STDERR, "routes/web.php not found\n");
    exit(1);
}

$content = file_get_contents($routesPath);

if (strpos($content, 'use App\\Http\\Controllers\\BackupController;') === false) {
    $content = str_replace(
        "use Illuminate\\Support\\Facades\\Route;",
        "use App\\Http\\Controllers\\BackupController;\nuse Illuminate\\Support\\Facades\\Route;",
        $content
    );
}

$restoreRoutes = <<<PHPROUTES
    Route::get('/backups/{fileName}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::post('/backups/{fileName}/restore/database', [BackupController::class, 'restoreDatabase'])->name('backups.restore.database');
    Route::post('/backups/{fileName}/restore/files', [BackupController::class, 'restoreFiles'])->name('backups.restore.files');
    Route::post('/backups/{fileName}/restore/full', [BackupController::class, 'restoreFull'])->name('backups.restore.full');

PHPROUTES;

if (strpos($content, 'backups.restore.database') === false) {
    $needles = [
        "    Route::get('/backups/{fileName}/download', [BackupController::class, 'download'])->name('backups.download');",
        "    Route::delete('/backups/{fileName}', [BackupController::class, 'destroy'])->name('backups.destroy');",
    ];

    $inserted = false;
    foreach ($needles as $needle) {
        if (strpos($content, $needle) !== false) {
            $content = str_replace($needle, $restoreRoutes . $needle, $content);
            $inserted = true;
            break;
        }
    }

    if (!$inserted) {
        $backupGroupStart = "Route::middleware(['auth'])->group(function () {";
        if (strpos($content, $backupGroupStart) !== false) {
            $content = str_replace($backupGroupStart, $backupGroupStart . "\n" . $restoreRoutes, $content);
        } else {
            $content .= "\nRoute::middleware(['auth'])->group(function () {\n" . $restoreRoutes . "});\n";
        }
    }
}

file_put_contents($routesPath, $content);

echo "Backup restore routes applied.\n";

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$webRoutesPath = Join-Path $root "routes\web.php"

if (!(Test-Path $webRoutesPath)) {
    throw "لم يتم العثور على الملف routes\web.php. تأكد أنك تفك الضغط داخل جذر مشروع DocumentArchive."
}

$routes = Get-Content -Raw -Encoding UTF8 $webRoutesPath

if ($routes -notmatch 'use\s+App\\Http\\Controllers\\BackupController;') {
    if ($routes -match 'use\s+Illuminate\\Support\\Facades\\Route;') {
        $routes = $routes -replace '(use\s+Illuminate\\Support\\Facades\\Route;\s*)', "`$1`r`nuse App\Http\Controllers\BackupController;`r`n"
    } elseif ($routes -match '<\?php') {
        $routes = [regex]::Replace($routes, '<\?php\s*', "<?php`r`n`r`nuse App\Http\Controllers\BackupController;`r`n", 1)
    } else {
        $routes = "<?php`r`n`r`nuse App\Http\Controllers\BackupController;`r`n`r`n" + $routes
    }
}

$backupRoutes = @'

// Backup routes
Route::middleware(['auth'])->group(function () {
    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups/database', [BackupController::class, 'createDatabaseBackup'])->name('backups.database');
    Route::post('/backups/files', [BackupController::class, 'createFilesBackup'])->name('backups.files');
    Route::post('/backups/full', [BackupController::class, 'createFullBackup'])->name('backups.full');
    Route::get('/backups/{fileName}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::delete('/backups/{fileName}', [BackupController::class, 'destroy'])->name('backups.destroy');
});
'@

if ($routes -notmatch "backups\.index") {
    $routes = $routes.TrimEnd() + "`r`n" + $backupRoutes + "`r`n"
}

Set-Content -Path $webRoutesPath -Value $routes -Encoding UTF8

Write-Host "Backup routes added to routes/web.php successfully." -ForegroundColor Green

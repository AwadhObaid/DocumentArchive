$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$controllerPath = Join-Path $root "app\Http\Controllers\BackupController.php"
$viewDir = Join-Path $root "resources\views\backups"
$webRoutesPath = Join-Path $root "routes\web.php"
$layoutPath = Join-Path $root "resources\views\layouts\app.blade.php"

New-Item -ItemType Directory -Force -Path (Split-Path -Parent $controllerPath) | Out-Null
New-Item -ItemType Directory -Force -Path $viewDir | Out-Null

Copy-Item -Force (Join-Path $PSScriptRoot "..\app\Http\Controllers\BackupController.php") $controllerPath
Copy-Item -Force (Join-Path $PSScriptRoot "..\resources\views\backups\index.blade.php") (Join-Path $viewDir "index.blade.php")

$routes = Get-Content -Raw -Encoding UTF8 $webRoutesPath

if ($routes -notmatch 'use App\Http\Controllers\BackupController;') {
    $routes = $routes -replace '(use App\Http\Controllers\[^;]+;)', "`$1`r`nuse App\Http\Controllers\BackupController;"
}

$backupRoutes = @'

// Backup routes
Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
Route::post('/backups/database', [BackupController::class, 'createDatabaseBackup'])->name('backups.database');
Route::post('/backups/files', [BackupController::class, 'createFilesBackup'])->name('backups.files');
Route::post('/backups/full', [BackupController::class, 'createFullBackup'])->name('backups.full');
Route::get('/backups/{fileName}/download', [BackupController::class, 'download'])->name('backups.download');
Route::delete('/backups/{fileName}', [BackupController::class, 'destroy'])->name('backups.destroy');
'@

if ($routes -notmatch "backups\.index") {
    if ($routes -match "Route::resource\('documents'") {
        $routes = $routes -replace "(?=Route::resource\('documents')", $backupRoutes + "`r`n"
    } else {
        $routes += "`r`n" + $backupRoutes + "`r`n"
    }
}

Set-Content -Path $webRoutesPath -Value $routes -Encoding UTF8

if (Test-Path $layoutPath) {
    $layout = Get-Content -Raw -Encoding UTF8 $layoutPath
    if ($layout -notmatch "route\('backups\.index'\)") {
        $backupLink = @'
<a href="{{ route('backups.index') }}" class="side-nav-link {{ request()->routeIs('backups.*') ? 'active' : '' }}">💾 النسخ الاحتياطي</a>
'@
        if ($layout -match "route\('settings\.edit'\)") {
            $layout = $layout -replace "(<a[^>]+route\('settings\.edit'\)[\s\S]*?</a>)", "`$1`r`n" + $backupLink
        } elseif ($layout -match "</nav>") {
            $layout = $layout -replace "</nav>", $backupLink + "`r`n</nav>"
        } else {
            $layout += "`r`n" + $backupLink
        }
        Set-Content -Path $layoutPath -Value $layout -Encoding UTF8
    }
}

Write-Host "Backup update applied successfully." -ForegroundColor Green

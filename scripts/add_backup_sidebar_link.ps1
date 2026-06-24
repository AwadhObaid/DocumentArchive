$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$layoutPath = Join-Path $root "resources\views\layouts\app.blade.php"

if (-not (Test-Path $layoutPath)) {
    throw "لم يتم العثور على الملف: $layoutPath"
}

$layout = Get-Content -Raw -Encoding UTF8 $layoutPath

# If the link already exists, do nothing.
if ($layout -match "route\('backups\.index'\)") {
    Write-Host "رابط النسخ الاحتياطي موجود مسبقاً في السايد بار." -ForegroundColor Yellow
    exit 0
}

# Backup current layout before editing.
$backupPath = $layoutPath + ".before-backup-sidebar-" + (Get-Date -Format "yyyyMMddHHmmss") + ".bak"
Copy-Item -Force $layoutPath $backupPath

$backupLink = '            <a href="{{ route(''backups.index'') }}" class="{{ request()->routeIs(''backups.*'') ? ''active'' : '''' }}">💾 النسخ الاحتياطي</a>'

# Prefer placing before settings. If not found, place before users. If not found, before closing nav.
$settingsPattern = '(?m)^\s*<a\s+href="\{\{\s*route\(''settings\.edit''\)\s*\}\}"[\s\S]*?</a>\s*$'
$usersPattern = '(?m)^\s*@if\(auth\(\)->user\(\)\?->role === ''admin''\)'

if ($layout -match $settingsPattern) {
    $layout = [regex]::Replace($layout, $settingsPattern, $backupLink + "`r`n" + '$0', 1)
} elseif ($layout -match $usersPattern) {
    $layout = [regex]::Replace($layout, $usersPattern, $backupLink + "`r`n`r`n" + '$0', 1)
} elseif ($layout -match '</nav>') {
    $layout = $layout -replace '</nav>', ($backupLink + "`r`n        </nav>")
} else {
    throw "لم أستطع تحديد موضع السايد بار داخل app.blade.php. أرسل صورة من الملف إذا تكرر ذلك."
}

Set-Content -Path $layoutPath -Value $layout -Encoding UTF8

Write-Host "تمت إضافة زر النسخ الاحتياطي إلى السايد بار بنجاح." -ForegroundColor Green
Write-Host "تم حفظ نسخة احتياطية من ملف الواجهة هنا:" -ForegroundColor Cyan
Write-Host $backupPath -ForegroundColor Cyan

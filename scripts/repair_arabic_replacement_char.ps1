# إصلاح مباشر لرمز الترميز التالف الذي يظهر بالشكل: � أو ï¿½
# مخصص للحالة الحالية في المشروع حيث الرمز التالف يمثل غالباً حرف: م
# شغّل هذا الملف من جذر المشروع:
# powershell -ExecutionPolicy Bypass -File scripts\repair_arabic_replacement_char.ps1

$ErrorActionPreference = "Stop"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$root = Resolve-Path (Join-Path $scriptDir "..")
$root = $root.Path

$targets = @(
    "resources\views",
    "app",
    "routes",
    "public\js",
    "public\css"
)

$extensions = @("*.blade.php", "*.php", "*.js", "*.css")
$badTokens = @(
    ([string][char]0xFFFD),
    "ï¿½",
    "&#65533;",
    "&#xFFFD;",
    "&amp;#65533;",
    "&amp;#xFFFD;"
)

$replacement = "م"
$backupRoot = Join-Path $root ("storage\app\private\patch-backups\arabic-fffd-direct-" + (Get-Date -Format "yyyyMMdd_HHmmss"))
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

$utf8Read = New-Object System.Text.UTF8Encoding($false, $false)
$utf8Write = New-Object System.Text.UTF8Encoding($false)

$scanned = 0
$changed = 0
$changedFiles = New-Object System.Collections.Generic.List[string]

foreach ($target in $targets) {
    $targetPath = Join-Path $root $target
    if (-not (Test-Path $targetPath)) { continue }

    $files = Get-ChildItem -Path $targetPath -Recurse -File -Include $extensions -ErrorAction SilentlyContinue |
        Where-Object {
            $_.FullName -notmatch "\\vendor\\" -and
            $_.FullName -notmatch "\\storage\\" -and
            $_.FullName -notmatch "\\node_modules\\" -and
            $_.FullName -notmatch "\\patch-backups\\" -and
            $_.FullName -notmatch "\\_backup"
        }

    foreach ($file in $files) {
        $scanned++
        $content = [System.IO.File]::ReadAllText($file.FullName, $utf8Read)
        $newContent = $content

        foreach ($token in $badTokens) {
            if ([string]::IsNullOrEmpty($token)) { continue }
            $newContent = $newContent.Replace($token, $replacement)
        }

        if ($newContent -ne $content) {
            $relative = $file.FullName.Substring($root.Length).TrimStart('\', '/')
            $backupPath = Join-Path $backupRoot $relative
            $backupDir = Split-Path -Parent $backupPath
            New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
            Copy-Item -Path $file.FullName -Destination $backupPath -Force

            [System.IO.File]::WriteAllText($file.FullName, $newContent, $utf8Write)
            $changed++
            $changedFiles.Add($relative) | Out-Null
        }
    }
}

Write-Host "DONE: Arabic replacement characters repaired." -ForegroundColor Green
Write-Host "Scanned files: $scanned"
Write-Host "Changed files: $changed"
Write-Host "Backup: $backupRoot"

if ($changedFiles.Count -gt 0) {
    Write-Host "Files changed:" -ForegroundColor Cyan
    $changedFiles | ForEach-Object { Write-Host "- $_" }
} else {
    Write-Host "No files needed changes." -ForegroundColor Yellow
}

Write-Host "NEXT: php artisan view:clear && php artisan optimize:clear" -ForegroundColor Cyan

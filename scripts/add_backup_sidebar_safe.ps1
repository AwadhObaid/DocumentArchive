$ErrorActionPreference = "Stop"

$projectRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
$layoutPath = Join-Path $projectRoot "resources\views\layouts\app.blade.php"

if (!(Test-Path $layoutPath)) {
    throw "Layout file not found: $layoutPath"
}

$content = Get-Content -Raw -Encoding UTF8 $layoutPath

if ($content.Contains("BACKUP-SIDEBAR-LINK")) {
    Write-Host "Backup sidebar link already exists." -ForegroundColor Yellow
    exit 0
}

$backupBlock = @'

{{-- BACKUP-SIDEBAR-LINK --}}
<a href="{{ url('/backups') }}" class="sidebar-link {{ request()->is('backups*') ? 'active' : '' }}">
    <span class="sidebar-icon">&#128190;</span>
    <span>&#1575;&#1604;&#1606;&#1587;&#1582; &#1575;&#1604;&#1575;&#1581;&#1578;&#1610;&#1575;&#1591;&#1610;</span>
</a>
'@

$updated = $null

# Preferred: insert before the settings link if present.
$settingsIndex = $content.IndexOf("/settings")
if ($settingsIndex -lt 0) { $settingsIndex = $content.IndexOf("settings") }

if ($settingsIndex -ge 0) {
    $aStart = $content.LastIndexOf("<a", $settingsIndex)
    if ($aStart -ge 0) {
        $updated = $content.Insert($aStart, $backupBlock + "`r`n")
    }
}

# Fallback 1: insert before closing aside.
if ($null -eq $updated) {
    $asideEnd = $content.LastIndexOf("</aside>")
    if ($asideEnd -ge 0) {
        $updated = $content.Insert($asideEnd, $backupBlock + "`r`n")
    }
}

# Fallback 2: insert before closing nav.
if ($null -eq $updated) {
    $navEnd = $content.LastIndexOf("</nav>")
    if ($navEnd -ge 0) {
        $updated = $content.Insert($navEnd, $backupBlock + "`r`n")
    }
}

# Fallback 3: append to file so the developer can move it manually if needed.
if ($null -eq $updated) {
    $updated = $content + "`r`n" + $backupBlock + "`r`n"
    Write-Host "Warning: sidebar location was not detected; block appended to the file." -ForegroundColor Yellow
}

Set-Content -Path $layoutPath -Value $updated -Encoding UTF8

Write-Host "Backup sidebar link added successfully." -ForegroundColor Green
Write-Host "Run: php artisan optimize:clear" -ForegroundColor Cyan

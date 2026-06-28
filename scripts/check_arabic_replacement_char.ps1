# فحص بقايا رموز الترميز التالفة
# شغّل من جذر المشروع:
# powershell -ExecutionPolicy Bypass -File scripts\check_arabic_replacement_char.ps1

$ErrorActionPreference = "Stop"
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$root = Resolve-Path (Join-Path $scriptDir "..")
$root = $root.Path

$paths = @(
    "resources\views",
    "app",
    "routes",
    "public\js",
    "public\css"
)
$extensions = @("*.blade.php", "*.php", "*.js", "*.css")
$badTokens = @(([string][char]0xFFFD), "ï¿½", "&#65533;", "&#xFFFD;", "&amp;#65533;", "&amp;#xFFFD;")
$utf8Read = New-Object System.Text.UTF8Encoding($false, $false)

$found = @()
foreach ($p in $paths) {
    $dir = Join-Path $root $p
    if (-not (Test-Path $dir)) { continue }
    $files = Get-ChildItem -Path $dir -Recurse -File -Include $extensions -ErrorAction SilentlyContinue |
        Where-Object {
            $_.FullName -notmatch "\\vendor\\" -and
            $_.FullName -notmatch "\\storage\\" -and
            $_.FullName -notmatch "\\node_modules\\" -and
            $_.FullName -notmatch "\\patch-backups\\" -and
            $_.FullName -notmatch "\\_backup"
        }

    foreach ($file in $files) {
        $content = [System.IO.File]::ReadAllText($file.FullName, $utf8Read)
        foreach ($token in $badTokens) {
            if ($content.Contains($token)) {
                $relative = $file.FullName.Substring($root.Length).TrimStart('\', '/')
                $found += $relative
                break
            }
        }
    }
}

if ($found.Count -eq 0) {
    Write-Host "OK: No corrupted Arabic replacement characters found." -ForegroundColor Green
    exit 0
}

Write-Host "ERROR: Corrupted Arabic replacement characters remain:" -ForegroundColor Red
$found | Sort-Object -Unique | ForEach-Object { Write-Host "- $_" }
exit 1

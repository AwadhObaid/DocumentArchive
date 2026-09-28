[CmdletBinding()]
param(
    [string]$PhpExe = 'php'
)

$ErrorActionPreference = 'Stop'

$php = Get-Command $PhpExe -ErrorAction Stop
Write-Host "PHP: $($php.Source)"

$ini = & $PhpExe --ini
$iniText = ($ini | Out-String)
Write-Host $iniText.Trim()

$keys = @('upload_max_filesize', 'post_max_size', 'max_file_uploads', 'max_input_time', 'max_execution_time')
$values = @{}
foreach ($key in $keys) {
    $raw = & $PhpExe -r "echo ini_get('$key');"
    $values[$key] = if ($null -ne $raw) { $raw.Trim() } else { '' }
}

Write-Host ""
Write-Host "Current PHP upload-related limits:"
$values.GetEnumerator() | Sort-Object Name | ForEach-Object {
    Write-Host ("  {0} = {1}" -f $_.Key, $_.Value)
}

function Convert-ToBytes([string]$Value) {
    if ([string]::IsNullOrWhiteSpace($Value)) { return 0 }
    $v = $Value.Trim()
    if ($v -eq '-1') { return [long]::MaxValue }
    if ($v -match '^([0-9]+(?:\.[0-9]+)?)\s*([KMG])?B?$') {
        $n = [double]$Matches[1]
        $unit = if ($Matches.Count -gt 2 -and $Matches[2]) { $Matches[2] } else { '' }
        switch ($unit.ToUpperInvariant()) {
            'K' { return [long]($n * 1KB) }
            'M' { return [long]($n * 1MB) }
            'G' { return [long]($n * 1GB) }
            default { return [long]$n }
        }
    }
    return 0
}

$requiredUpload = 50MB
$requiredPost = 256MB

$uploadBytes = Convert-ToBytes $values['upload_max_filesize']
$postBytes = Convert-ToBytes $values['post_max_size']

$ok = $true

if ($uploadBytes -lt $requiredUpload) {
    Write-Host "[FAIL] upload_max_filesize must be at least 50M." -ForegroundColor Red
    $ok = $false
} else {
    Write-Host "[OK] upload_max_filesize is sufficient for a 50 MB individual attachment." -ForegroundColor Green
}

if ($postBytes -lt $requiredPost) {
    Write-Host "[WARN] post_max_size is below the recommended 256M. Multiple large attachments in one request may be rejected." -ForegroundColor Yellow
} else {
    Write-Host "[OK] post_max_size is at least 256M." -ForegroundColor Green
}

if (-not $ok) {
    Write-Host ""
    Write-Host "Recommended php.ini values:"
    Write-Host "  upload_max_filesize = 64M"
    Write-Host "  post_max_size = 256M"
    Write-Host "  max_input_time = 300"
    Write-Host "  max_execution_time = 300"
    exit 1
}

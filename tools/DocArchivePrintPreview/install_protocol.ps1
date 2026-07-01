# Register docarchive-print:// protocol for the current Windows user.
# Run after build_release.ps1.
$ErrorActionPreference = "Stop"

$exePath = Join-Path $PSScriptRoot "bin\Release\DocArchivePrintPreview.exe"

if (!(Test-Path $exePath)) {
    Write-Host "Application was not found:" -ForegroundColor Red
    Write-Host $exePath -ForegroundColor Yellow
    Write-Host "Run first: .\build_release.ps1" -ForegroundColor Cyan
    exit 1
}

$exePath = (Resolve-Path $exePath).Path
$command = '"' + $exePath + '" "%1"'

cmd /c reg add "HKCU\Software\Classes\docarchive-print" /ve /d "URL:DocArchive Print Preview Protocol" /f | Out-Null
cmd /c reg add "HKCU\Software\Classes\docarchive-print" /v "URL Protocol" /d "" /f | Out-Null
cmd /c reg add "HKCU\Software\Classes\docarchive-print\DefaultIcon" /ve /d "`"$exePath`",0" /f | Out-Null
cmd /c reg add "HKCU\Software\Classes\docarchive-print\shell\open\command" /ve /d "$command" /f | Out-Null
cmd /c reg add "HKCU\Software\Microsoft\Internet Explorer\Main\FeatureControl\FEATURE_BROWSER_EMULATION" /v "DocArchivePrintPreview.exe" /t REG_DWORD /d 11001 /f | Out-Null

Write-Host "Protocol registered successfully." -ForegroundColor Green
Write-Host "docarchive-print://" -ForegroundColor Yellow
Write-Host "Executable:" -ForegroundColor Cyan
Write-Host $exePath -ForegroundColor Yellow

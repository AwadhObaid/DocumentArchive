# Remove docarchive-print:// protocol registration for the current Windows user.
$ErrorActionPreference = "Stop"

cmd /c reg delete "HKCU\Software\Classes\docarchive-print" /f | Out-Null
cmd /c reg delete "HKCU\Software\Microsoft\Internet Explorer\Main\FeatureControl\FEATURE_BROWSER_EMULATION" /v "DocArchivePrintPreview.exe" /f 2>$null | Out-Null

Write-Host "Protocol removed successfully." -ForegroundColor Green

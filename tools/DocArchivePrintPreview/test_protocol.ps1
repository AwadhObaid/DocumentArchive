# Test docarchive-print:// protocol.
$ErrorActionPreference = "Stop"

$testUrl = "https://www.mod.gov.kw/resources/skins/EformImages/000000/230000-12.htm"
$encodedUrl = [System.Uri]::EscapeDataString($testUrl)
$encodedTitle = [System.Uri]::EscapeDataString("نموذج اختبار")
$protocolUrl = "docarchive-print://preview?url=$encodedUrl&title=$encodedTitle"

Write-Host "Opening:" -ForegroundColor Cyan
Write-Host $protocolUrl -ForegroundColor Yellow
Start-Process $protocolUrl

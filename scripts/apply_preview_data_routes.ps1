$webPath = "routes\web.php"

if (!(Test-Path $webPath)) {
    Write-Host "routes/web.php not found. Run this script from Laravel project root." -ForegroundColor Red
    exit 1
}

$content = Get-Content $webPath -Raw

if ($content -notmatch "AttachmentPreviewController") {
    $content = $content -replace "use Illuminate\\Support\\Facades\\Route;\r?\n", "use Illuminate\Support\Facades\Route;`r`nuse App\Http\Controllers\AttachmentPreviewController;`r`n"
}

# Remove old preview route if present.
$content = [regex]::Replace(
    $content,
    "Route::get\('/attachments/\{attachment\}/preview'.*?->name\('attachments\.preview'\);\s*",
    "",
    [System.Text.RegularExpressions.RegexOptions]::Singleline
)

# Remove old data route if present.
$content = [regex]::Replace(
    $content,
    "Route::get\('/attachments/\{attachment\}/data'.*?->name\('attachments\.data'\);\s*",
    "",
    [System.Text.RegularExpressions.RegexOptions]::Singleline
)

$newRoutes = @"

Route::get('/attachments/{attachment}/preview', [AttachmentPreviewController::class, 'preview'])
    ->name('attachments.preview');

Route::get('/attachments/{attachment}/data', [AttachmentPreviewController::class, 'data'])
    ->name('attachments.data');

"@

if ($content -match "Route::get\('/attachments/\{attachment\}/download'") {
    $content = $content -replace "Route::get\('/attachments/\{attachment\}/download'", $newRoutes + "Route::get('/attachments/{attachment}/download'"
} elseif ($content -match "Route::resource\('documents'") {
    $content = $content -replace "Route::resource\('documents'", $newRoutes + "Route::resource('documents'"
} else {
    $content += $newRoutes
}

Set-Content $webPath $content -Encoding UTF8

Write-Host "Preview routes applied successfully." -ForegroundColor Green

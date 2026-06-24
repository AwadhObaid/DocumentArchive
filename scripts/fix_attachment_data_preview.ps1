# Fix attachmentData method and attachment routes for DocumentArchive
# Run from project root:
# powershell -ExecutionPolicy Bypass -File scripts\fix_attachment_data_preview.ps1

$ErrorActionPreference = "Stop"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$root = Split-Path -Parent $scriptDir

$controllerPath = Join-Path $root "app\Http\Controllers\DocumentController.php"
$routesPath = Join-Path $root "routes\web.php"

if (!(Test-Path $controllerPath)) {
    throw "DocumentController.php not found at $controllerPath"
}

if (!(Test-Path $routesPath)) {
    throw "routes/web.php not found at $routesPath"
}

$content = Get-Content $controllerPath -Raw -Encoding UTF8

# Ensure required use statements exist
if ($content -notmatch "use\s+App\\Models\\DocumentAttachment;") {
    $content = $content -replace "namespace App\\Http\\Controllers;\s*", "namespace App\Http\Controllers;`r`n`r`nuse App\Models\DocumentAttachment;`r`n"
}

if ($content -notmatch "use\s+Illuminate\\Support\\Facades\\Storage;") {
    $content = $content -replace "namespace App\\Http\\Controllers;\s*", "namespace App\Http\Controllers;`r`n`r`nuse Illuminate\Support\Facades\Storage;`r`n"
}

if ($content -notmatch "function\s+attachmentData\s*\(") {
$method = @'
    public function attachmentData(DocumentAttachment $attachment)
    {
        $disk = Storage::disk($attachment->disk);

        if (!$disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        $extension = strtolower(
            $attachment->extension ?: pathinfo($attachment->original_name, PATHINFO_EXTENSION)
        );

        $mimeType = match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => $attachment->mime_type ?: 'application/octet-stream',
        };

        $contents = $disk->get($attachment->file_path);

        return response()->json([
            'id' => $attachment->id,
            'name' => $attachment->original_name,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => (int) $attachment->file_size,
            'base64' => base64_encode($contents),
        ]);
    }


'@

    $marker = "    public function inlineAttachment(DocumentAttachment `$attachment)"
    $idx = $content.IndexOf($marker)

    if ($idx -lt 0) {
        $marker = "    public function downloadAttachment(DocumentAttachment `$attachment)"
        $idx = $content.IndexOf($marker)
    }

    if ($idx -lt 0) {
        $lastBrace = $content.LastIndexOf("}")
        if ($lastBrace -lt 0) {
            throw "Unable to find insertion point in DocumentController.php"
        }
        $content = $content.Insert($lastBrace, "`r`n" + $method + "`r`n")
    } else {
        $content = $content.Insert($idx, $method)
    }

    Set-Content -Path $controllerPath -Value $content -Encoding UTF8
    Write-Host "attachmentData method added to DocumentController.php" -ForegroundColor Green
} else {
    Write-Host "attachmentData method already exists. Skipped controller insert." -ForegroundColor Yellow
}

$routes = Get-Content $routesPath -Raw -Encoding UTF8

$routePreview = "Route::get('/attachments/{attachment}/preview', [DocumentController::class, 'previewAttachment'])`r`n    ->name('attachments.preview');"
$routeData = "Route::get('/attachments/{attachment}/data', [DocumentController::class, 'attachmentData'])`r`n    ->name('attachments.data');"
$routeDownload = "Route::get('/attachments/{attachment}/download', [DocumentController::class, 'downloadAttachment'])`r`n    ->name('attachments.download');"
$routeInline = "Route::get('/attachments/{attachment}/inline', [DocumentController::class, 'inlineAttachment'])`r`n    ->name('attachments.inline');"

# Insert missing routes before Route::resource('documents'...
$insertBlock = ""
if ($routes -notmatch "attachments/\{attachment\}/preview") {
    $insertBlock += $routePreview + "`r`n`r`n"
}
if ($routes -notmatch "attachments/\{attachment\}/data") {
    $insertBlock += $routeData + "`r`n`r`n"
}
if ($routes -notmatch "attachments/\{attachment\}/download") {
    $insertBlock += $routeDownload + "`r`n`r`n"
}
if ($routes -notmatch "attachments/\{attachment\}/inline") {
    $insertBlock += $routeInline + "`r`n`r`n"
}

if ($insertBlock.Length -gt 0) {
    $markerRoute = "Route::resource('documents', DocumentController::class);"
    $idxRoute = $routes.IndexOf($markerRoute)

    if ($idxRoute -ge 0) {
        $routes = $routes.Insert($idxRoute, $insertBlock)
    } else {
        $routes += "`r`n" + $insertBlock
    }

    Set-Content -Path $routesPath -Value $routes -Encoding UTF8
    Write-Host "Missing attachment routes added to routes/web.php" -ForegroundColor Green
} else {
    Write-Host "Attachment routes already exist. Skipped routes update." -ForegroundColor Yellow
}

Write-Host "Preview data fix completed." -ForegroundColor Green

# Build DocArchivePrintPreview.exe.
# ASCII-only script to avoid Windows PowerShell encoding issues.
# This script uses csc.exe directly first, then falls back to MSBuild.
$ErrorActionPreference = "Stop"

$projectFile = Join-Path $PSScriptRoot "DocArchivePrintPreview.csproj"
$sourceFile = Join-Path $PSScriptRoot "Program.cs"
$outputDir = Join-Path $PSScriptRoot "bin\Release"
$exePath = Join-Path $outputDir "DocArchivePrintPreview.exe"

if (!(Test-Path $sourceFile)) {
    throw "Source file not found: $sourceFile"
}

if (!(Test-Path $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
}

function Get-CscPath {
    $fallbacks = @(
        "$env:WINDIR\Microsoft.NET\Framework64\v4.0.30319\csc.exe",
        "$env:WINDIR\Microsoft.NET\Framework\v4.0.30319\csc.exe"
    )

    foreach ($path in $fallbacks) {
        if (Test-Path $path) {
            return $path
        }
    }

    return $null
}

function Get-MSBuildPath {
    $vswhere = Join-Path ${env:ProgramFiles(x86)} "Microsoft Visual Studio\Installer\vswhere.exe"

    if (Test-Path $vswhere) {
        $found = & $vswhere -latest -requires Microsoft.Component.MSBuild -find "MSBuild\**\Bin\MSBuild.exe" 2>$null | Select-Object -First 1
        if ($found -and (Test-Path $found)) {
            return $found
        }
    }

    $fallbacks = @(
        "$env:ProgramFiles\Microsoft Visual Studio\2022\Community\MSBuild\Current\Bin\MSBuild.exe",
        "$env:ProgramFiles\Microsoft Visual Studio\2022\Professional\MSBuild\Current\Bin\MSBuild.exe",
        "$env:ProgramFiles\Microsoft Visual Studio\2022\Enterprise\MSBuild\Current\Bin\MSBuild.exe",
        "$env:WINDIR\Microsoft.NET\Framework64\v4.0.30319\MSBuild.exe",
        "$env:WINDIR\Microsoft.NET\Framework\v4.0.30319\MSBuild.exe"
    )

    foreach ($path in $fallbacks) {
        if (Test-Path $path) {
            return $path
        }
    }

    return $null
}

$csc = Get-CscPath
if ($csc) {
    Write-Host "CSC:" $csc -ForegroundColor Cyan

    & $csc `
        /nologo `
        /target:winexe `
        /platform:anycpu `
        /optimize+ `
        /out:$exePath `
        /reference:System.dll `
        /reference:System.Core.dll `
        /reference:System.Drawing.dll `
        /reference:System.Web.dll `
        /reference:System.Windows.Forms.dll `
        $sourceFile

    if ($LASTEXITCODE -eq 0 -and (Test-Path $exePath)) {
        Write-Host "Build completed successfully:" -ForegroundColor Green
        Write-Host $exePath -ForegroundColor Yellow
        exit 0
    }

    Write-Host "Direct CSC build failed. Trying MSBuild..." -ForegroundColor Yellow
}

if (!(Test-Path $projectFile)) {
    throw "Project file not found: $projectFile"
}

$msbuild = Get-MSBuildPath
if (!$msbuild) {
    throw "Neither csc.exe nor MSBuild.exe was found. Install .NET Framework 4.x Developer Pack or Visual Studio Build Tools."
}

Write-Host "MSBuild:" $msbuild -ForegroundColor Cyan

& $msbuild $projectFile /t:Build /p:Configuration=Release /p:Platform=AnyCPU /p:OutputPath="bin\Release\"

if ($LASTEXITCODE -ne 0) {
    throw "MSBuild failed with exit code $LASTEXITCODE"
}

if (!(Test-Path $exePath)) {
    throw "Build failed. Output file was not found: $exePath"
}

Write-Host "Build completed successfully:" -ForegroundColor Green
Write-Host $exePath -ForegroundColor Yellow

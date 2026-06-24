$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
$layoutPath = Join-Path $projectRoot "resources\views\layouts\app.blade.php"
$cssPath = Join-Path $projectRoot "public\css\app.css"

if (!(Test-Path $layoutPath)) {
    throw "لم يتم العثور على الملف: $layoutPath"
}

$content = Get-Content -Path $layoutPath -Raw -Encoding UTF8

# نضيف رابط جديد بعلامة مميزة حتى لا نعتمد على تحديثات سابقة ربما أضيفت في مكان مخفي
if ($content -match "BACKUP-SIDEBAR-V2") {
    Write-Host "Backup sidebar link V2 already exists." -ForegroundColor Yellow
} else {
    $backupLink = @'

{{-- BACKUP-SIDEBAR-V2 --}}
<a href="{{ url('/backups') }}" class="sidebar-link sidebar-backup-link {{ request()->is('backups*') ? 'active' : '' }}">
    <span class="sidebar-icon">💾</span>
    <span class="sidebar-text">النسخ الاحتياطي</span>
</a>
'@

    function Insert-AfterRouteLink {
        param(
            [string]$Text,
            [string]$RoutePattern,
            [string]$InsertText
        )

        $match = [regex]::Match($Text, $RoutePattern)
        if (!$match.Success) {
            return $null
        }

        $start = $match.Index
        $endAnchor = $Text.IndexOf("</a>", $start)
        if ($endAnchor -lt 0) {
            return $null
        }

        $insertAt = $endAnchor + 4
        return $Text.Insert($insertAt, $InsertText)
    }

    $patterns = @(
        "route\('activity-logs\.index'\)",
        "route\('settings\.edit'\)",
        "route\('settings\.index'\)",
        "route\('document-types\.index'\)",
        "route\('departments\.index'\)",
        "route\('documents\.index'\)",
        "url\('/documents'\)",
        "url\('/activity-logs'\)"
    )

    $updated = $null
    foreach ($pattern in $patterns) {
        $updated = Insert-AfterRouteLink -Text $content -RoutePattern $pattern -InsertText $backupLink
        if ($null -ne $updated) {
            break
        }
    }

    if ($null -eq $updated) {
        # إذا لم نجد رابطاً معروفاً، نحاول الإضافة قبل نهاية القائمة الجانبية
        $asideEnd = $content.IndexOf("</aside>")
        if ($asideEnd -ge 0) {
            $updated = $content.Insert($asideEnd, $backupLink)
        } else {
            $navEnd = $content.IndexOf("</nav>")
            if ($navEnd -ge 0) {
                $updated = $content.Insert($navEnd, $backupLink)
            } else {
                throw "لم أستطع تحديد مكان القائمة الجانبية داخل app.blade.php. أرسل صورة من الملف وسأعدله لك يدوياً."
            }
        }
    }

    Set-Content -Path $layoutPath -Value $updated -Encoding UTF8
    Write-Host "Backup sidebar link added to app.blade.php" -ForegroundColor Green
}

# إضافة CSS بسيط لضمان ظهور الرابط حتى لو اختلفت كلاس القائمة
if (Test-Path $cssPath) {
    $css = Get-Content -Path $cssPath -Raw -Encoding UTF8

    if ($css -notmatch "BACKUP-SIDEBAR-V2-CSS") {
        $cssBlock = @'

/* BACKUP-SIDEBAR-V2-CSS */
.sidebar-backup-link {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    width: 100% !important;
    text-decoration: none !important;
}

.sidebar-backup-link .sidebar-icon {
    display: inline-flex !important;
    width: 24px !important;
    min-width: 24px !important;
    align-items: center !important;
    justify-content: center !important;
}

.sidebar-backup-link.active {
    font-weight: 700 !important;
}
'@
        Add-Content -Path $cssPath -Value $cssBlock -Encoding UTF8
        Write-Host "Backup sidebar CSS added to public/css/app.css" -ForegroundColor Green
    } else {
        Write-Host "Backup sidebar CSS already exists." -ForegroundColor Yellow
    }
} else {
    Write-Host "public/css/app.css not found. Link was added without extra CSS." -ForegroundColor Yellow
}

Write-Host "Done. Run: php artisan optimize:clear" -ForegroundColor Cyan

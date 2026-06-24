$ErrorActionPreference = 'Stop'

$projectRoot = (Get-Location).Path
$cssDir = Join-Path $projectRoot 'public\css'
$cssPath = Join-Path $cssDir 'cairo-global.css'
$layoutPath = Join-Path $projectRoot 'resources\views\layouts\app.blade.php'

if (!(Test-Path $cssDir)) {
    New-Item -ItemType Directory -Path $cssDir -Force | Out-Null
}

$css = @'
/* Cairo global font - DocumentArchive */
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap');

html,
body,
body *,
button,
input,
select,
textarea,
table,
th,
td,
a,
label,
.btn,
.card,
.alert,
.badge,
.sidebar,
.side-nav,
.nav-menu,
.topbar,
.page-header,
.form-control,
.form-select,
.dropdown-menu,
.modal,
.swal2-popup {
    font-family: "Cairo", Tahoma, Arial, sans-serif !important;
}

body {
    line-height: 1.65;
}

button,
input,
select,
textarea {
    letter-spacing: 0;
}
/* End Cairo global font - DocumentArchive */
'@

Set-Content -Path $cssPath -Value $css -Encoding UTF8

if (!(Test-Path $layoutPath)) {
    Write-Host "Layout file not found: $layoutPath" -ForegroundColor Yellow
    Write-Host 'Cairo CSS file was created, but layout link was not inserted.' -ForegroundColor Yellow
    exit 0
}

$content = Get-Content -Path $layoutPath -Raw -Encoding UTF8
$link = '<link rel="stylesheet" href="{{ asset(''css/cairo-global.css'') }}">'

if ($content -notmatch [regex]::Escape('css/cairo-global.css')) {
    if ($content -match '</head>') {
        $content = $content -replace '</head>', "    $link`r`n</head>"
        Set-Content -Path $layoutPath -Value $content -Encoding UTF8
        Write-Host 'Cairo font link inserted into resources/views/layouts/app.blade.php' -ForegroundColor Green
    } else {
        Write-Host 'Could not find </head> in layout file. Add this line manually inside <head>:' -ForegroundColor Yellow
        Write-Host $link -ForegroundColor Cyan
    }
} else {
    Write-Host 'Cairo font link already exists in layout.' -ForegroundColor Green
}

Write-Host 'Cairo global font and print preview files applied successfully.' -ForegroundColor Green

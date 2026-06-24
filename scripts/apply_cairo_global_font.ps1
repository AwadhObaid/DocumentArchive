$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$cssPath = Join-Path $root 'public\css\app.css'

if (!(Test-Path $cssPath)) {
    throw "لم يتم العثور على الملف: public\css\app.css"
}

$content = Get-Content -Path $cssPath -Raw -Encoding UTF8

$cairoImport = "@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap');"

if ($content -notmatch 'fonts\.googleapis\.com/css2\?family=Cairo') {
    $content = $cairoImport + "`r`n" + $content
}

$cairoBlock = @'

/* Cairo global font - DocumentArchive */
html,
body,
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

if ($content -notmatch 'Cairo global font - DocumentArchive') {
    $content = $content.TrimEnd() + "`r`n" + $cairoBlock + "`r`n"
}

Set-Content -Path $cssPath -Value $content -Encoding UTF8
Write-Host "Cairo font applied successfully to public\css\app.css" -ForegroundColor Green

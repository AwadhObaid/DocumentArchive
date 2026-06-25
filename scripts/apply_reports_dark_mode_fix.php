<?php
/**
 * DocumentArchive - Reports dark mode fix
 * Adds a scoped CSS/JS fix for /reports pages so report cards, filters and tables respect dark mode.
 */

$root = dirname(__DIR__);
if (!is_dir($root . DIRECTORY_SEPARATOR . 'resources') || !is_dir($root . DIRECTORY_SEPARATOR . 'public')) {
    fwrite(STDERR, "ERROR: شغّل السكربت من جذر مشروع Laravel DocumentArchive.\n");
    exit(1);
}

$publicCssDir = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css';
$publicJsDir  = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js';
@mkdir($publicCssDir, 0777, true);
@mkdir($publicJsDir, 0777, true);

$cssPath = $publicCssDir . DIRECTORY_SEPARATOR . 'reports-dark-mode-fix.css';
$jsPath  = $publicJsDir  . DIRECTORY_SEPARATOR . 'reports-dark-mode-fix.js';

$css = <<<'CSS'
/* DocumentArchive reports dark mode fix - scoped to reports pages only. */
@media screen {
    body.reports-dark-fix.reports-dark-mode-active {
        --reports-dark-bg: #0f172a;
        --reports-dark-card: #111827;
        --reports-dark-card-2: #1f2937;
        --reports-dark-border: #2f3b52;
        --reports-dark-text: #f8fafc;
        --reports-dark-muted: #cbd5e1;
        --reports-dark-soft: #94a3b8;
        --reports-dark-input: #0b1220;
    }

    body.reports-dark-fix.reports-dark-mode-active .card,
    body.reports-dark-fix.reports-dark-mode-active .report-card,
    body.reports-dark-fix.reports-dark-mode-active .reports-card,
    body.reports-dark-fix.reports-dark-mode-active .filter-card,
    body.reports-dark-fix.reports-dark-mode-active .summary-card,
    body.reports-dark-fix.reports-dark-mode-active .stat-card,
    body.reports-dark-fix.reports-dark-mode-active .dashboard-card,
    body.reports-dark-fix.reports-dark-mode-active .table-card,
    body.reports-dark-fix.reports-dark-mode-active .panel,
    body.reports-dark-fix.reports-dark-mode-active .box,
    body.reports-dark-fix.reports-dark-mode-active .bg-white,
    body.reports-dark-fix.reports-dark-mode-active .bg-light,
    body.reports-dark-fix.reports-dark-mode-active .white-card,
    body.reports-dark-fix.reports-dark-mode-active section.bg-white,
    body.reports-dark-fix.reports-dark-mode-active div[class*="card"],
    body.reports-dark-fix.reports-dark-mode-active div[class*="summary"],
    body.reports-dark-fix.reports-dark-mode-active div[class*="filter"] {
        background: var(--reports-dark-card) !important;
        color: var(--reports-dark-text) !important;
        border-color: var(--reports-dark-border) !important;
        box-shadow: 0 12px 32px rgba(0, 0, 0, .24) !important;
    }

    body.reports-dark-fix.reports-dark-mode-active .card *,
    body.reports-dark-fix.reports-dark-mode-active .report-card *,
    body.reports-dark-fix.reports-dark-mode-active .reports-card *,
    body.reports-dark-fix.reports-dark-mode-active .filter-card *,
    body.reports-dark-fix.reports-dark-mode-active .summary-card *,
    body.reports-dark-fix.reports-dark-mode-active .stat-card *,
    body.reports-dark-fix.reports-dark-mode-active .table-card *,
    body.reports-dark-fix.reports-dark-mode-active .bg-white *,
    body.reports-dark-fix.reports-dark-mode-active .bg-light *,
    body.reports-dark-fix.reports-dark-mode-active .white-card * {
        color: inherit;
    }

    body.reports-dark-fix.reports-dark-mode-active .text-muted,
    body.reports-dark-fix.reports-dark-mode-active .muted,
    body.reports-dark-fix.reports-dark-mode-active small,
    body.reports-dark-fix.reports-dark-mode-active label,
    body.reports-dark-fix.reports-dark-mode-active .form-label,
    body.reports-dark-fix.reports-dark-mode-active .help-text,
    body.reports-dark-fix.reports-dark-mode-active .description {
        color: var(--reports-dark-muted) !important;
    }

    body.reports-dark-fix.reports-dark-mode-active input,
    body.reports-dark-fix.reports-dark-mode-active select,
    body.reports-dark-fix.reports-dark-mode-active textarea,
    body.reports-dark-fix.reports-dark-mode-active .form-control,
    body.reports-dark-fix.reports-dark-mode-active .form-select,
    body.reports-dark-fix.reports-dark-mode-active .custom-select {
        background-color: var(--reports-dark-input) !important;
        color: var(--reports-dark-text) !important;
        border-color: var(--reports-dark-border) !important;
    }

    body.reports-dark-fix.reports-dark-mode-active input::placeholder,
    body.reports-dark-fix.reports-dark-mode-active textarea::placeholder {
        color: var(--reports-dark-soft) !important;
        opacity: 1 !important;
    }

    body.reports-dark-fix.reports-dark-mode-active select option {
        background-color: var(--reports-dark-input) !important;
        color: var(--reports-dark-text) !important;
    }

    body.reports-dark-fix.reports-dark-mode-active table,
    body.reports-dark-fix.reports-dark-mode-active .table {
        background: var(--reports-dark-card) !important;
        color: var(--reports-dark-text) !important;
        border-color: var(--reports-dark-border) !important;
    }

    body.reports-dark-fix.reports-dark-mode-active thead,
    body.reports-dark-fix.reports-dark-mode-active thead tr,
    body.reports-dark-fix.reports-dark-mode-active thead th,
    body.reports-dark-fix.reports-dark-mode-active .table thead th {
        background: var(--reports-dark-card-2) !important;
        color: var(--reports-dark-text) !important;
        border-color: var(--reports-dark-border) !important;
    }

    body.reports-dark-fix.reports-dark-mode-active tbody,
    body.reports-dark-fix.reports-dark-mode-active tbody tr,
    body.reports-dark-fix.reports-dark-mode-active tbody td,
    body.reports-dark-fix.reports-dark-mode-active .table tbody td {
        background: var(--reports-dark-card) !important;
        color: var(--reports-dark-muted) !important;
        border-color: var(--reports-dark-border) !important;
    }

    body.reports-dark-fix.reports-dark-mode-active tbody tr:hover,
    body.reports-dark-fix.reports-dark-mode-active .table tbody tr:hover td {
        background: #172033 !important;
        color: var(--reports-dark-text) !important;
    }

    body.reports-dark-fix.reports-dark-mode-active .badge,
    body.reports-dark-fix.reports-dark-mode-active .pill,
    body.reports-dark-fix.reports-dark-mode-active .chip {
        border-color: var(--reports-dark-border) !important;
    }
}

/* Keep printed reports clean on white paper. */
@media print {
    body.reports-dark-fix.reports-dark-mode-active .card,
    body.reports-dark-fix.reports-dark-mode-active .report-card,
    body.reports-dark-fix.reports-dark-mode-active .reports-card,
    body.reports-dark-fix.reports-dark-mode-active .filter-card,
    body.reports-dark-fix.reports-dark-mode-active .summary-card,
    body.reports-dark-fix.reports-dark-mode-active .stat-card,
    body.reports-dark-fix.reports-dark-mode-active .table-card,
    body.reports-dark-fix.reports-dark-mode-active table,
    body.reports-dark-fix.reports-dark-mode-active thead,
    body.reports-dark-fix.reports-dark-mode-active tbody,
    body.reports-dark-fix.reports-dark-mode-active tr,
    body.reports-dark-fix.reports-dark-mode-active th,
    body.reports-dark-fix.reports-dark-mode-active td {
        background: #fff !important;
        color: #000 !important;
        box-shadow: none !important;
    }
}
CSS;

$js = <<<'JS'
(function () {
    function isReportsPage() {
        return window.location && window.location.pathname.indexOf('/reports') === 0;
    }

    function readStorage(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }

    function hasDarkMode() {
        var html = document.documentElement;
        var body = document.body;
        var htmlClasses = html.classList;
        var bodyClasses = body ? body.classList : { contains: function () { return false; } };

        var attrTheme = (html.getAttribute('data-theme') || body.getAttribute('data-theme') || '').toLowerCase();
        var attrBsTheme = (html.getAttribute('data-bs-theme') || body.getAttribute('data-bs-theme') || '').toLowerCase();
        var storageValues = [
            readStorage('theme'),
            readStorage('appearance'),
            readStorage('color-theme'),
            readStorage('documentarchive-theme'),
            readStorage('darkMode')
        ].filter(Boolean).map(function (value) { return String(value).toLowerCase(); });

        if (htmlClasses.contains('dark') || htmlClasses.contains('dark-mode') || htmlClasses.contains('theme-dark')) return true;
        if (bodyClasses.contains('dark') || bodyClasses.contains('dark-mode') || bodyClasses.contains('theme-dark')) return true;
        if (attrTheme === 'dark' || attrBsTheme === 'dark') return true;
        if (storageValues.indexOf('dark') !== -1 || storageValues.indexOf('true') !== -1 || storageValues.indexOf('1') !== -1) return true;

        return false;
    }

    function syncReportsDarkMode() {
        if (!document.body || !isReportsPage()) return;
        document.body.classList.add('reports-dark-fix');
        document.body.classList.toggle('reports-dark-mode-active', hasDarkMode());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncReportsDarkMode);
    } else {
        syncReportsDarkMode();
    }

    var observer = new MutationObserver(syncReportsDarkMode);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme'] });
    document.addEventListener('click', function () { window.setTimeout(syncReportsDarkMode, 50); }, true);
})();
JS;

file_put_contents($cssPath, $css);
file_put_contents($jsPath, $js);

echo "OK: تم إنشاء public/css/reports-dark-mode-fix.css\n";
echo "OK: تم إنشاء public/js/reports-dark-mode-fix.js\n";

$layouts = [
    $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php',
    $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'admin.blade.php',
];

$targetLayout = null;
foreach ($layouts as $layout) {
    if (is_file($layout)) {
        $targetLayout = $layout;
        break;
    }
}

if (!$targetLayout) {
    fwrite(STDERR, "ERROR: لم أجد ملف layout مناسباً داخل resources/views/layouts.\n");
    exit(1);
}

$layoutContent = file_get_contents($targetLayout);
$markerStart = '<!-- reports-dark-mode-fix:start -->';
$markerEnd   = '<!-- reports-dark-mode-fix:end -->';
$injection = <<<BLADE
$markerStart
<link rel="stylesheet" href="{{ asset('css/reports-dark-mode-fix.css') }}">
<script defer src="{{ asset('js/reports-dark-mode-fix.js') }}"></script>
$markerEnd
BLADE;

if (strpos($layoutContent, $markerStart) === false) {
    if (stripos($layoutContent, '</head>') !== false) {
        $layoutContent = preg_replace('/<\/head>/i', $injection . "\n</head>", $layoutContent, 1);
    } else {
        $layoutContent .= "\n" . $injection . "\n";
    }
    file_put_contents($targetLayout, $layoutContent);
    echo "OK: تم ربط ملفات الإصلاح داخل: " . str_replace($root . DIRECTORY_SEPARATOR, '', $targetLayout) . "\n";
} else {
    $layoutContent = preg_replace('/<!-- reports-dark-mode-fix:start -->(.*?)<!-- reports-dark-mode-fix:end -->/s', $injection, $layoutContent, 1);
    file_put_contents($targetLayout, $layoutContent);
    echo "OK: رابط الإصلاح موجود وتم تحديثه داخل layout.\n";
}

echo "DONE: تم تطبيق إصلاح بطاقات التقارير في الوضع الليلي.\n";

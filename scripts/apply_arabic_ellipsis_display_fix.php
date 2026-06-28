<?php
/**
 * Arabic ellipsis/display clipping fix for DocumentArchive.
 * This patch does NOT convert encoding and does NOT touch QR.
 * It adds a final CSS/JS override to prevent Arabic words from being clipped as "...".
 */

$root = dirname(__DIR__);
$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups';
$backupDir = $backupRoot . DIRECTORY_SEPARATOR . 'arabic-ellipsis-display-fix-' . date('Ymd_His');

function fail_msg(string $message): void {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function ensure_dir(string $dir): void {
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fail_msg("تعذر إنشاء المجلد: {$dir}");
    }
}

function backup_file(string $file, string $root, string $backupDir): void {
    if (!is_file($file)) {
        return;
    }
    $relative = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);
    $target = $backupDir . DIRECTORY_SEPARATOR . $relative;
    ensure_dir(dirname($target));
    copy($file, $target);
}

ensure_dir($backupDir);

$layout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
if (!is_file($layout)) {
    fail_msg('لم يتم العثور على resources/views/layouts/app.blade.php');
}

$cssRel = 'css/arabic-ellipsis-display-fix.css';
$jsRel  = 'js/arabic-ellipsis-display-fix.js';
$cssPath = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'arabic-ellipsis-display-fix.css';
$jsPath = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'arabic-ellipsis-display-fix.js';

$css = <<<'CSS'
/*
 * Arabic ellipsis/display clipping fix
 * Purpose: show Arabic words fully instead of truncating them as "...".
 * This file intentionally runs after the main application CSS.
 */

html[dir="rtl"] body:not(.da-print-reference-page) {
    text-rendering: optimizeLegibility;
}

/* General Arabic text: disable aggressive truncation/line-clamp. */
html[dir="rtl"] body:not(.da-print-reference-page) h1,
html[dir="rtl"] body:not(.da-print-reference-page) h2,
html[dir="rtl"] body:not(.da-print-reference-page) h3,
html[dir="rtl"] body:not(.da-print-reference-page) h4,
html[dir="rtl"] body:not(.da-print-reference-page) h5,
html[dir="rtl"] body:not(.da-print-reference-page) h6,
html[dir="rtl"] body:not(.da-print-reference-page) p,
html[dir="rtl"] body:not(.da-print-reference-page) label,
html[dir="rtl"] body:not(.da-print-reference-page) th,
html[dir="rtl"] body:not(.da-print-reference-page) td,
html[dir="rtl"] body:not(.da-print-reference-page) button,
html[dir="rtl"] body:not(.da-print-reference-page) .btn,
html[dir="rtl"] body:not(.da-print-reference-page) .alert,
html[dir="rtl"] body:not(.da-print-reference-page) .badge,
html[dir="rtl"] body:not(.da-print-reference-page) .card,
html[dir="rtl"] body:not(.da-print-reference-page) .card *,
html[dir="rtl"] body:not(.da-print-reference-page) .page-title,
html[dir="rtl"] body:not(.da-print-reference-page) .page-subtitle,
html[dir="rtl"] body:not(.da-print-reference-page) .brand-title,
html[dir="rtl"] body:not(.da-print-reference-page) .brand-subtitle,
html[dir="rtl"] body:not(.da-print-reference-page) .section-title,
html[dir="rtl"] body:not(.da-print-reference-page) .stat-title,
html[dir="rtl"] body:not(.da-print-reference-page) .stat-label,
html[dir="rtl"] body:not(.da-print-reference-page) .stat-value,
html[dir="rtl"] body:not(.da-print-reference-page) .table,
html[dir="rtl"] body:not(.da-print-reference-page) .table *,
html[dir="rtl"] body:not(.da-print-reference-page) .form-label,
html[dir="rtl"] body:not(.da-print-reference-page) .form-control,
html[dir="rtl"] body:not(.da-print-reference-page) .form-select {
    text-overflow: clip !important;
    -webkit-line-clamp: unset !important;
    line-clamp: unset !important;
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
}

/* Sidebar/header/nav items often had nowrap + ellipsis. */
html[dir="rtl"] body:not(.da-print-reference-page) aside a,
html[dir="rtl"] body:not(.da-print-reference-page) aside button,
html[dir="rtl"] body:not(.da-print-reference-page) aside span,
html[dir="rtl"] body:not(.da-print-reference-page) aside div,
html[dir="rtl"] body:not(.da-print-reference-page) .sidebar a,
html[dir="rtl"] body:not(.da-print-reference-page) .sidebar button,
html[dir="rtl"] body:not(.da-print-reference-page) .sidebar span,
html[dir="rtl"] body:not(.da-print-reference-page) .app-sidebar a,
html[dir="rtl"] body:not(.da-print-reference-page) .app-sidebar button,
html[dir="rtl"] body:not(.da-print-reference-page) .app-sidebar span,
html[dir="rtl"] body:not(.da-print-reference-page) .nav-link,
html[dir="rtl"] body:not(.da-print-reference-page) .navbar a,
html[dir="rtl"] body:not(.da-print-reference-page) .navbar span,
html[dir="rtl"] body:not(.da-print-reference-page) header a,
html[dir="rtl"] body:not(.da-print-reference-page) header span,
html[dir="rtl"] body:not(.da-print-reference-page) header div {
    text-overflow: clip !important;
    -webkit-line-clamp: unset !important;
    line-clamp: unset !important;
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word !important;
}

/* Avoid flex children being squeezed until text is clipped. */
html[dir="rtl"] body:not(.da-print-reference-page) .d-flex > *,
html[dir="rtl"] body:not(.da-print-reference-page) .flex > *,
html[dir="rtl"] body:not(.da-print-reference-page) .row > *,
html[dir="rtl"] body:not(.da-print-reference-page) header > *,
html[dir="rtl"] body:not(.da-print-reference-page) aside > *,
html[dir="rtl"] body:not(.da-print-reference-page) main > * {
    min-width: 0;
}

html[dir="rtl"] body:not(.da-print-reference-page) aside .nav-link,
html[dir="rtl"] body:not(.da-print-reference-page) .sidebar .nav-link,
html[dir="rtl"] body:not(.da-print-reference-page) .app-sidebar .nav-link,
html[dir="rtl"] body:not(.da-print-reference-page) .menu-link {
    min-height: 42px;
    line-height: 1.65 !important;
    gap: 8px;
}

html[dir="rtl"] body:not(.da-print-reference-page) .da-force-no-ellipsis,
html[dir="rtl"] body:not(.da-print-reference-page) .da-force-no-ellipsis * {
    text-overflow: clip !important;
    -webkit-line-clamp: unset !important;
    line-clamp: unset !important;
    white-space: normal !important;
    overflow: visible !important;
}
CSS;

$js = <<<'JS'
(function () {
    'use strict';

    function markNoEllipsis() {
        var selectors = [
            'h1','h2','h3','h4','h5','h6','p','label','th','td','button',
            '.btn','.alert','.badge','.card','.page-title','.page-subtitle',
            '.brand-title','.brand-subtitle','.section-title','.stat-title','.stat-label',
            '.sidebar a','.sidebar span','.app-sidebar a','.app-sidebar span',
            'aside a','aside span','header a','header span','.nav-link','.menu-link'
        ];

        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (el) {
                el.classList.add('da-force-no-ellipsis');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', markNoEllipsis);
    } else {
        markNoEllipsis();
    }
})();
JS;

backup_file($layout, $root, $backupDir);
backup_file($cssPath, $root, $backupDir);
backup_file($jsPath, $root, $backupDir);

ensure_dir(dirname($cssPath));
ensure_dir(dirname($jsPath));
file_put_contents($cssPath, $css);
file_put_contents($jsPath, $js);

$layoutContent = file_get_contents($layout);
if ($layoutContent === false) {
    fail_msg('تعذر قراءة ملف layout.');
}

$cssNeedle = "arabic-ellipsis-display-fix.css";
$jsNeedle = "arabic-ellipsis-display-fix.js";

if (!str_contains($layoutContent, $cssNeedle)) {
    $cssLink = "    <link rel=\"stylesheet\" href=\"{{ asset('css/arabic-ellipsis-display-fix.css') }}?v={{ time() }}\">\n";
    if (str_contains($layoutContent, '</head>')) {
        $layoutContent = str_replace('</head>', $cssLink . '</head>', $layoutContent);
    } else {
        $layoutContent = $cssLink . $layoutContent;
    }
}

if (!str_contains($layoutContent, $jsNeedle)) {
    $jsScript = "    <script src=\"{{ asset('js/arabic-ellipsis-display-fix.js') }}?v={{ time() }}\"></script>\n";
    if (str_contains($layoutContent, '</body>')) {
        $layoutContent = str_replace('</body>', $jsScript . '</body>', $layoutContent);
    } else {
        $layoutContent .= "\n" . $jsScript;
    }
}

file_put_contents($layout, $layoutContent);

echo "DONE: تم إضافة إصلاح عرض الكلمات العربية ومنع اختصارها بالنقاط.\n";
echo "Backup: {$backupDir}\n";
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

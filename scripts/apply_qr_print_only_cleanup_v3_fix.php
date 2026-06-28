<?php
/**
 * QR cleanup + print-only binding V3
 * - Removes old _backup folders from Laravel active paths.
 * - Adds a small guard CSS/JS layer to prevent print QR artifacts from appearing on document show pages.
 * - Adds/updates a body class on the official print page if a matching Blade view is found.
 * - Adds CSS variables support for QR settings on print pages.
 */

function project_root(): string
{
    $root = realpath(__DIR__ . '/..');
    if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
        fwrite(STDERR, "ERROR: شغّل السكربت من داخل جذر مشروع Laravel.\n");
        exit(1);
    }
    return $root;
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = array_diff(scandir($dir) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) rrmdir($path); else @unlink($path);
    }
    @rmdir($dir);
}

function cleanup_backups(string $root): int
{
    $count = 0;
    foreach (['app', 'resources', 'routes'] as $rel) {
        $dir = $root . DIRECTORY_SEPARATOR . $rel;
        if (!is_dir($dir)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir() && str_starts_with($file->getFilename(), '_backup')) {
                rrmdir($file->getPathname());
                $count++;
            }
        }
    }
    return $count;
}

function ensure_asset_link(string $layout, string $needle, string $html, string $beforeTag): bool
{
    if (!is_file($layout)) return false;
    $content = file_get_contents($layout);
    if ($content === false) return false;
    if (str_contains($content, $needle)) return false;
    if (str_contains($content, $beforeTag)) {
        $content = str_replace($beforeTag, $html . "\n" . $beforeTag, $content);
    } else {
        $content .= "\n" . $html . "\n";
    }
    file_put_contents($layout, $content);
    return true;
}

function find_print_views(string $root): array
{
    $views = [];
    $base = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
    if (!is_dir($base)) return [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) continue;
        $path = $file->getPathname();
        $content = file_get_contents($path);
        if ($content === false) continue;
        $name = str_replace('\\', '/', substr($path, strlen($base)+1));
        $isPrint = (str_contains($name, 'print') || str_contains($content, 'طباعة رقم الكتاب') || (str_contains($content, 'رقم الكتاب') && str_contains($content, 'تاريخ الكتاب') && str_contains($content, 'window.print')));
        if ($isPrint && str_contains($content, 'رقم الكتاب') && str_contains($content, 'تاريخ الكتاب')) {
            $views[] = $path;
        }
    }
    return $views;
}

function add_body_class_to_print_view(string $path): bool
{
    $content = file_get_contents($path);
    if ($content === false) return false;
    $original = $content;

    if (!str_contains($content, 'da-document-print-page')) {
        // Add class to existing body tag, preserving other attributes/classes.
        if (preg_match('/<body\b([^>]*)>/i', $content, $m, PREG_OFFSET_CAPTURE)) {
            $bodyTag = $m[0][0];
            if (preg_match('/class\s*=\s*(["\'])(.*?)\1/i', $bodyTag, $cm)) {
                $newBodyTag = preg_replace('/class\s*=\s*(["\'])(.*?)\1/i', 'class=$1$2 da-document-print-page$1', $bodyTag, 1);
            } else {
                $newBodyTag = str_replace('<body', '<body class="da-document-print-page"', $bodyTag);
            }
            $content = substr_replace($content, $newBodyTag, $m[0][1], strlen($bodyTag));
        } else {
            // Blade partial without body: insert a harmless marker CSS class wrapper comment/attribute via a div at top.
            $content = "<div class=\"da-document-print-page da-document-print-page-marker\">\n" . $content . "\n</div>\n";
        }
    }

    // If the print view already contains a QR container, ensure it has the final class.
    if (str_contains($content, '/qr.svg') && !str_contains($content, 'da-reference-print-qr')) {
        $content = str_replace(['document-qr-print', 'document-print-qr', 'qr-print-card', 'da-qr-print', 'da-qr-side', 'da-qr-precise'], 'da-reference-print-qr', $content);
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
        return true;
    }
    return false;
}

$root = project_root();
@mkdir($root . '/public/css', 0777, true);
@mkdir($root . '/public/js', 0777, true);

$removed = cleanup_backups($root);

$css = <<<'CSS'
/* Final guard for document QR display.
   QR used for print positioning must not leak into normal document show pages. */
body.da-document-show-page .da-reference-print-qr,
body.da-document-show-page .da-qr-print,
body.da-document-show-page .document-qr-print,
body.da-document-show-page .document-print-qr,
body.da-document-show-page .document-qr-card,
body.da-document-show-page .qr-print-card,
body.da-document-show-page .qr-code-card,
body.da-document-show-page .da-qr-side,
body.da-document-show-page .da-qr-precise,
body.da-document-show-page .da-qr-positioned,
body.da-document-show-page [data-da-print-qr="1"] {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
}

/* Normalize document show action/header blocks if a print CSS patch made them float. */
body.da-document-show-page .document-show-actions,
body.da-document-show-page .show-actions,
body.da-document-show-page .page-actions,
body.da-document-show-page .header-actions,
body.da-document-show-page .top-actions {
    position: static !important;
    inset: auto !important;
    transform: none !important;
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    gap: .5rem !important;
    width: auto !important;
    max-width: none !important;
    min-height: auto !important;
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 0 1rem 0 !important;
}

/* Official print QR position. The values can be overridden inline from settings. */
body.da-document-print-page .da-reference-print-qr {
    position: absolute !important;
    left: var(--da-qr-x, 55mm) !important;
    top: var(--da-qr-y, 48mm) !important;
    width: auto !important;
    height: auto !important;
    text-align: center !important;
    z-index: 10 !important;
    background: transparent !important;
    box-shadow: none !important;
    border: 0 !important;
}
body.da-document-print-page .da-reference-print-qr img,
body.da-document-print-page .da-reference-print-qr svg,
body.da-document-print-page .da-reference-print-qr object,
body.da-document-print-page .da-reference-print-qr iframe {
    width: var(--da-qr-size, 16mm) !important;
    height: var(--da-qr-size, 16mm) !important;
    max-width: var(--da-qr-size, 16mm) !important;
    max-height: var(--da-qr-size, 16mm) !important;
}
body.da-document-print-page .da-reference-print-qr .qr-label,
body.da-document-print-page .da-reference-print-qr small,
body.da-document-print-page .da-reference-print-qr span {
    display: block;
    margin-top: 1mm;
    font-size: var(--da-qr-label-size, 7px) !important;
    line-height: 1.2 !important;
    white-space: nowrap !important;
}
@media print {
    body.da-document-print-page .da-reference-print-qr {
        position: absolute !important;
        left: var(--da-qr-x, 55mm) !important;
        top: var(--da-qr-y, 48mm) !important;
    }
}
CSS;
file_put_contents($root . '/public/css/document-qr-final-guard.css', $css);

$js = <<<'JS'
(function () {
    function isDocumentShowPath() {
        return /^\/documents\/\d+\/?$/.test(window.location.pathname);
    }
    function isOfficialPrintPage() {
        return document.body.classList.contains('da-document-print-page') ||
            document.querySelector('.da-document-print-page-marker') ||
            document.title.includes('طباعة رقم الكتاب') ||
            window.location.pathname.includes('/print');
    }
    function removeQrLeakFromShowPage() {
        if (!isDocumentShowPath() || isOfficialPrintPage()) return;
        document.body.classList.add('da-document-show-page');
        var selectors = [
            'img[src*="/qr.svg"]', 'iframe[src*="/qr.svg"]', 'object[data*="/qr.svg"]',
            'a[href*="/qr.svg"]', '.da-reference-print-qr', '.da-qr-print', '.document-qr-print',
            '.document-print-qr', '.document-qr-card', '.qr-print-card', '.qr-code-card',
            '.da-qr-side', '.da-qr-precise', '.da-qr-positioned', '[data-da-print-qr="1"]'
        ];
        document.querySelectorAll(selectors.join(',')).forEach(function (el) {
            var holder = el.closest('.da-reference-print-qr,.da-qr-print,.document-qr-print,.document-print-qr,.document-qr-card,.qr-print-card,.qr-code-card,.da-qr-side,.da-qr-precise,.da-qr-positioned,[data-da-print-qr="1"]');
            (holder || el).remove();
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', removeQrLeakFromShowPage);
    } else {
        removeQrLeakFromShowPage();
    }
})();
JS;
file_put_contents($root . '/public/js/document-qr-final-guard.js', $js);

$layouts = [
    $root . '/resources/views/layouts/app.blade.php',
    $root . '/resources/views/layouts/admin.blade.php',
    $root . '/resources/views/layouts/main.blade.php',
];
$linked = 0;
foreach ($layouts as $layout) {
    if (!is_file($layout)) continue;
    if (ensure_asset_link($layout, 'document-qr-final-guard.css', "<link rel=\"stylesheet\" href=\"{{ asset('css/document-qr-final-guard.css') }}\">", '</head>')) $linked++;
    if (ensure_asset_link($layout, 'document-qr-final-guard.js', "<script src=\"{{ asset('js/document-qr-final-guard.js') }}\" defer></script>", '</body>')) $linked++;
}

$printViews = find_print_views($root);
$patchedPrint = 0;
foreach ($printViews as $view) {
    if (add_body_class_to_print_view($view)) $patchedPrint++;
}

echo "DONE: تم تنظيف ظهور QR العشوائي وربطه بالطباعة فقط.\n";
echo "Backups removed: {$removed}\n";
echo "Assets linked/verified in layouts: {$linked}\n";
echo "Print views detected: " . count($printViews) . " / patched: {$patchedPrint}\n";
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

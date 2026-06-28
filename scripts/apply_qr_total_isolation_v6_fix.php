<?php
/**
 * QR Total Isolation V6 Fix
 * - Stops leaked QR elements from non-print pages.
 * - Rebuilds documents/print-reference.blade.php as the only page that shows QR.
 * - Adds global fallback variables to avoid undefined $daQrUrl errors.
 */

function root_path_v6(): string
{
    $dir = realpath(__DIR__ . '/..');
    if (!$dir || !file_exists($dir . DIRECTORY_SEPARATOR . 'artisan')) {
        fwrite(STDERR, "ERROR: شغّل السكربت من داخل جذر مشروع Laravel.\n");
        exit(1);
    }
    return $dir;
}

function ensure_dir_v6(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function copy_backup_v6(string $root, string $relative, string $backupRoot): void
{
    $src = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    if (!file_exists($src)) return;
    $dst = $backupRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    ensure_dir_v6(dirname($dst));
    copy($src, $dst);
}

function remove_dir_v6(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    if (!$items) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) remove_dir_v6($path);
        else @unlink($path);
    }
    @rmdir($dir);
}

function clean_backup_dirs_v6(string $root): array
{
    $removed = [];
    foreach (['app', 'resources', 'routes'] as $folder) {
        $base = $root . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($base)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir() && str_starts_with($file->getFilename(), '_backup')) {
                $path = $file->getPathname();
                remove_dir_v6($path);
                $removed[] = $path;
            }
        }
    }
    return $removed;
}

function write_file_v6(string $path, string $content): void
{
    ensure_dir_v6(dirname($path));
    file_put_contents($path, $content);
}

function patch_layout_v6(string $root, string $backupRoot): void
{
    $layout = $root . DIRECTORY_SEPARATOR . 'resources/views/layouts/app.blade.php';
    if (!file_exists($layout)) return;
    copy_backup_v6($root, 'resources/views/layouts/app.blade.php', $backupRoot);
    $content = file_get_contents($layout);

    // Remove old QR asset includes only, keep other layout content.
    $lines = preg_split("/\R/", $content);
    $new = [];
    foreach ($lines as $line) {
        $lower = strtolower($line);
        if ((str_contains($lower, '<link') || str_contains($lower, '<script')) &&
            (str_contains($lower, 'qr') || str_contains($lower, 'qrcode') || str_contains($lower, 'document-qr'))) {
            continue;
        }
        if (str_contains($lower, 'qr-total-isolation-v6')) continue;
        $new[] = $line;
    }
    $content = implode(PHP_EOL, $new);

    $css = "    <link rel=\"stylesheet\" href=\"{{ asset('css/qr-total-isolation-v6.css') }}?v={{ filemtime(public_path('css/qr-total-isolation-v6.css')) }}\">";
    $js  = "    <script src=\"{{ asset('js/qr-total-isolation-v6.js') }}?v={{ filemtime(public_path('js/qr-total-isolation-v6.js')) }}\" defer></script>";

    if (!str_contains($content, 'qr-total-isolation-v6.css')) {
        if (str_contains($content, '</head>')) {
            $content = str_replace('</head>', $css . PHP_EOL . '</head>', $content);
        } else {
            $content = $css . PHP_EOL . $content;
        }
    }
    if (!str_contains($content, 'qr-total-isolation-v6.js')) {
        if (str_contains($content, '</body>')) {
            $content = str_replace('</body>', $js . PHP_EOL . '</body>', $content);
        } else {
            $content .= PHP_EOL . $js . PHP_EOL;
        }
    }
    file_put_contents($layout, $content);
}

function patch_service_provider_v6(string $root, string $backupRoot): void
{
    $file = $root . DIRECTORY_SEPARATOR . 'app/Providers/AppServiceProvider.php';
    if (!file_exists($file)) return;
    copy_backup_v6($root, 'app/Providers/AppServiceProvider.php', $backupRoot);
    $content = file_get_contents($file);

    if (!str_contains($content, 'Illuminate\\Support\\Facades\\View')) {
        $content = preg_replace('/namespace\s+App\\Providers;\s*/', "namespace App\\Providers;\n\nuse Illuminate\\Support\\Facades\\View;\n", $content, 1);
    }

    $shareBlock = <<<'PHP'
        // QR fallback variables: prevents old injected QR snippets from breaking non-print pages.
        View::share('daQrUrl', '');
        View::share('daQrDocument', null);
        View::share('daQrSettings', []);
PHP;

    if (!str_contains($content, "View::share('daQrUrl'")) {
        if (preg_match('/public\s+function\s+boot\s*\([^)]*\)\s*:\s*void\s*\{/', $content, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);
            $content = substr($content, 0, $pos) . PHP_EOL . $shareBlock . substr($content, $pos);
        } elseif (preg_match('/public\s+function\s+boot\s*\([^)]*\)\s*\{/', $content, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);
            $content = substr($content, 0, $pos) . PHP_EOL . $shareBlock . substr($content, $pos);
        } else {
            // Add a boot method before final class brace.
            $insert = PHP_EOL . "    public function boot(): void\n    {\n" . $shareBlock . "\n    }\n";
            $last = strrpos($content, '}');
            if ($last !== false) {
                $content = substr($content, 0, $last) . $insert . substr($content, $last);
            }
        }
    }

    file_put_contents($file, $content);
}

function remove_qr_from_non_print_views_v6(string $root, string $backupRoot): array
{
    $views = $root . DIRECTORY_SEPARATOR . 'resources/views';
    if (!is_dir($views)) return [];
    $changed = [];
    $terms = [
        'daq', 'daqr', 'da-qr', 'document-qr', 'document_qr', 'qr-print', 'print-qr',
        'qr.svg', 'qrcode', 'qr_code', 'qr-position', 'qr_print', 'رمز الوصول الإلكتروني'
    ];

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) continue;
        $full = $file->getPathname();
        $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $full);
        $relNorm = str_replace('\\', '/', $rel);
        if ($relNorm === 'resources/views/documents/print-reference.blade.php') continue;
        if ($relNorm === 'resources/views/settings/qr-print-position.blade.php') continue;

        $content = file_get_contents($full);
        $original = $content;

        // Remove marked blocks first.
        $content = preg_replace('/<!--\s*(?:DA_)?QR[^>]*START\s*-->.*?<!--\s*(?:DA_)?QR[^>]*END\s*-->/is', '', $content);
        $content = preg_replace('/\{\{--\s*(?:DA_)?QR[^}]*START\s*--\}\}.*?\{\{--\s*(?:DA_)?QR[^}]*END\s*--\}\}/is', '', $content);

        // Remove obvious QR div/card blocks.
        $content = preg_replace('/<div\b[^>]*(?:document-qr|da-qr|qr-print|print-qr|qr-position)[^>]*>.*?<\/div>\s*/is', '', $content);
        $content = preg_replace('/<section\b[^>]*(?:document-qr|da-qr|qr-print|print-qr|qr-position)[^>]*>.*?<\/section>\s*/is', '', $content);

        // Remove single QR-related lines to prevent undefined variables.
        $lines = preg_split('/\R/', $content);
        $kept = [];
        foreach ($lines as $line) {
            $lower = mb_strtolower($line, 'UTF-8');
            $hit = false;
            foreach ($terms as $term) {
                if (str_contains($lower, mb_strtolower($term, 'UTF-8'))) { $hit = true; break; }
            }
            if ($hit) continue;
            $kept[] = $line;
        }
        $content = implode(PHP_EOL, $kept);

        if ($content !== $original) {
            copy_backup_v6($root, $relNorm, $backupRoot);
            file_put_contents($full, $content);
            $changed[] = $relNorm;
        }
    }
    return $changed;
}

function rebuild_print_reference_v6(string $root, string $backupRoot): void
{
    $rel = 'resources/views/documents/print-reference.blade.php';
    $file = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    copy_backup_v6($root, $rel, $backupRoot);

    $blade = <<<'BLADE'
@php
    $doc = $document ?? ($daQrDocument ?? null);
    $docId = data_get($doc, 'id');
    $referenceNumber = data_get($doc, 'reference_number', data_get($doc, 'document_no', ''));
    $referenceDateRaw = data_get($doc, 'reference_date', data_get($doc, 'date', null));
    try {
        $referenceDate = $referenceDateRaw ? \Carbon\Carbon::parse($referenceDateRaw)->format('d/m/Y') : '';
    } catch (\Throwable $e) {
        $referenceDate = (string) $referenceDateRaw;
    }

    $settings = [];
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('qr_print_settings')) {
            $settings = \Illuminate\Support\Facades\DB::table('qr_print_settings')->pluck('value', 'key')->toArray();
        }
    } catch (\Throwable $e) { $settings = []; }

    $setting = function ($keys, $default = null) use ($settings) {
        foreach ((array) $keys as $key) {
            if (array_key_exists($key, $settings) && $settings[$key] !== '' && $settings[$key] !== null) {
                return $settings[$key];
            }
        }
        return $default;
    };

    $qrX = (float) $setting(['x', 'qr_x', 'qr_print_x', 'qr_position_x', 'position_x'], 55);
    $qrY = (float) $setting(['y', 'qr_y', 'qr_print_y', 'qr_position_y', 'position_y'], 48);
    $qrSize = (float) $setting(['size', 'qr_size', 'qr_print_size'], 16);
    $qrPadding = (float) $setting(['padding', 'qr_padding', 'qr_print_padding'], 1);
    $qrTextSize = (float) $setting(['text_size', 'qr_text_size', 'label_size'], 7);
    $showQr = filter_var($setting(['show_qr', 'qr_show', 'enabled'], true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $showQr = $showQr === null ? true : $showQr;
    $showLabel = filter_var($setting(['show_label', 'qr_show_label', 'label_enabled'], true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $showLabel = $showLabel === null ? true : $showLabel;
    $qrUrl = $docId ? url('/documents/' . $docId . '/qr.svg') : '';
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة رقم الكتاب {{ $referenceNumber }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #eef0f4; font-family: Tahoma, Arial, sans-serif; color: #000; }
        .da-toolbar { position: fixed; top: 10px; left: 10px; z-index: 10; display: flex; gap: 8px; }
        .da-btn { border: 0; border-radius: 8px; padding: 9px 16px; font-weight: 700; cursor: pointer; text-decoration: none; color: #fff; font-size: 14px; }
        .da-btn-print { background: #2563eb; }
        .da-btn-back { background: #6b7280; }
        .da-a4-page { width: 210mm; height: 297mm; margin: 10mm auto; background: #fff; position: relative; overflow: hidden; box-shadow: 0 0 0 1px #d1d5db; }
        .da-reference-block { position: absolute; top: 49mm; right: 42mm; min-width: 74mm; font-size: 13pt; font-weight: 700; line-height: 1.65; text-align: right; }
        .da-dept { text-align: center; margin-bottom: 2mm; font-size: 12pt; }
        .da-row { display: grid; grid-template-columns: 28mm 5mm 38mm; gap: 2mm; align-items: center; }
        .da-label { text-align: right; }
        .da-colon { text-align: center; }
        .da-value { text-align: left; direction: ltr; }
        .da-print-qr-only { position: absolute; left: {{ $qrX }}mm; top: {{ $qrY }}mm; width: {{ $qrSize + ($qrPadding * 2) }}mm; min-height: {{ $qrSize + ($qrPadding * 2) + 6 }}mm; padding: {{ $qrPadding }}mm; background: #fff; border: .25mm solid #d1d5db; border-radius: 2mm; text-align: center; }
        .da-print-qr-only img { display: block; width: {{ $qrSize }}mm; height: {{ $qrSize }}mm; margin: 0 auto; object-fit: contain; }
        .da-print-qr-label { margin-top: 1mm; font-size: {{ $qrTextSize }}pt; color: #555; line-height: 1.2; white-space: nowrap; }
        @media print {
            html, body { background: #fff; }
            .da-toolbar { display: none !important; }
            .da-a4-page { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body class="da-print-reference-page">
    <div class="da-toolbar">
        <button class="da-btn da-btn-print" onclick="window.print()">طباعة</button>
        <a class="da-btn da-btn-back" href="{{ $docId ? url('/documents/' . $docId) : url('/documents') }}">رجوع</a>
    </div>

    <main class="da-a4-page">
        <section class="da-reference-block">
            <div class="da-dept">الشحن والتأمين</div>
            <div class="da-row">
                <span class="da-label">رقم الكتاب</span>
                <span class="da-colon">:</span>
                <span class="da-value">{{ $referenceNumber }}</span>
            </div>
            <div class="da-row">
                <span class="da-label">تاريخ الكتاب</span>
                <span class="da-colon">:</span>
                <span class="da-value">{{ $referenceDate }}</span>
            </div>
        </section>

        @if($showQr && $qrUrl)
            <section class="da-print-qr-only" data-da-print-qr="1">
                <img src="{{ $qrUrl }}" alt="رمز الوصول الإلكتروني">
                @if($showLabel)
                    <div class="da-print-qr-label">رمز الوصول الإلكتروني</div>
                @endif
            </section>
        @endif
    </main>
</body>
</html>
BLADE;
    write_file_v6($file, $blade);
}

function write_assets_v6(string $root): void
{
    $css = <<<'CSS'
/* QR Total Isolation V6
   Hide leaked QR widgets from every normal app page. QR is allowed only on:
   - /documents/{id}/print-reference
   - /settings/qr-print-position
*/
body:not(.da-print-reference-page):not(.da-qr-settings-page) .document-qr-card,
body:not(.da-print-reference-page):not(.da-qr-settings-page) .document-qr-wrapper,
body:not(.da-print-reference-page):not(.da-qr-settings-page) .da-document-qr,
body:not(.da-print-reference-page):not(.da-qr-settings-page) .da-document-qr-card,
body:not(.da-print-reference-page):not(.da-qr-settings-page) .da-print-qr,
body:not(.da-print-reference-page):not(.da-qr-settings-page) .da-print-qr-only,
body:not(.da-print-reference-page):not(.da-qr-settings-page) .da-qr-print,
body:not(.da-print-reference-page):not(.da-qr-settings-page) .da-qr-print-box,
body:not(.da-print-reference-page):not(.da-qr-settings-page) .qr-print-position-card,
body:not(.da-print-reference-page):not(.da-qr-settings-page) [data-da-qr],
body:not(.da-print-reference-page):not(.da-qr-settings-page) [data-da-print-qr] {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
}
@media print {
    body:not(.da-print-reference-page) .document-qr-card,
    body:not(.da-print-reference-page) .document-qr-wrapper,
    body:not(.da-print-reference-page) .da-document-qr,
    body:not(.da-print-reference-page) .da-document-qr-card,
    body:not(.da-print-reference-page) .da-print-qr,
    body:not(.da-print-reference-page) .da-print-qr-only,
    body:not(.da-print-reference-page) .da-qr-print,
    body:not(.da-print-reference-page) .da-qr-print-box,
    body:not(.da-print-reference-page) .qr-print-position-card,
    body:not(.da-print-reference-page) [data-da-qr],
    body:not(.da-print-reference-page) [data-da-print-qr] {
        display: none !important;
    }
}
CSS;
    $js = <<<'JS'
(function () {
    function isAllowedPage() {
        var p = window.location.pathname || '';
        return /\/documents\/\d+\/print-reference\/?$/.test(p) || p.indexOf('/settings/qr-print-position') === 0;
    }
    if (isAllowedPage()) return;

    var selectors = [
        '.document-qr-card',
        '.document-qr-wrapper',
        '.da-document-qr',
        '.da-document-qr-card',
        '.da-print-qr',
        '.da-print-qr-only',
        '.da-qr-print',
        '.da-qr-print-box',
        '.qr-print-position-card',
        '[data-da-qr]',
        '[data-da-print-qr]',
        'img[src*="/qr.svg"]',
        'img[src*="qr.svg"]'
    ];

    function removeLeakedQr() {
        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (node) {
                var box = node.closest('.document-qr-card, .document-qr-wrapper, .da-document-qr, .da-document-qr-card, .da-print-qr, .da-print-qr-only, .da-qr-print, .da-qr-print-box, .qr-print-position-card, [data-da-qr], [data-da-print-qr]');
                (box || node).remove();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', removeLeakedQr);
    } else {
        removeLeakedQr();
    }
    setTimeout(removeLeakedQr, 100);
    setTimeout(removeLeakedQr, 500);
})();
JS;
    write_file_v6($root . '/public/css/qr-total-isolation-v6.css', $css);
    write_file_v6($root . '/public/js/qr-total-isolation-v6.js', $js);
}

$root = root_path_v6();
$stamp = date('Ymd_His');
$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage/app/private/patch-backups/qr-total-isolation-v6-' . $stamp;
ensure_dir_v6($backupRoot);

$removedBackups = clean_backup_dirs_v6($root);
write_assets_v6($root);
patch_layout_v6($root, $backupRoot);
patch_service_provider_v6($root, $backupRoot);
$changedViews = remove_qr_from_non_print_views_v6($root, $backupRoot);
rebuild_print_reference_v6($root, $backupRoot);

// Create a small marker file for the checker.
write_file_v6($root . '/storage/app/private/qr-total-isolation-v6-applied.txt', date('c'));

echo "DONE: تم عزل QR وإعادة بناء صفحة طباعة رقم الكتاب V6.\n";
echo "Backup: {$backupRoot}\n";
if ($removedBackups) echo "Removed backup dirs: " . count($removedBackups) . "\n";
if ($changedViews) echo "Cleaned views: " . count($changedViews) . "\n";
echo "NEXT: composer dump-autoload && php artisan view:clear && php artisan optimize:clear\n";

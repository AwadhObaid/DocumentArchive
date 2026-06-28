<?php
/**
 * Arabic mojibake + no-truncate final repair V4
 * - Binary-safe: no mb_convert_encoding, no Windows-1256, no complex regex.
 * - Repairs the exact mojibake sequence produced when UTF-8 Arabic meem bytes are decoded as Windows-1256: ظ…
 * - Adds late CSS/JS guards to prevent ellipsis clipping and runtime mojibake display.
 */

$root = dirname(__DIR__);
$timestamp = date('Ymd_His');
$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'arabic-ui-v4-' . $timestamp;

function ensure_dir($dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function normalize_path($path) {
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function backup_file($file, $root, $backupRoot) {
    $relative = ltrim(substr($file, strlen($root)), DIRECTORY_SEPARATOR);
    $target = $backupRoot . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $relative;
    ensure_dir(dirname($target));
    copy($file, $target);
}

function fix_bytes($content) {
    // Replacement is UTF-8 Arabic letter MEEM: م => D9 85
    $meem = "\xD9\x85";
    $patterns = [
        "\xD8\xB8\xE2\x80\xA6", // ظ… literal mojibake of UTF-8 meem shown/saved as Windows-1256 chars
        "\xD8\xB8...",              // ظ... fallback
        "\xEF\xBF\xBD",            // replacement character �
        "\xC3\xAF\xC2\xBF\xC2\xBD", // ï¿½
        '&#65533;',
        '&#xFFFD;',
        '&amp;#65533;',
        '&amp;#xFFFD;',
    ];
    return str_replace($patterns, $meem, $content);
}

function file_should_scan($path) {
    $pathNorm = str_replace('\\', '/', $path);
    if (strpos($pathNorm, '/vendor/') !== false || strpos($pathNorm, '/storage/') !== false || strpos($pathNorm, '/node_modules/') !== false) return false;
    if (strpos($pathNorm, '/bootstrap/cache/') !== false) return false;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['php','css','js','json','env','blade.php'], true)) return true;
    if (str_ends_with($path, '.blade.php')) return true;
    return false;
}

function scan_files($root, $backupRoot) {
    $dirs = ['resources', 'app', 'routes', 'config', 'public/css', 'public/js'];
    $scanned = 0;
    $changed = [];
    foreach ($dirs as $dir) {
        $full = $root . DIRECTORY_SEPARATOR . normalize_path($dir);
        if (!is_dir($full)) continue;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) continue;
            $file = $fileInfo->getPathname();
            if (!file_should_scan($file)) continue;
            $scanned++;
            $old = file_get_contents($file);
            $new = fix_bytes($old);
            if ($new !== $old) {
                backup_file($file, $root, $backupRoot);
                file_put_contents($file, $new);
                $changed[] = ltrim(substr($file, strlen($root)), DIRECTORY_SEPARATOR);
            }
        }
    }
    return [$scanned, $changed];
}

function write_guard_assets($root, $backupRoot) {
    $cssPath = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'arabic-no-truncate-v4.css';
    $jsPath  = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'arabic-text-mojibake-v4.js';

    $css = <<<'CSS'
/* Arabic no-truncate V4: loaded late to override accidental ellipsis on Arabic UI text. */
html[dir="rtl"] h1,
html[dir="rtl"] h2,
html[dir="rtl"] h3,
html[dir="rtl"] h4,
html[dir="rtl"] h5,
html[dir="rtl"] h6,
html[dir="rtl"] p,
html[dir="rtl"] label,
html[dir="rtl"] th,
html[dir="rtl"] td,
html[dir="rtl"] a,
html[dir="rtl"] button,
html[dir="rtl"] .btn,
html[dir="rtl"] .nav-link,
html[dir="rtl"] .menu-link,
html[dir="rtl"] .sidebar a,
html[dir="rtl"] .app-sidebar a,
html[dir="rtl"] .sidebar .nav-link,
html[dir="rtl"] .app-sidebar .nav-link,
html[dir="rtl"] .page-title,
html[dir="rtl"] .page-subtitle,
html[dir="rtl"] .card-title,
html[dir="rtl"] .stat-title,
html[dir="rtl"] .stat-label,
html[dir="rtl"] .notification-title,
html[dir="rtl"] .notification-text,
html[dir="rtl"] [class*="title"],
html[dir="rtl"] [class*="label"],
html[dir="rtl"] [class*="text"] {
    text-overflow: clip !important;
    white-space: normal !important;
    overflow: visible !important;
    max-width: none !important;
    word-break: normal !important;
    overflow-wrap: normal !important;
}

html[dir="rtl"] .truncate,
html[dir="rtl"] .text-truncate,
html[dir="rtl"] [style*="text-overflow"],
html[dir="rtl"] [style*="white-space: nowrap"],
html[dir="rtl"] [style*="overflow: hidden"] {
    text-overflow: clip !important;
    white-space: normal !important;
    overflow: visible !important;
}

html[dir="rtl"] aside,
html[dir="rtl"] .sidebar,
html[dir="rtl"] .app-sidebar,
html[dir="rtl"] .sidebar-menu,
html[dir="rtl"] .nav-menu {
    overflow-x: visible !important;
}

html[dir="rtl"] .sidebar a,
html[dir="rtl"] .app-sidebar a,
html[dir="rtl"] aside a,
html[dir="rtl"] .sidebar button,
html[dir="rtl"] .app-sidebar button,
html[dir="rtl"] aside button {
    min-height: 44px !important;
    height: auto !important;
    line-height: 1.65 !important;
}
CSS;

    $js = <<<'JS'
(function () {
    'use strict';
    var BAD_ELLIPSIS = '\u0638\u2026'; // ظ…
    var BAD_DOTS = '\u0638...';        // ظ...
    var BAD_REPLACEMENT = '\uFFFD';
    var MEEM = '\u0645';               // م

    function fixString(value) {
        if (typeof value !== 'string' || value.length === 0) return value;
        return value
            .split(BAD_ELLIPSIS).join(MEEM)
            .split(BAD_DOTS).join(MEEM)
            .split(BAD_REPLACEMENT).join(MEEM);
    }

    function fixTextNode(node) {
        var fixed = fixString(node.nodeValue);
        if (fixed !== node.nodeValue) node.nodeValue = fixed;
    }

    function fixElementAttributes(el) {
        if (!el || !el.attributes) return;
        var attrs = ['title', 'aria-label', 'placeholder', 'alt', 'value'];
        attrs.forEach(function (name) {
            if (!el.hasAttribute || !el.hasAttribute(name)) return;
            var oldValue = el.getAttribute(name);
            var newValue = fixString(oldValue);
            if (newValue !== oldValue) el.setAttribute(name, newValue);
        });
    }

    function walk(root) {
        if (!root) return;
        if (root.nodeType === Node.TEXT_NODE) {
            fixTextNode(root);
            return;
        }
        if (root.nodeType === Node.ELEMENT_NODE) {
            fixElementAttributes(root);
        }
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, null);
        var node;
        while ((node = walker.nextNode())) {
            if (node.nodeType === Node.TEXT_NODE) fixTextNode(node);
            else if (node.nodeType === Node.ELEMENT_NODE) fixElementAttributes(node);
        }
    }

    function run() { walk(document.body || document.documentElement); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    setTimeout(run, 250);
    setTimeout(run, 1000);

    if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes && mutation.addedNodes.forEach(walk);
                if (mutation.type === 'characterData') walk(mutation.target);
            });
        });
        observer.observe(document.documentElement, { childList: true, subtree: true, characterData: true });
    }
})();
JS;

    foreach ([[$cssPath, $css], [$jsPath, $js]] as $pair) {
        [$path, $content] = $pair;
        if (file_exists($path)) backup_file($path, $root, $backupRoot);
        ensure_dir(dirname($path));
        file_put_contents($path, $content);
    }

    return [$cssPath, $jsPath];
}

function inject_assets($root, $backupRoot) {
    $layout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
    if (!file_exists($layout)) return false;
    $content = file_get_contents($layout);
    $original = $content;

    $cssLine = "    {{-- Arabic UI V4 final guard --}}\n    <link rel=\"stylesheet\" href=\"{{ asset('css/arabic-no-truncate-v4.css') }}?v={{ filemtime(public_path('css/arabic-no-truncate-v4.css')) }}\">\n";
    $jsLine = "    {{-- Arabic UI V4 final guard --}}\n    <script src=\"{{ asset('js/arabic-text-mojibake-v4.js') }}?v={{ filemtime(public_path('js/arabic-text-mojibake-v4.js')) }}\" defer></script>\n";

    if (strpos($content, 'arabic-no-truncate-v4.css') === false) {
        if (strpos($content, '</head>') !== false) {
            $content = str_replace('</head>', $cssLine . '</head>', $content);
        } else {
            $content = $cssLine . $content;
        }
    }
    if (strpos($content, 'arabic-text-mojibake-v4.js') === false) {
        if (strpos($content, '</body>') !== false) {
            $content = str_replace('</body>', $jsLine . '</body>', $content);
        } else {
            $content .= "\n" . $jsLine;
        }
    }

    $content = fix_bytes($content);
    if ($content !== $original) {
        backup_file($layout, $root, $backupRoot);
        file_put_contents($layout, $content);
        return true;
    }
    return false;
}

function try_fix_database($root, $backupRoot) {
    $autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $bootstrap = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
    if (!file_exists($autoload) || !file_exists($bootstrap)) {
        return ['skipped' => true, 'reason' => 'Laravel bootstrap files not found', 'tables' => 0, 'rows' => 0, 'cells' => 0];
    }

    try {
        require_once $autoload;
        $app = require $bootstrap;
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $db = app('db');
        $schemaName = $db->getDatabaseName();
        $tableRows = $db->select('SHOW TABLES');
        $tables = [];
        $prop = 'Tables_in_' . $schemaName;
        foreach ($tableRows as $row) {
            $arr = (array) $row;
            $tables[] = $arr[$prop] ?? reset($arr);
        }

        $skip = ['migrations','sessions','cache','cache_locks','jobs','job_batches','failed_jobs','password_reset_tokens'];
        $changedLog = [];
        $changedRows = 0;
        $changedCells = 0;
        $scannedTables = 0;

        foreach ($tables as $table) {
            if (in_array($table, $skip, true)) continue;
            $columnsInfo = $db->select('SHOW COLUMNS FROM `' . str_replace('`','``',$table) . '`');
            $primary = null;
            $textCols = [];
            foreach ($columnsInfo as $col) {
                $field = $col->Field;
                $type = strtolower($col->Type ?? '');
                if (($col->Key ?? '') === 'PRI' && $primary === null) $primary = $field;
                if (preg_match('/char|text|enum|set|json/i', $type)) $textCols[] = $field;
            }
            if (!$primary || !$textCols) continue;
            $scannedTables++;
            $selectCols = array_merge([$primary], $textCols);
            $quoted = array_map(fn($c) => '`' . str_replace('`','``',$c) . '`', $selectCols);
            $rows = $db->select('SELECT ' . implode(',', $quoted) . ' FROM `' . str_replace('`','``',$table) . '`');
            foreach ($rows as $row) {
                $updates = [];
                $before = [];
                $pk = $row->{$primary};
                foreach ($textCols as $col) {
                    $value = $row->{$col};
                    if ($value === null || !is_string($value)) continue;
                    $fixed = fix_bytes($value);
                    if ($fixed !== $value) {
                        $updates[$col] = $fixed;
                        $before[$col] = $value;
                        $changedCells++;
                    }
                }
                if ($updates) {
                    $db->table($table)->where($primary, $pk)->update($updates);
                    $changedRows++;
                    $changedLog[] = ['table' => $table, 'primary' => $primary, 'id' => $pk, 'before' => $before, 'after' => $updates];
                }
            }
        }

        if ($changedLog) {
            ensure_dir($backupRoot);
            file_put_contents($backupRoot . DIRECTORY_SEPARATOR . 'db-changed-cells.json', json_encode($changedLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return ['skipped' => false, 'tables' => $scannedTables, 'rows' => $changedRows, 'cells' => $changedCells];
    } catch (Throwable $e) {
        return ['skipped' => true, 'reason' => $e->getMessage(), 'tables' => 0, 'rows' => 0, 'cells' => 0];
    }
}

ensure_dir($backupRoot);
[$scanned, $changedFiles] = scan_files($root, $backupRoot);
write_guard_assets($root, $backupRoot);
$layoutChanged = inject_assets($root, $backupRoot);
$dbResult = try_fix_database($root, $backupRoot);

$summary = [
    'time' => date('c'),
    'backup' => $backupRoot,
    'scanned_files' => $scanned,
    'changed_files_count' => count($changedFiles),
    'changed_files' => $changedFiles,
    'layout_changed' => $layoutChanged,
    'database' => $dbResult,
];
file_put_contents($backupRoot . DIRECTORY_SEPARATOR . 'summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "DONE: Arabic UI V4 repair completed.\n";
echo "Backup: {$backupRoot}\n";
echo "Scanned files: {$scanned}\n";
echo "Changed files: " . count($changedFiles) . "\n";
echo "Layout injected: " . ($layoutChanged ? 'yes' : 'already present/no change') . "\n";
if ($dbResult['skipped'] ?? false) {
    echo "Database fix: skipped (" . ($dbResult['reason'] ?? 'unknown') . ")\n";
} else {
    echo "Database scanned tables: {$dbResult['tables']} | changed rows: {$dbResult['rows']} | changed cells: {$dbResult['cells']}\n";
}
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

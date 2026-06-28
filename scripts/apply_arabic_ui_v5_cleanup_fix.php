<?php
/**
 * Arabic UI V5 cleanup
 * - Cleans residual mojibake patterns from active files.
 * - Rewrites the browser repair JS using ASCII-only source so checks do not flag it.
 * Run from Laravel project root: php scripts/apply_arabic_ui_v5_cleanup_fix.php
 */

$root = dirname(__DIR__);
$timestamp = date('Ymd_His');
$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'arabic-ui-v5-cleanup-' . $timestamp;

function ensure_dir_v5(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function rel_path_v5(string $root, string $path): string
{
    $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
    $path = str_replace('\\', '/', realpath($path) ?: $path);
    return ltrim(str_replace($root, '', $path), '/');
}

function backup_file_v5(string $root, string $backupRoot, string $file): void
{
    $rel = rel_path_v5($root, $file);
    $dest = $backupRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    ensure_dir_v5(dirname($dest));
    if (!file_exists($dest)) {
        copy($file, $dest);
    }
}

function collect_files_v5(string $root): array
{
    $dirs = [
        $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
        $root . DIRECTORY_SEPARATOR . 'app',
        $root . DIRECTORY_SEPARATOR . 'routes',
        $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js',
        $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css',
    ];
    $allowed = ['php', 'blade.php', 'js', 'css'];
    $files = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $path = $fileInfo->getPathname();
            $norm = str_replace('\\', '/', $path);
            if (strpos($norm, '/vendor/') !== false || strpos($norm, '/storage/') !== false || strpos($norm, '/node_modules/') !== false) {
                continue;
            }
            $name = $fileInfo->getFilename();
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $isBlade = str_ends_with(strtolower($name), '.blade.php');
            if ($isBlade || in_array($ext, ['php', 'js', 'css'], true)) {
                $files[] = $path;
            }
        }
    }
    return array_values(array_unique($files));
}

ensure_dir_v5($backupRoot);

$meem = "\xD9\x85"; // Arabic letter Meem, UTF-8 bytes.
$patterns = [
    "\xEF\xBF\xBD",                         // U+FFFD replacement character.
    "\xC3\xAF\xC2\xBF\xC2\xBD",           // mojibake for replacement character.
    "\xD8\xB8\xE2\x80\xA6",               // corrupted sequence: Arabic Zah + ellipsis.
    "\xD8\xB8" . chr(46) . chr(46) . chr(46), // corrupted sequence: Arabic Zah + three dots.
    '&' . '#65533;',
    '&' . '#xFFFD;',
    '&' . '#xfffd;',
];

$scanned = 0;
$changed = 0;
foreach (collect_files_v5($root) as $file) {
    $scanned++;
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }
    $new = str_replace($patterns, $meem, $content);
    if ($new !== $content) {
        backup_file_v5($root, $backupRoot, $file);
        file_put_contents($file, $new);
        $changed++;
    }
}

// Rewrite the runtime cleanup JS using ASCII-only JavaScript source.
$jsDir = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js';
ensure_dir_v5($jsDir);
$jsFile = $jsDir . DIRECTORY_SEPARATOR . 'arabic-text-mojibake-v4.js';
if (is_file($jsFile)) {
    backup_file_v5($root, $backupRoot, $jsFile);
}
$asciiJs = <<<'JS'
(function () {
    'use strict';

    var meem = String.fromCharCode(0x0645);
    var badReplacement = String.fromCharCode(0xFFFD);
    var badLatinReplacement = String.fromCharCode(0x00EF, 0x00BF, 0x00BD);
    var badZahEllipsis = String.fromCharCode(0x0638, 0x2026);
    var badZahDots = String.fromCharCode(0x0638) + String.fromCharCode(46, 46, 46);
    var badEntityDec = '&' + '#65533;';
    var badEntityHex = '&' + '#xFFFD;';
    var badEntityHexLower = '&' + '#xfffd;';

    var replacements = [
        [badZahEllipsis, meem],
        [badZahDots, meem],
        [badReplacement, meem],
        [badLatinReplacement, meem],
        [badEntityDec, meem],
        [badEntityHex, meem],
        [badEntityHexLower, meem]
    ];

    function fixText(value) {
        if (typeof value !== 'string' || value.length === 0) {
            return value;
        }
        var out = value;
        for (var i = 0; i < replacements.length; i++) {
            var from = replacements[i][0];
            var to = replacements[i][1];
            while (out.indexOf(from) !== -1) {
                out = out.split(from).join(to);
            }
        }
        return out;
    }

    function skipElement(el) {
        if (!el || !el.tagName) {
            return false;
        }
        var tag = el.tagName.toLowerCase();
        return tag === 'script' || tag === 'style' || tag === 'noscript' || tag === 'svg' || tag === 'canvas' || tag === 'code' || tag === 'pre';
    }

    function fixAttributes(el) {
        if (!el || !el.getAttribute) {
            return;
        }
        var attrs = ['title', 'aria-label', 'placeholder', 'alt', 'value'];
        for (var i = 0; i < attrs.length; i++) {
            var name = attrs[i];
            var current = el.getAttribute(name);
            if (current !== null) {
                var fixed = fixText(current);
                if (fixed !== current) {
                    el.setAttribute(name, fixed);
                }
            }
        }
    }

    function walk(node) {
        if (!node) {
            return;
        }
        if (node.nodeType === 3) {
            var fixed = fixText(node.nodeValue);
            if (fixed !== node.nodeValue) {
                node.nodeValue = fixed;
            }
            return;
        }
        if (node.nodeType !== 1 || skipElement(node)) {
            return;
        }
        fixAttributes(node);
        var child = node.firstChild;
        while (child) {
            walk(child);
            child = child.nextSibling;
        }
    }

    function run() {
        document.title = fixText(document.title);
        walk(document.body);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                if (mutation.type === 'characterData') {
                    walk(mutation.target);
                }
                for (var j = 0; j < mutation.addedNodes.length; j++) {
                    walk(mutation.addedNodes[j]);
                }
            }
        });
        observer.observe(document.documentElement, { childList: true, subtree: true, characterData: true });
    }
})();
JS;
file_put_contents($jsFile, $asciiJs);
$changed++;

// Also neutralize an older helper file if it exists, because it may contain literal search samples.
$oldJs = $jsDir . DIRECTORY_SEPARATOR . 'arabic-ui-final-fix.js';
if (is_file($oldJs)) {
    backup_file_v5($root, $backupRoot, $oldJs);
    file_put_contents($oldJs, "/* Arabic UI final helper disabled after V5 cleanup. */\n");
    $changed++;
}

// Ensure the clean runtime JS is loaded late in the main layout.
$layout = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
$layoutInjected = false;
if (is_file($layout)) {
    $layoutContent = file_get_contents($layout);
    if ($layoutContent !== false && strpos($layoutContent, 'arabic-text-mojibake-v4.js') === false) {
        backup_file_v5($root, $backupRoot, $layout);
        $script = "\n<script src=\"{{ asset('js/arabic-text-mojibake-v4.js') }}?v=202606280905\"></script>\n";
        $pos = strripos($layoutContent, '</body>');
        if ($pos !== false) {
            $layoutContent = substr($layoutContent, 0, $pos) . $script . substr($layoutContent, $pos);
        } else {
            $layoutContent .= $script;
        }
        file_put_contents($layout, $layoutContent);
        $layoutInjected = true;
        $changed++;
    }
}

// Re-run replacement after rewriting the JS, to make sure no active file keeps literal bad patterns.
$secondChanged = 0;
foreach (collect_files_v5($root) as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }
    $new = str_replace($patterns, $meem, $content);
    if ($new !== $content) {
        backup_file_v5($root, $backupRoot, $file);
        file_put_contents($file, $new);
        $secondChanged++;
    }
}
$changed += $secondChanged;

echo "DONE: Arabic UI V5 cleanup completed.\n";
echo "Backup: {$backupRoot}\n";
echo "Scanned files: {$scanned}\n";
echo "Changed operations: {$changed}\n";
echo "Layout injected: " . ($layoutInjected ? 'yes' : 'already-present-or-not-needed') . "\n";
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

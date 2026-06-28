<?php
/**
 * DocumentArchive - QR Show Cleanup V5 Final Fix
 * هدفه: إزالة كل آثار QR المتسربة من صفحة عرض الكتاب فقط، مع ترك QR في صفحة الطباعة.
 */

function fail(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function projectRoot(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 8; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan') && is_dir($dir . DIRECTORY_SEPARATOR . 'resources')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    fail('تعذر تحديد جذر مشروع Laravel. شغّل السكربت من داخل E:\\LaravelProjects\\DocumentArchive');
}

function ensureDir(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
        fail('تعذر إنشاء المجلد: ' . $path);
    }
}

function copyIfExists(string $from, string $to): void
{
    if (is_file($from)) {
        ensureDir(dirname($to));
        if (!copy($from, $to)) {
            fail('تعذر نسخ backup من: ' . $from);
        }
    }
}

function removeBackupDirs(string $root): array
{
    $removed = [];
    foreach (['app', 'resources', 'routes'] as $rel) {
        $base = $root . DIRECTORY_SEPARATOR . $rel;
        if (!is_dir($base)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir() && str_starts_with($file->getFilename(), '_backup')) {
                $path = $file->getPathname();
                deleteDir($path);
                $removed[] = $path;
            }
        }
    }
    return $removed;
}

function deleteDir(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($dir);
}

function findMatchingHtmlEnd(string $content, int $start, string $tag): ?int
{
    $tail = substr($content, $start);
    if (!preg_match_all('~</?' . preg_quote($tag, '~') . '\b[^>]*>~i', $tail, $matches, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $depth = 0;
    foreach ($matches[0] as $m) {
        $token = $m[0];
        $offset = $m[1];
        if (preg_match('~^</~', $token)) {
            $depth--;
            if ($depth <= 0) {
                return $start + $offset + strlen($token);
            }
        } else {
            // self closing tags are not expected for div/section/figure/asides, but keep safe
            if (!preg_match('~/\s*>$~', $token)) {
                $depth++;
            }
        }
    }
    return null;
}

function removeBlockAroundMarker(string $content, string $marker): string
{
    while (($pos = strpos($content, $marker)) !== false) {
        $before = substr($content, 0, $pos);
        $candidates = [];
        foreach (['div', 'section', 'figure', 'aside'] as $tag) {
            $p = strripos($before, '<' . $tag);
            if ($p !== false) $candidates[$p] = $tag;
        }
        $ifPos = strripos($before, '@if');
        if ($ifPos !== false) $candidates[$ifPos] = '@if';

        if (!$candidates) {
            $lineStart = strrpos($before, "\n");
            $start = $lineStart === false ? 0 : $lineStart + 1;
            $lineEnd = strpos($content, "\n", $pos);
            $end = $lineEnd === false ? strlen($content) : $lineEnd + 1;
            $content = substr($content, 0, $start) . substr($content, $end);
            continue;
        }

        krsort($candidates);
        $start = array_key_first($candidates);
        $tag = $candidates[$start];
        $end = null;
        if ($tag === '@if') {
            $endif = strpos($content, '@endif', $pos);
            $end = $endif === false ? null : $endif + strlen('@endif');
        } else {
            $end = findMatchingHtmlEnd($content, $start, $tag);
        }

        if ($end === null) {
            $lineEnd = strpos($content, "\n", $pos);
            $end = $lineEnd === false ? strlen($content) : $lineEnd + 1;
        }
        $content = substr($content, 0, $start) . "\n" . substr($content, $end);
    }
    return $content;
}

function removeQrResidueFromShow(string $content): string
{
    $markers = [
        'document-qr-card',
        'data-document-qr',
        'da-document-qr',
        'da-qr-print',
        'document-print-qr',
        'qr-print-position',
        '/qr.svg',
        'qr.svg',
    ];

    foreach ($markers as $marker) {
        $content = removeBlockAroundMarker($content, $marker);
    }

    // Remove remaining standalone image/link/style/script lines that point to QR assets.
    $lines = preg_split('/\R/', $content);
    $clean = [];
    foreach ($lines as $line) {
        $lower = strtolower($line);
        $looksQr = str_contains($lower, 'qr.svg')
            || str_contains($lower, 'document-qr-card')
            || str_contains($lower, 'document-qr')
            || str_contains($lower, 'data-document-qr')
            || str_contains($lower, 'da-qr-print')
            || str_contains($lower, 'document-print-qr');
        if ($looksQr) {
            continue;
        }
        $clean[] = $line;
    }

    $content = implode(PHP_EOL, $clean);

    // Remove excessive blank lines caused by cleanup.
    $content = preg_replace("~\n{3,}~", "\n\n", $content) ?? $content;
    return $content;
}

function insertOnceBefore(string $content, string $needle, string $snippet, string $identity): string
{
    if (str_contains($content, $identity)) return $content;
    $pos = stripos($content, $needle);
    if ($pos === false) {
        return rtrim($content) . PHP_EOL . $snippet . PHP_EOL;
    }
    return substr($content, 0, $pos) . $snippet . PHP_EOL . substr($content, $pos);
}

$root = projectRoot();
$timestamp = date('Ymd_His');
$backupDir = $root . DIRECTORY_SEPARATOR . 'storage/app/private/patch-backups/qr-show-cleanup-v5-' . $timestamp;
ensureDir($backupDir);

$show = $root . DIRECTORY_SEPARATOR . 'resources/views/documents/show.blade.php';
$layout = $root . DIRECTORY_SEPARATOR . 'resources/views/layouts/app.blade.php';
if (!is_file($show)) fail('الملف غير موجود: resources/views/documents/show.blade.php');
if (!is_file($layout)) fail('الملف غير موجود: resources/views/layouts/app.blade.php');

copyIfExists($show, $backupDir . DIRECTORY_SEPARATOR . 'resources/views/documents/show.blade.php');
copyIfExists($layout, $backupDir . DIRECTORY_SEPARATOR . 'resources/views/layouts/app.blade.php');

$showContent = file_get_contents($show);
if ($showContent === false) fail('تعذر قراءة show.blade.php');
$showContent = removeQrResidueFromShow($showContent);
file_put_contents($show, $showContent);

$cssPath = $root . DIRECTORY_SEPARATOR . 'public/css/document-show-qr-cleanup-v5.css';
$jsPath = $root . DIRECTORY_SEPARATOR . 'public/js/document-show-qr-cleanup-v5.js';
ensureDir(dirname($cssPath));
ensureDir(dirname($jsPath));
file_put_contents($cssPath, <<<'CSS'
/* DocumentArchive QR show cleanup V5
   حماية إضافية: لا تسمح بظهور QR الطباعة داخل صفحة عرض الكتاب فقط. */
body.da-document-show .document-qr-card,
body.da-document-show [data-document-qr],
body.da-document-show .da-document-qr,
body.da-document-show .da-qr-print,
body.da-document-show .document-print-qr,
body.da-document-show .qr-print-position,
body.da-document-show img[src*="qr.svg"],
body.da-document-show img[src*="/qr"] {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
}
CSS);

file_put_contents($jsPath, <<<'JS'
(function () {
    var path = window.location.pathname || '';
    var isDocumentShow = /^\/documents\/\d+\/?$/.test(path);
    if (!isDocumentShow) return;

    document.body.classList.add('da-document-show');

    function removeLeakedQr() {
        var selectors = [
            '.document-qr-card',
            '[data-document-qr]',
            '.da-document-qr',
            '.da-qr-print',
            '.document-print-qr',
            '.qr-print-position',
            'img[src*="qr.svg"]',
            'img[src*="/qr"]'
        ];
        document.querySelectorAll(selectors.join(',')).forEach(function (el) {
            var box = el.closest('.document-qr-card') || el.closest('[data-document-qr]') || el;
            if (box && box.parentNode) box.parentNode.removeChild(box);
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
JS);

$layoutContent = file_get_contents($layout);
if ($layoutContent === false) fail('تعذر قراءة layout');
$cssSnippet = "<link rel=\"stylesheet\" href=\"{{ asset('css/document-show-qr-cleanup-v5.css') }}\" data-da-qr-show-cleanup-v5>";
$jsSnippet = "<script src=\"{{ asset('js/document-show-qr-cleanup-v5.js') }}\" defer data-da-qr-show-cleanup-v5></script>";
$layoutContent = insertOnceBefore($layoutContent, '</head>', $cssSnippet, 'document-show-qr-cleanup-v5.css');
$layoutContent = insertOnceBefore($layoutContent, '</body>', $jsSnippet, 'document-show-qr-cleanup-v5.js');
file_put_contents($layout, $layoutContent);

$removed = removeBackupDirs($root);

echo "DONE: تم تنظيف QR من صفحة عرض الكتاب V5.\n";
echo "Backup: {$backupDir}\n";
if ($removed) {
    echo "Removed backup dirs: " . count($removed) . "\n";
}
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

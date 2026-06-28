<?php
/**
 * QR Show Cleanup V4
 * هدف السكربت:
 * - تنظيف صفحة عرض الكتاب من أي QR أو عناصر طباعة متسربة.
 * - إبقاء QR الخاص بالإحداثيات في صفحة طباعة رقم الكتاب فقط.
 * - إضافة CSS/JS حارس يمنع ظهور QR داخل /documents/{id} حتى لو بقي أثر قديم.
 * - حفظ النسخ الاحتياطية خارج app/resources/routes داخل storage/app/private/patch-backups.
 */

function projectRoot(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 6; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan') && is_dir($dir . DIRECTORY_SEPARATOR . 'resources')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    fwrite(STDERR, "ERROR: لم يتم العثور على جذر مشروع Laravel. شغّل السكربت من داخل المشروع.\n");
    exit(1);
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = array_diff(scandir($dir) ?: [], ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) rrmdir($path);
        else @unlink($path);
    }
    @rmdir($dir);
}

function cleanupBackupDirs(string $root): void
{
    foreach (['app', 'resources', 'routes'] as $top) {
        $base = $root . DIRECTORY_SEPARATOR . $top;
        if (!is_dir($base)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir() && str_starts_with($file->getFilename(), '_backup')) {
                rrmdir($file->getPathname());
            }
        }
    }
}

function backupFile(string $root, string $file, string $backupRoot): void
{
    if (!is_file($file)) return;
    $relative = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR . '/\\');
    $target = $backupRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
    copy($file, $target);
}

function writeFile(string $path, string $content): void
{
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
    file_put_contents($path, $content);
}

function injectBeforeEndHead(string $content, string $snippet): string
{
    if (str_contains($content, 'document-show-qr-cleanup-v4.css')) return $content;
    if (stripos($content, '</head>') !== false) {
        return str_ireplace('</head>', $snippet . "\n</head>", $content);
    }
    return $content . "\n" . $snippet . "\n";
}

function injectBeforeEndBody(string $content, string $snippet): string
{
    if (str_contains($content, 'document-show-qr-cleanup-v4.js')) return $content;
    if (stripos($content, '</body>') !== false) {
        return str_ireplace('</body>', $snippet . "\n</body>", $content);
    }
    return $content . "\n" . $snippet . "\n";
}

function removeQrFragmentsFromShow(string $content): string
{
    // إزالة روابط CSS/JS القديمة الخاصة بالـ QR من صفحة العرض فقط.
    $lines = preg_split('/\R/', $content);
    $filtered = [];
    $skipBlock = false;
    $skipDepth = 0;

    $needles = [
        'qr.svg', 'qr-code', 'qr_code', 'document-qr', 'document_qr',
        'print-qr', 'print_qr', 'qr-print', 'qr_print', 'da-qr', 'daQr',
        'رمز الوصول الإلكتروني', 'رمز الوصول الالكتروني',
        'qr-print-position', 'qr_position', 'QrCode', 'QrPrint',
        'settings.qr-print-position'
    ];

    foreach ($lines as $line) {
        $lower = strtolower($line);
        $hit = false;
        foreach ($needles as $needle) {
            if (str_contains($lower, strtolower($needle))) { $hit = true; break; }
        }

        // عند وجود بداية div أو section فيه QR، نحاول إسقاط البلوك المباشر.
        if (!$skipBlock && $hit && preg_match('/<\s*(div|section|aside|figure)\b/i', $line)) {
            $skipBlock = true;
            $skipDepth = substr_count(strtolower($line), '<div') + substr_count(strtolower($line), '<section') + substr_count(strtolower($line), '<aside') + substr_count(strtolower($line), '<figure');
            $skipDepth -= substr_count(strtolower($line), '</div>') + substr_count(strtolower($line), '</section>') + substr_count(strtolower($line), '</aside>') + substr_count(strtolower($line), '</figure>');
            if ($skipDepth <= 0) { $skipBlock = false; }
            continue;
        }

        if ($skipBlock) {
            $skipDepth += substr_count(strtolower($line), '<div') + substr_count(strtolower($line), '<section') + substr_count(strtolower($line), '<aside') + substr_count(strtolower($line), '<figure');
            $skipDepth -= substr_count(strtolower($line), '</div>') + substr_count(strtolower($line), '</section>') + substr_count(strtolower($line), '</aside>') + substr_count(strtolower($line), '</figure>');
            if ($skipDepth <= 0) { $skipBlock = false; }
            continue;
        }

        // حذف الأسطر المفردة التي تحتوي QR مثل img/link/include/style/script.
        if ($hit && preg_match('/(<\s*(img|link|script|style|iframe|object)\b|@include|asset\(|route\(|url\(|<svg|\/qr\.svg)/i', $line)) {
            continue;
        }

        $filtered[] = $line;
    }

    $content = implode(PHP_EOL, $filtered);

    // إزالة بقايا بلوكات محددة بأسماء كلاسات QR إن بقيت على سطر واحد.
    $content = preg_replace('/<div\b[^>]*(?:da-qr|document-qr|print-qr|qr-print|qr-code|qr-card)[^>]*>.*?<\/div>/is', '', $content) ?? $content;
    $content = preg_replace('/<figure\b[^>]*(?:da-qr|document-qr|print-qr|qr-print|qr-code|qr-card)[^>]*>.*?<\/figure>/is', '', $content) ?? $content;

    return $content;
}

$root = projectRoot();
cleanupBackupDirs($root);

$timestamp = date('Ymd_His');
$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'qr-show-cleanup-v4-' . $timestamp;
@mkdir($backupRoot, 0777, true);

$layoutCandidates = [
    $root . '/resources/views/layouts/app.blade.php',
    $root . '/resources/views/layouts/admin.blade.php',
];
$showFile = $root . '/resources/views/documents/show.blade.php';

$touched = [];

if (is_file($showFile)) {
    backupFile($root, $showFile, $backupRoot);
    $content = file_get_contents($showFile);
    $new = removeQrFragmentsFromShow($content);
    if ($new !== $content) {
        file_put_contents($showFile, $new);
        $touched[] = 'resources/views/documents/show.blade.php';
    }
}

$cssPath = $root . '/public/css/document-show-qr-cleanup-v4.css';
$jsPath = $root . '/public/js/document-show-qr-cleanup-v4.js';

$css = <<<'CSS'
/* QR Show Cleanup V4
   يمنع ظهور عناصر QR الخاصة بالطباعة داخل صفحة عرض الكتاب فقط. */
body.da-document-show-page .da-print-qr,
body.da-document-show-page .da-qr-print,
body.da-document-show-page .da-qr-print-box,
body.da-document-show-page .da-qr-print-layer,
body.da-document-show-page .da-document-qr,
body.da-document-show-page .document-qr,
body.da-document-show-page .document-qr-card,
body.da-document-show-page .document-qr-box,
body.da-document-show-page .qr-print-card,
body.da-document-show-page .qr-print-box,
body.da-document-show-page .qr-position-box,
body.da-document-show-page .qr-code-card,
body.da-document-show-page .qr-card,
body.da-document-show-page [data-document-qr],
body.da-document-show-page [data-qr-print] {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
}

body.da-document-show-page .page-actions,
body.da-document-show-page .document-actions,
body.da-document-show-page .action-buttons,
body.da-document-show-page .toolbar-actions,
body.da-document-show-page .header-actions {
    position: static !important;
    inset: auto !important;
    transform: none !important;
    width: auto !important;
    height: auto !important;
    min-width: 0 !important;
    max-width: none !important;
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    gap: 8px !important;
}
CSS;

$js = <<<'JS'
(function () {
    function isDocumentShowPage() {
        return /^\/documents\/\d+\/?$/.test(window.location.pathname);
    }

    function markPage() {
        if (isDocumentShowPage()) {
            document.body.classList.add('da-document-show-page');
        }
    }

    function removeQrLeaks() {
        if (!isDocumentShowPage()) return;
        markPage();

        const selectors = [
            '.da-print-qr', '.da-qr-print', '.da-qr-print-box', '.da-qr-print-layer',
            '.da-document-qr', '.document-qr', '.document-qr-card', '.document-qr-box',
            '.qr-print-card', '.qr-print-box', '.qr-position-box', '.qr-code-card', '.qr-card',
            '[data-document-qr]', '[data-qr-print]',
            'img[src*="/qr.svg"]', 'img[src*="qr.svg"]',
            'object[data*="/qr.svg"]', 'iframe[src*="/qr.svg"]',
            'svg[data-qr]', 'canvas[data-qr]'
        ];

        document.querySelectorAll(selectors.join(',')).forEach(function (el) {
            const container = el.closest('.da-print-qr, .da-qr-print, .da-qr-print-box, .da-document-qr, .document-qr-card, .document-qr-box, .qr-print-card, .qr-print-box, .qr-code-card, .qr-card, [data-document-qr], [data-qr-print], .card, .panel, .box, figure, aside');
            (container || el).remove();
        });

        // أي بطاقة تحتوي عبارة رمز الوصول الإلكتروني وفيها صورة QR يتم حذفها.
        Array.from(document.querySelectorAll('div, section, figure, aside')).forEach(function (box) {
            const text = (box.textContent || '').trim();
            if ((text.includes('رمز الوصول الإلكتروني') || text.includes('رمز الوصول الالكتروني')) && box.querySelector('img, svg, canvas')) {
                box.remove();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        markPage();
        removeQrLeaks();
        setTimeout(removeQrLeaks, 100);
        setTimeout(removeQrLeaks, 500);
        setTimeout(removeQrLeaks, 1200);
    });
})();
JS;

backupFile($root, $cssPath, $backupRoot);
backupFile($root, $jsPath, $backupRoot);
writeFile($cssPath, $css);
writeFile($jsPath, $js);
$touched[] = 'public/css/document-show-qr-cleanup-v4.css';
$touched[] = 'public/js/document-show-qr-cleanup-v4.js';

$headSnippet = "<link rel=\"stylesheet\" href=\"{{ asset('css/document-show-qr-cleanup-v4.css') }}?v=4\">";
$bodySnippet = "<script src=\"{{ asset('js/document-show-qr-cleanup-v4.js') }}?v=4\"></script>";
$layoutTouched = false;
foreach ($layoutCandidates as $layout) {
    if (!is_file($layout)) continue;
    backupFile($root, $layout, $backupRoot);
    $content = file_get_contents($layout);
    $new = injectBeforeEndHead($content, $headSnippet);
    $new = injectBeforeEndBody($new, $bodySnippet);
    if ($new !== $content) {
        file_put_contents($layout, $new);
        $touched[] = str_replace($root . DIRECTORY_SEPARATOR, '', $layout);
        $layoutTouched = true;
        break;
    }
}

if (!$layoutTouched) {
    fwrite(STDERR, "WARNING: لم يتم العثور على layout مناسب لحقن CSS/JS. تم إنشاء ملفات التنظيف فقط.\n");
}

echo "DONE: تم تنظيف تسريب QR من صفحة عرض الكتاب وإضافة حارس CSS/JS.\n";
echo "Backup: " . $backupRoot . "\n";
echo "Files touched:\n- " . implode("\n- ", array_unique($touched)) . "\n";
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

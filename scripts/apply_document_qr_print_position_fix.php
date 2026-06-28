<?php
/**
 * DocumentArchive - QR print position fix
 * Moves QR code block into the A4 print paper container and pins it inside the page.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if (!$root || !is_file($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: تأكد أنك تفك الضغط داخل جذر مشروع Laravel. ملف artisan غير موجود.\n");
    exit(1);
}

$viewsDir = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
$documentsDir = $viewsDir . DIRECTORY_SEPARATOR . 'documents';
if (!is_dir($documentsDir)) {
    fwrite(STDERR, "ERROR: مجلد resources/views/documents غير موجود.\n");
    exit(1);
}

$backupDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'qr-print-position-' . date('Ymd-His');
if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: تعذر إنشاء مجلد النسخ الاحتياطي: {$backupDir}\n");
    exit(1);
}

function normalizePath(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function readFileText(string $file): string
{
    $content = file_get_contents($file);
    if ($content === false) {
        throw new RuntimeException("تعذر قراءة الملف: {$file}");
    }
    return $content;
}

function writeFileText(string $file, string $content): void
{
    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException("تعذر كتابة الملف: {$file}");
    }
}

function findMatchingDivEnd(string $html, int $divStart): ?int
{
    $pattern = '/<\/?div\b[^>]*>/i';
    if (!preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE, $divStart)) {
        return null;
    }

    $depth = 0;
    foreach ($matches[0] as $m) {
        $tag = $m[0];
        $pos = $m[1];
        if (stripos($tag, '</div') === 0) {
            $depth--;
            if ($depth === 0) {
                return $pos + strlen($tag);
            }
        } else {
            $depth++;
        }
    }
    return null;
}

function findBestPageContainer(string $html): ?array
{
    $pattern = '/<div\b[^>]*class=["\'][^"\']*(?:a4|paper|print\-paper|print\-page|document\-print|official\-print|page)[^"\']*["\'][^>]*>/i';
    if (!preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    foreach ($matches[0] as $m) {
        $tag = $m[0];
        $start = $m[1];
        // Avoid matching QR container itself.
        if (stripos($tag, 'qr') !== false) {
            continue;
        }
        $end = findMatchingDivEnd($html, $start);
        if ($end !== null) {
            return [$start, $end, $tag];
        }
    }

    return null;
}

function findQrBlock(string $html): ?array
{
    $qrImgPattern = '/<img\b[^>]*(?:qr\.svg|documents\.qr|qr\-code|qr)[^>]*>/i';
    if (!preg_match($qrImgPattern, $html, $imgMatch, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $img = $imgMatch[0][0];
    $imgPos = $imgMatch[0][1];

    $before = substr($html, 0, $imgPos);
    $divStart = strripos($before, '<div');
    if ($divStart === false) {
        return [$imgPos, $imgPos + strlen($img), $img];
    }

    $divEnd = findMatchingDivEnd($html, $divStart);
    if ($divEnd === null || $divEnd < $imgPos) {
        return [$imgPos, $imgPos + strlen($img), $img];
    }

    $block = substr($html, $divStart, $divEnd - $divStart);
    return [$divStart, $divEnd, $block];
}

function extractQrImage(string $block): string
{
    if (preg_match('/<img\b[^>]*>/i', $block, $m)) {
        $img = $m[0];
        // Remove conflicting sizing classes/styles, then add stable class.
        $img = preg_replace('/\sclass=["\'][^"\']*["\']/i', '', $img) ?? $img;
        $img = preg_replace('/\sstyle=["\'][^"\']*["\']/i', '', $img) ?? $img;
        $img = preg_replace('/<img\b/i', '<img class="official-document-qr-image"', $img, 1) ?? $img;
        return $img;
    }

    return '<img class="official-document-qr-image" src="{{ route(\'documents.qr\', $document) }}" alt="رمز QR للكتاب">';
}

function addStyleBlock(string $html): string
{
    $marker = 'document-qr-print-position-fix-v10';
    if (strpos($html, $marker) !== false) {
        return $html;
    }

    $style = <<<'BLADE'

<style id="document-qr-print-position-fix-v10">
    @page {
        size: A4 portrait;
        margin: 0;
    }

    .a4-page,
    .a4-paper,
    .paper,
    .print-paper,
    .print-page,
    .document-print-page,
    .official-print-page,
    .page {
        position: relative !important;
        overflow: hidden !important;
    }

    .official-document-qr-inside-page {
        position: absolute !important;
        left: 50% !important;
        bottom: 28mm !important;
        transform: translateX(-50%) !important;
        z-index: 20 !important;
        width: 42mm !important;
        text-align: center !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        direction: rtl !important;
        color: #111827 !important;
        font-family: Cairo, Tahoma, Arial, sans-serif !important;
        page-break-inside: avoid !important;
    }

    .official-document-qr-inside-page .official-document-qr-image,
    .official-document-qr-inside-page img,
    .official-document-qr-inside-page svg {
        display: block !important;
        width: 30mm !important;
        height: 30mm !important;
        max-width: 30mm !important;
        max-height: 30mm !important;
        margin: 0 auto 3mm auto !important;
        padding: 2mm !important;
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 3mm !important;
        box-sizing: border-box !important;
    }

    .official-document-qr-caption {
        display: block !important;
        font-size: 10px !important;
        line-height: 1.5 !important;
        color: #374151 !important;
        white-space: nowrap !important;
    }

    @media print {
        body {
            background: #ffffff !important;
        }

        .official-document-qr-inside-page {
            bottom: 22mm !important;
        }

        .no-print,
        .print-actions,
        .btn,
        button {
            display: none !important;
        }
    }
</style>
BLADE;

    if (stripos($html, '</head>') !== false) {
        return preg_replace('/<\/head>/i', $style . "\n</head>", $html, 1) ?? ($style . $html);
    }

    return $style . "\n" . $html;
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($documentsDir, FilesystemIterator::SKIP_DOTS));
$candidates = [];
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }
    $path = $fileInfo->getPathname();
    if (!str_ends_with($path, '.blade.php')) {
        continue;
    }
    $content = readFileText($path);
    if (stripos($content, 'qr.svg') !== false || stripos($content, 'documents.qr') !== false || stripos($content, 'qr-code') !== false || stripos($content, '/qr') !== false) {
        $candidates[] = $path;
    }
}

if (empty($candidates)) {
    fwrite(STDERR, "ERROR: لم أجد ملف Blade يحتوي QR داخل resources/views/documents.\n");
    exit(1);
}

$updated = [];
foreach ($candidates as $file) {
    $html = readFileText($file);
    $qr = findQrBlock($html);
    if ($qr === null) {
        continue;
    }

    [$qrStart, $qrEnd, $qrBlock] = $qr;
    $qrImg = extractQrImage($qrBlock);

    $standardQrBlock = "\n        <div class=\"official-document-qr-inside-page\">\n            {$qrImg}\n            <div class=\"official-document-qr-caption\">رمز الوصول الإلكتروني للكتاب</div>\n        </div>\n";

    // Remove old QR block.
    $htmlWithoutQr = substr($html, 0, $qrStart) . substr($html, $qrEnd);

    $container = findBestPageContainer($htmlWithoutQr);
    if ($container === null) {
        // Fallback: keep QR block but style it; better than failing.
        $htmlPatched = $htmlWithoutQr . $standardQrBlock;
    } else {
        [$pageStart, $pageEnd, $tag] = $container;
        // Insert before container closing </div>.
        $htmlPatched = substr($htmlWithoutQr, 0, $pageEnd - strlen('</div>')) . $standardQrBlock . substr($htmlWithoutQr, $pageEnd - strlen('</div>'));
    }

    $htmlPatched = addStyleBlock($htmlPatched);

    if ($htmlPatched !== $html) {
        $relative = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);
        $backupFile = $backupDir . DIRECTORY_SEPARATOR . str_replace(DIRECTORY_SEPARATOR, '__', $relative);
        copy($file, $backupFile);
        writeFileText($file, $htmlPatched);
        $updated[] = $relative;
    }
}

if (empty($updated)) {
    echo "INFO: لم يتم تعديل أي ملف، ربما تم تطبيق الإصلاح مسبقاً.\n";
} else {
    echo "DONE: تم نقل QR داخل صفحة A4 وتثبيته داخل نطاق الورقة.\n";
    foreach ($updated as $file) {
        echo "- {$file}\n";
    }
    echo "Backup: {$backupDir}\n";
}

echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

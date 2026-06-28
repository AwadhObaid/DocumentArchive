<?php
/**
 * DocumentArchive - QR side position fix
 * Places the document QR code inside the A4 paper, to the left of the document number/date block.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if (!$root || !is_file($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: تأكد أنك تفك الضغط داخل جذر مشروع Laravel. ملف artisan غير موجود.\n");
    exit(1);
}

$documentsDir = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'documents';
if (!is_dir($documentsDir)) {
    fwrite(STDERR, "ERROR: مجلد resources/views/documents غير موجود.\n");
    exit(1);
}

$backupDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'qr-side-position-' . date('Ymd-His');
if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "ERROR: تعذر إنشاء مجلد النسخ الاحتياطي: {$backupDir}\n");
    exit(1);
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
    // Prefer our normalized QR block if it already exists.
    if (preg_match('/<div\b[^>]*class=["\'][^"\']*official-document-qr-inside-page[^"\']*["\'][^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $start = $m[0][1];
        $end = findMatchingDivEnd($html, $start);
        if ($end !== null) {
            return [$start, $end, substr($html, $start, $end - $start)];
        }
    }

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

    return [$divStart, $divEnd, substr($html, $divStart, $divEnd - $divStart)];
}

function extractQrImage(string $block): string
{
    if (preg_match('/<img\b[^>]*>/i', $block, $m)) {
        $img = $m[0];
        $img = preg_replace('/\sclass=["\'][^"\']*["\']/i', '', $img) ?? $img;
        $img = preg_replace('/\sstyle=["\'][^"\']*["\']/i', '', $img) ?? $img;
        $img = preg_replace('/<img\b/i', '<img class="official-document-qr-image"', $img, 1) ?? $img;
        return $img;
    }

    return '<img class="official-document-qr-image" src="{{ route(\'documents.qr\', $document) }}" alt="رمز QR للكتاب">';
}

function removeOldQrStyleBlocks(string $html): string
{
    $patterns = [
        '/\s*<style\s+id=["\']document-qr-print-position-fix-v10["\'][\s\S]*?<\/style>\s*/i',
        '/\s*<style\s+id=["\']document-qr-print-side-position-v11["\'][\s\S]*?<\/style>\s*/i',
    ];
    foreach ($patterns as $pattern) {
        $html = preg_replace($pattern, "\n", $html) ?? $html;
    }
    return $html;
}

function addSidePositionStyle(string $html): string
{
    $style = <<<'BLADE'

<style id="document-qr-print-side-position-v11">
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

    /* QR بجانب رقم الكتاب والتاريخ من جهة اليسار */
    .official-document-qr-inside-page {
        position: absolute !important;
        left: 52mm !important;
        top: 55mm !important;
        right: auto !important;
        bottom: auto !important;
        transform: none !important;
        z-index: 20 !important;
        width: 32mm !important;
        min-width: 32mm !important;
        max-width: 32mm !important;
        text-align: center !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        direction: rtl !important;
        color: #111827 !important;
        font-family: Cairo, Tahoma, Arial, sans-serif !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .official-document-qr-inside-page .official-document-qr-image,
    .official-document-qr-inside-page img,
    .official-document-qr-inside-page svg {
        display: block !important;
        width: 25mm !important;
        height: 25mm !important;
        max-width: 25mm !important;
        max-height: 25mm !important;
        margin: 0 auto 1.6mm auto !important;
        padding: 1.6mm !important;
        background: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 2.2mm !important;
        box-sizing: border-box !important;
    }

    .official-document-qr-caption {
        display: block !important;
        font-size: 8.5px !important;
        line-height: 1.35 !important;
        color: #374151 !important;
        white-space: nowrap !important;
    }

    @media print {
        body {
            background: #ffffff !important;
        }

        .official-document-qr-inside-page {
            left: 52mm !important;
            top: 55mm !important;
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
    if (
        stripos($content, 'qr.svg') !== false ||
        stripos($content, 'documents.qr') !== false ||
        stripos($content, 'official-document-qr') !== false ||
        stripos($content, 'qr-code') !== false ||
        stripos($content, '/qr') !== false
    ) {
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
    $original = $html;

    $qr = findQrBlock($html);
    if ($qr !== null) {
        [$qrStart, $qrEnd, $qrBlock] = $qr;
        $qrImg = extractQrImage($qrBlock);
        $standardQrBlock = "\n        <div class=\"official-document-qr-inside-page\">\n            {$qrImg}\n            <div class=\"official-document-qr-caption\">رمز الوصول الإلكتروني</div>\n        </div>\n";

        $htmlWithoutQr = substr($html, 0, $qrStart) . substr($html, $qrEnd);
        $container = findBestPageContainer($htmlWithoutQr);

        if ($container !== null) {
            [$pageStart, $pageEnd] = $container;
            $insertAt = $pageEnd - strlen('</div>');
            $html = substr($htmlWithoutQr, 0, $insertAt) . $standardQrBlock . substr($htmlWithoutQr, $insertAt);
        } else {
            $html = $htmlWithoutQr . $standardQrBlock;
        }
    }

    $html = removeOldQrStyleBlocks($html);
    $html = addSidePositionStyle($html);

    if ($html !== $original) {
        $relative = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);
        $backupFile = $backupDir . DIRECTORY_SEPARATOR . str_replace(DIRECTORY_SEPARATOR, '__', $relative);
        copy($file, $backupFile);
        writeFileText($file, $html);
        $updated[] = $relative;
    }
}

if (empty($updated)) {
    echo "INFO: لم يتم تعديل أي ملف، ربما تم تطبيق الإصلاح مسبقاً.\n";
} else {
    echo "DONE: تم وضع QR بجانب رقم الكتاب والتاريخ من جهة اليسار.\n";
    foreach ($updated as $file) {
        echo "- {$file}\n";
    }
    echo "Backup: {$backupDir}\n";
}

echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

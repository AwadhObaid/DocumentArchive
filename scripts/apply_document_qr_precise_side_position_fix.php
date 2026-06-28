<?php
/**
 * Precise QR side position fix for DocumentArchive print page.
 * Run from Laravel project root:
 *   php scripts/apply_document_qr_precise_side_position_fix.php
 */

function fail(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

$root = getcwd();
if (!is_file($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fail('يجب تشغيل السكربت من جذر مشروع Laravel حيث يوجد ملف artisan.');
}

$viewsDir = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
$publicDir = $root . DIRECTORY_SEPARATOR . 'public';
if (!is_dir($viewsDir)) {
    fail('مجلد resources/views غير موجود. تأكد أنك داخل جذر المشروع.');
}
if (!is_dir($publicDir)) {
    mkdir($publicDir, 0775, true);
}
if (!is_dir($publicDir . DIRECTORY_SEPARATOR . 'css')) {
    mkdir($publicDir . DIRECTORY_SEPARATOR . 'css', 0775, true);
}
if (!is_dir($publicDir . DIRECTORY_SEPARATOR . 'js')) {
    mkdir($publicDir . DIRECTORY_SEPARATOR . 'js', 0775, true);
}

$cssPath = $publicDir . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'document-qr-precise-side-position.css';
$jsPath = $publicDir . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'document-qr-precise-side-position.js';

$css = <<<'CSS'
/* DocumentArchive - precise QR position on document reference print page */
:root {
    /* يمكن تعديل هذه القيم بالملليمتر إذا احتجت ضبطاً أدق لاحقاً */
    --da-qr-top: 48mm;
    --da-qr-right: 103mm;
    --da-qr-size: 20mm;
    --da-qr-card: 24mm;
}

.document-print-paper-precise,
.da-print-paper-precise {
    position: relative !important;
    overflow: hidden !important;
}

.document-print-paper-precise .document-qr-precise-box,
.da-print-paper-precise .document-qr-precise-box {
    position: absolute !important;
    top: var(--da-qr-top) !important;
    right: var(--da-qr-right) !important;
    width: var(--da-qr-card) !important;
    min-width: var(--da-qr-card) !important;
    max-width: var(--da-qr-card) !important;
    height: auto !important;
    margin: 0 !important;
    padding: 1.5mm !important;
    transform: none !important;
    z-index: 20 !important;
    text-align: center !important;
    background: #ffffff !important;
    border: 1px solid #cfd6e3 !important;
    border-radius: 2mm !important;
    box-sizing: border-box !important;
    float: none !important;
    display: block !important;
}

.document-qr-precise-box img,
.document-qr-precise-box svg,
.document-qr-precise-box object,
.document-qr-precise-box iframe {
    display: block !important;
    width: var(--da-qr-size) !important;
    height: var(--da-qr-size) !important;
    max-width: var(--da-qr-size) !important;
    max-height: var(--da-qr-size) !important;
    margin: 0 auto !important;
    padding: 0 !important;
    border: 0 !important;
}

.document-qr-precise-box .document-qr-caption,
.document-qr-precise-box .qr-caption,
.document-qr-precise-box small,
.document-qr-precise-box p {
    display: block !important;
    margin: 1mm 0 0 0 !important;
    padding: 0 !important;
    font-size: 6.5pt !important;
    line-height: 1.1 !important;
    color: #667085 !important;
    white-space: nowrap !important;
    text-align: center !important;
}

/* يمنع أي موضع قديم أضافه تحديث سابق */
.document-qr-side,
.document-print-qr,
.qr-print-side,
.qr-code-print,
.qr-print-box,
.document-qr-card {
    margin: 0 !important;
}

@media screen {
    .document-print-paper-precise .document-qr-precise-box,
    .da-print-paper-precise .document-qr-precise-box {
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.12) !important;
    }
}

@media print {
    .document-print-paper-precise,
    .da-print-paper-precise {
        box-shadow: none !important;
        page-break-after: avoid !important;
    }

    .document-print-paper-precise .document-qr-precise-box,
    .da-print-paper-precise .document-qr-precise-box {
        box-shadow: none !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
CSS;

$js = <<<'JS'
(function () {
    'use strict';

    function findQrElement() {
        return document.querySelector(
            'img[src*="/qr.svg"], img[src*="qr.svg"], object[data*="/qr.svg"], object[data*="qr.svg"], iframe[src*="/qr.svg"], iframe[src*="qr.svg"], svg[data-document-qr="1"]'
        );
    }

    function findPaper(qr) {
        if (!qr) return null;
        var selectors = [
            '.a4-page', '.paper', '.print-page', '.print-paper', '.document-print-page',
            '.reference-print-page', '.document-reference-page', '.page', 'main', 'section'
        ];
        for (var i = 0; i < selectors.length; i++) {
            var node = qr.closest(selectors[i]);
            if (node && node !== document.body && node.getBoundingClientRect().width > 400) {
                return node;
            }
        }
        var candidates = Array.prototype.slice.call(document.querySelectorAll('div,main,section'));
        candidates.sort(function (a, b) {
            return (b.getBoundingClientRect().width * b.getBoundingClientRect().height) -
                   (a.getBoundingClientRect().width * a.getBoundingClientRect().height);
        });
        return candidates.find(function (el) {
            var r = el.getBoundingClientRect();
            return r.width > 500 && r.height > 700 && el.contains(qr);
        }) || document.body;
    }

    function applyPreciseQrPosition() {
        var qr = findQrElement();
        if (!qr) return;

        var box = qr.closest('.document-qr-precise-box');
        if (!box) {
            box = qr.parentElement;
            // إذا كان الأب صغيراً جداً أو الأب هو رابط فقط، استخدم الأب الأعلى
            if (box && box.parentElement && box.getBoundingClientRect().width < 40) {
                box = box.parentElement;
            }
        }
        if (!box) return;

        var paper = findPaper(qr);
        if (paper) {
            paper.classList.add('document-print-paper-precise', 'da-print-paper-precise');
        }

        box.classList.add('document-qr-precise-box');

        // أزل أي خصائص inline قديمة قد تكون سببت خروج الرمز من الصفحة أو تداخله مع النص
        var resetProps = ['left', 'bottom', 'transform', 'float', 'marginLeft', 'marginRight'];
        resetProps.forEach(function (prop) { box.style[prop] = ''; });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyPreciseQrPosition);
    } else {
        applyPreciseQrPosition();
    }
    window.addEventListener('load', applyPreciseQrPosition);
    window.addEventListener('beforeprint', applyPreciseQrPosition);
})();
JS;

file_put_contents($cssPath, $css);
file_put_contents($jsPath, $js);

$bladeFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || substr($file->getFilename(), -10) !== '.blade.php') {
        continue;
    }
    $path = $file->getPathname();
    $content = file_get_contents($path);
    $normalized = str_replace('\\', '/', $path);
    $isLikelyPrint = stripos($normalized, '/documents/') !== false && (
        stripos($content, 'qr.svg') !== false ||
        stripos($content, 'رمز الوصول الإلكتروني') !== false ||
        stripos($content, 'رقم الكتاب') !== false && stripos($content, 'تاريخ الكتاب') !== false && stripos($content, 'print') !== false
    );
    if ($isLikelyPrint) {
        $bladeFiles[] = $path;
    }
}

if (!$bladeFiles) {
    fail('لم أجد ملف Blade الخاص بطباعة رقم الكتاب أو QR. تأكد من وجود صفحة الطباعة.');
}

$cssLink = "<link rel=\"stylesheet\" href=\"{{ asset('css/document-qr-precise-side-position.css') }}\">";
$jsLink = "<script src=\"{{ asset('js/document-qr-precise-side-position.js') }}\" defer></script>";
$changed = [];
foreach ($bladeFiles as $path) {
    $content = file_get_contents($path);
    $original = $content;

    if (strpos($content, 'document-qr-precise-side-position.css') === false) {
        if (stripos($content, '</head>') !== false) {
            $content = preg_replace('/<\/head>/i', "    {$cssLink}\n</head>", $content, 1);
        } else {
            $content = $cssLink . "\n" . $content;
        }
    }

    if (strpos($content, 'document-qr-precise-side-position.js') === false) {
        if (stripos($content, '</body>') !== false) {
            $content = preg_replace('/<\/body>/i', "    {$jsLink}\n</body>", $content, 1);
        } else {
            $content .= "\n" . $jsLink . "\n";
        }
    }

    if ($content !== $original) {
        $backup = $path . '.before-qr-precise-side.bak';
        if (!is_file($backup)) {
            file_put_contents($backup, $original);
        }
        file_put_contents($path, $content);
        $changed[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    }
}

echo "DONE: تم تركيب ضبط موضع QR بجانب رقم الكتاب والتاريخ بدقة.\n";
echo "CSS: public/css/document-qr-precise-side-position.css\n";
echo "JS : public/js/document-qr-precise-side-position.js\n";
if ($changed) {
    echo "Updated Blade files:\n- " . implode("\n- ", $changed) . "\n";
} else {
    echo "Blade links already exist.\n";
}
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

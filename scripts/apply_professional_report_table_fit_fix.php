<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$cssSource = $root . '/public/css/professional-report-table-fit-fix.css';
$cssTargetDir = dirname($root) . '/public/css';
$cssTarget = $cssTargetDir . '/professional-report-table-fit-fix.css';

function fail(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function normalizePath(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

if (!is_file($cssSource)) {
    fail('ملف CSS الخاص بالإصلاح غير موجود داخل حزمة التحديث.');
}

if (!is_dir($cssTargetDir) && !mkdir($cssTargetDir, 0775, true) && !is_dir($cssTargetDir)) {
    fail('تعذر إنشاء مجلد public/css.');
}

if (!copy($cssSource, $cssTarget)) {
    fail('تعذر نسخ ملف CSS إلى public/css.');
}

$viewsRoot = dirname($root) . '/resources/views';
if (!is_dir($viewsRoot)) {
    fail('مجلد resources/views غير موجود. تأكد أنك تفك الضغط داخل جذر مشروع Laravel.');
}

$candidates = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsRoot, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . '_backup')) {
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }

    $looksLikePrintReport = str_contains($content, 'تقرير الكتب والمرفقات')
        || str_contains($content, 'reports/print')
        || str_contains($content, "route('reports.print")
        || str_contains($content, 'تفاصيل الكتب');

    if ($looksLikePrintReport) {
        $candidates[] = $path;
    }
}

$preferred = dirname($root) . '/resources/views/reports/print.blade.php';
$targetView = null;
if (is_file($preferred)) {
    $targetView = $preferred;
} elseif (!empty($candidates)) {
    usort($candidates, static fn ($a, $b) => strlen($a) <=> strlen($b));
    $targetView = $candidates[0];
}

if (!$targetView) {
    fail('لم أجد ملف تقرير الطباعة. المتوقع: resources/views/reports/print.blade.php');
}

$content = file_get_contents($targetView);
if ($content === false) {
    fail('تعذر قراءة ملف تقرير الطباعة.');
}

$marker = 'professional-report-table-fit-fix.css';
$link = "    <link rel=\"stylesheet\" href=\"{{ asset('css/professional-report-table-fit-fix.css') }}?v=20260628\">\n";

if (!str_contains($content, $marker)) {
    if (str_contains($content, '</head>')) {
        $content = str_replace('</head>', $link . '</head>', $content);
    } elseif (str_contains($content, '@push(')) {
        $content .= "\n@push('styles')\n" . trim($link) . "\n@endpush\n";
    } else {
        $content = $link . $content;
    }
}

/* Add a predictable class to the first body/container when possible so CSS scope is reliable. */
if (preg_match('/<body([^>]*)>/i', $content, $m) && !str_contains($m[0], 'professional-report-page')) {
    $attrs = $m[1];
    if (preg_match('/class=[\"\']([^\"\']*)[\"\']/i', $attrs, $classMatch)) {
        $newClass = trim($classMatch[1] . ' professional-report-page');
        $newAttrs = preg_replace('/class=[\"\']([^\"\']*)[\"\']/i', 'class="' . $newClass . '"', $attrs, 1);
        $content = str_replace($m[0], '<body' . $newAttrs . '>', $content);
    } else {
        $content = str_replace($m[0], '<body' . $attrs . ' class="professional-report-page">', $content);
    }
} elseif (!str_contains($content, 'professional-report-page')) {
    $content = "<div class=\"professional-report-page\">\n" . $content . "\n</div>\n";
}

if (file_put_contents($targetView, $content) === false) {
    fail('تعذر تحديث ملف تقرير الطباعة.');
}

$relative = str_replace(dirname($root) . DIRECTORY_SEPARATOR, '', normalizePath($targetView));
echo "OK: تم إصلاح عرض جدول تفاصيل الكتب داخل التقرير الرسمي.\n";
echo "View: {$relative}\n";
echo "CSS: public/css/professional-report-table-fit-fix.css\n";

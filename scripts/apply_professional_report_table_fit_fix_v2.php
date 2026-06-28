<?php

declare(strict_types=1);

function fail(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function normalizePath(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function candidateProjectRoots(): array
{
    $roots = [];
    $add = static function (?string $path) use (&$roots): void {
        if (!$path) {
            return;
        }
        $real = realpath($path);
        if ($real && !in_array($real, $roots, true)) {
            $roots[] = $real;
        }
    };

    $add(getcwd());
    $add(dirname(__DIR__));
    $add(dirname(dirname(__DIR__)));

    return $roots;
}

function findProjectRoot(): string
{
    foreach (candidateProjectRoots() as $root) {
        if (is_file($root . DIRECTORY_SEPARATOR . 'artisan') && is_dir($root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views')) {
            return $root;
        }
    }

    fail('لم أجد جذر مشروع Laravel. شغّل السكربت من داخل مجلد المشروع الذي يحتوي ملف artisan.');
}

function addClassToTag(string $tag, string $class): string
{
    if (preg_match('/\sclass=("|\')([^"\']*)\1/i', $tag, $m)) {
        $classes = preg_split('/\s+/', trim($m[2])) ?: [];
        if (!in_array($class, $classes, true)) {
            $classes[] = $class;
        }
        $newClass = 'class=' . $m[1] . trim(implode(' ', array_filter($classes))) . $m[1];
        return str_replace($m[0], ' ' . $newClass, $tag);
    }

    return rtrim($tag, '>') . ' class="' . $class . '">';
}

$projectRoot = findProjectRoot();
$packageRoot = dirname(__DIR__);

$cssSource = $packageRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'professional-report-table-fit-fix.css';
$cssTargetDir = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css';
$cssTarget = $cssTargetDir . DIRECTORY_SEPARATOR . 'professional-report-table-fit-fix.css';

if (!is_file($cssSource)) {
    fail('ملف CSS الخاص بالإصلاح غير موجود داخل حزمة التحديث.');
}

if (!is_dir($cssTargetDir) && !mkdir($cssTargetDir, 0775, true) && !is_dir($cssTargetDir)) {
    fail('تعذر إنشاء مجلد public/css داخل المشروع.');
}

if (realpath($cssSource) !== realpath($cssTarget)) {
    if (!copy($cssSource, $cssTarget)) {
        fail('تعذر نسخ ملف CSS إلى public/css.');
    }
}

$viewsRoot = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
$preferred = $viewsRoot . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'print.blade.php';
$targetView = null;

if (is_file($preferred)) {
    $targetView = $preferred;
} else {
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
        $content = file_get_contents($path) ?: '';
        if (str_contains($content, 'تقرير الكتب والمرفقات') && str_contains($content, 'تفاصيل الكتب')) {
            $candidates[] = $path;
        }
    }
    if ($candidates) {
        usort($candidates, static fn (string $a, string $b): int => strlen($a) <=> strlen($b));
        $targetView = $candidates[0];
    }
}

if (!$targetView) {
    fail('لم أجد ملف تقرير الطباعة. المتوقع غالباً: resources/views/reports/print.blade.php');
}

$content = file_get_contents($targetView);
if ($content === false) {
    fail('تعذر قراءة ملف تقرير الطباعة.');
}

$linkMarker = 'professional-report-table-fit-fix.css';
$link = "    <link rel=\"stylesheet\" href=\"{{ asset('css/professional-report-table-fit-fix.css') }}?v=20260628v2\">\n";

if (!str_contains($content, $linkMarker)) {
    if (stripos($content, '</head>') !== false) {
        $content = preg_replace('/<\/head>/i', $link . '</head>', $content, 1) ?? $content;
    } else {
        $content = $link . $content;
    }
}

if (preg_match('/<body([^>]*)>/i', $content, $m) && !str_contains($m[0], 'professional-report-page')) {
    $body = addClassToTag($m[0], 'professional-report-page');
    $content = preg_replace('/<body([^>]*)>/i', $body, $content, 1) ?? $content;
} elseif (!str_contains($content, 'professional-report-page')) {
    $content = "<div class=\"professional-report-page\">\n" . $content . "\n</div>\n";
}

/* Add a generic report table class to every table tag. */
$content = preg_replace_callback('/<table\b[^>]*>/i', static function (array $m): string {
    return addClassToTag($m[0], 'professional-report-table');
}, $content) ?? $content;

/* Add a specific details-table class to the first table after the 'تفاصيل الكتب' heading. */
$detailsPos = mb_strpos($content, 'تفاصيل الكتب');
if ($detailsPos !== false) {
    $before = mb_substr($content, 0, $detailsPos);
    $after = mb_substr($content, $detailsPos);
    $after = preg_replace_callback('/<table\b[^>]*>/i', static function (array $m): string {
        return addClassToTag($m[0], 'professional-report-details-table');
    }, $after, 1) ?? $after;
    $content = $before . $after;
}

if (file_put_contents($targetView, $content) === false) {
    fail('تعذر تحديث ملف تقرير الطباعة.');
}

$relative = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', normalizePath($targetView));
echo "OK: تم إصلاح جدول تفاصيل الكتب داخل التقرير الرسمي.\n";
echo "View: {$relative}\n";
echo "CSS: public/css/professional-report-table-fit-fix.css\n";

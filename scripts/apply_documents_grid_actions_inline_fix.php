<?php

$root = dirname(__DIR__);

function info_line(string $message): void
{
    echo $message . PHP_EOL;
}

function ensureDirectory(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function copyFileOrFail(string $from, string $to): void
{
    ensureDirectory(dirname($to));
    if (!copy($from, $to)) {
        throw new RuntimeException("تعذر نسخ الملف: {$from} إلى {$to}");
    }
}

function injectBefore(string $file, string $needle, string $block, string $marker): bool
{
    if (!is_file($file)) {
        return false;
    }

    $content = file_get_contents($file);
    if ($content === false) {
        return false;
    }

    if (str_contains($content, $marker)) {
        return true;
    }

    $pos = stripos($content, $needle);
    if ($pos === false) {
        return false;
    }

    $updated = substr($content, 0, $pos) . $block . PHP_EOL . substr($content, $pos);
    file_put_contents($file, $updated);
    return true;
}

$cssSource = __DIR__ . '/../public/css/documents-grid-actions-fix.css';
$jsSource = __DIR__ . '/../public/js/documents-grid-actions-fix.js';

$cssTarget = $root . '/public/css/documents-grid-actions-fix.css';
$jsTarget = $root . '/public/js/documents-grid-actions-fix.js';

copyFileOrFail($cssSource, $cssTarget);
copyFileOrFail($jsSource, $jsTarget);

$cssBlock = <<<'BLADE'
    {{-- DocumentArchive: Documents grid actions inline fix --}}
    <link rel="stylesheet" href="{{ asset('css/documents-grid-actions-fix.css') }}?v={{ file_exists(public_path('css/documents-grid-actions-fix.css')) ? filemtime(public_path('css/documents-grid-actions-fix.css')) : time() }}">
BLADE;

$jsBlock = <<<'BLADE'
    {{-- DocumentArchive: Documents grid actions inline fix --}}
    <script src="{{ asset('js/documents-grid-actions-fix.js') }}?v={{ file_exists(public_path('js/documents-grid-actions-fix.js')) ? filemtime(public_path('js/documents-grid-actions-fix.js')) : time() }}" defer></script>
BLADE;

$layoutsDir = $root . '/resources/views/layouts';
$layoutFiles = [];
if (is_dir($layoutsDir)) {
    foreach (glob($layoutsDir . '/*.blade.php') ?: [] as $file) {
        $layoutFiles[] = $file;
    }
}

$cssInjected = false;
$jsInjected = false;

foreach ($layoutFiles as $layoutFile) {
    $content = file_get_contents($layoutFile) ?: '';

    // نحقن في ملفات layout التي تحتوي على head/body فقط.
    if (stripos($content, '</head>') !== false) {
        $cssInjected = injectBefore($layoutFile, '</head>', $cssBlock, 'documents-grid-actions-fix.css') || $cssInjected;
    }

    if (stripos($content, '</body>') !== false) {
        $jsInjected = injectBefore($layoutFile, '</body>', $jsBlock, 'documents-grid-actions-fix.js') || $jsInjected;
    }
}

if (!$cssInjected || !$jsInjected) {
    throw new RuntimeException('تم نسخ ملفات CSS/JS، لكن لم يتم العثور على layout مناسب يحتوي على </head> و </body>. أضف الروابط يدوياً في layout الرئيسي.');
}

info_line('تم تركيب إصلاح أزرار إجراءات جدول الكتب بنجاح.');
info_line('الملفات المضافة:');
info_line('- public/css/documents-grid-actions-fix.css');
info_line('- public/js/documents-grid-actions-fix.js');

<?php

declare(strict_types=1);

$projectRoot = $argv[1] ?? dirname(__DIR__);
$projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
$layoutPath = $projectRoot . '/resources/views/layouts/app.blade.php';

if (!is_file($layoutPath)) {
    fwrite(STDERR, "[FAIL] Layout not found: {$layoutPath}\n");
    exit(1);
}

$content = file_get_contents($layoutPath);
if ($content === false) {
    fwrite(STDERR, "[FAIL] Unable to read layout: {$layoutPath}\n");
    exit(1);
}

$cssBlock = <<<'BLADE'
    {{-- global-operation-loading-v90-css:start --}}
    <link rel="stylesheet" href="{{ asset('css/global-operation-loading-v90.css') }}?v={{ filemtime(public_path('css/global-operation-loading-v90.css')) }}">
    {{-- global-operation-loading-v90-css:end --}}
BLADE;

$uiBlock = <<<'BLADE'
{{-- global-operation-loading-v90-ui:start --}}
<div class="da-page-progress" aria-hidden="true">
    <span class="da-page-progress__bar" data-da-page-progress></span>
</div>
<div class="da-global-loading" id="daGlobalLoading" aria-hidden="true" role="status" aria-live="polite" aria-atomic="true">
    <div class="da-global-loading__backdrop"></div>
    <div class="da-global-loading__panel">
        <div class="da-global-loading__spinner" aria-hidden="true"></div>
        <div class="da-global-loading__message" data-da-loading-message>جارٍ تنفيذ العملية...</div>
        <div class="da-global-loading__hint">يرجى الانتظار وعدم إغلاق الصفحة</div>
        <div class="da-global-loading__track" aria-hidden="true">
            <span class="da-global-loading__value" data-da-loading-progress></span>
        </div>
        <div class="da-global-loading__percent" data-da-loading-percent hidden>0%</div>
    </div>
</div>
{{-- global-operation-loading-v90-ui:end --}}
BLADE;

$jsBlock = <<<'BLADE'
    {{-- global-operation-loading-v90-js:start --}}
    <script src="{{ asset('js/global-operation-loading-v90.js') }}?v={{ filemtime(public_path('js/global-operation-loading-v90.js')) }}" defer></script>
    {{-- global-operation-loading-v90-js:end --}}
BLADE;

function addBlock(string $content, string $startMarker, string $endMarker, string $needle, string $block, string $label): string
{
    $hasStart = str_contains($content, $startMarker);
    $hasEnd = str_contains($content, $endMarker);

    if ($hasStart xor $hasEnd) {
        throw new RuntimeException("Partial {$label} marker found. Refusing unsafe patch.");
    }

    if ($hasStart && $hasEnd) {
        echo "[INFO] {$label} already installed.\n";
        return $content;
    }

    $position = strpos($content, $needle);
    if ($position === false) {
        throw new RuntimeException("Insertion point not found for {$label}: {$needle}");
    }

    echo "[ OK ] Added {$label}.\n";
    return substr($content, 0, $position) . $block . PHP_EOL . substr($content, $position);
}

try {
    $content = addBlock(
        $content,
        'global-operation-loading-v90-css:start',
        'global-operation-loading-v90-css:end',
        '</head>',
        $cssBlock,
        'loading stylesheet reference'
    );

    $content = addBlock(
        $content,
        'global-operation-loading-v90-ui:start',
        'global-operation-loading-v90-ui:end',
        '<div class="app-shell">',
        $uiBlock,
        'loading interface'
    );

    $content = addBlock(
        $content,
        'global-operation-loading-v90-js:start',
        'global-operation-loading-v90-js:end',
        '</body>',
        $jsBlock,
        'loading script reference'
    );

    if (file_put_contents($layoutPath, $content, LOCK_EX) === false) {
        throw new RuntimeException("Unable to write layout: {$layoutPath}");
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Global Operation Loading V90 layout patch completed.\n";

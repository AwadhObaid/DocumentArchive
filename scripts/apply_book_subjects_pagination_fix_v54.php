<?php

$root = dirname(__DIR__);
$layoutPath = $root . '/resources/views/layouts/app.blade.php';
$cssPath = $root . '/public/css/book-subjects-pagination-fix-v54.css';

if (!file_exists($layoutPath)) {
    echo "[FAIL] app.blade.php not found: {$layoutPath}\n";
    exit(1);
}

if (!file_exists($cssPath)) {
    echo "[FAIL] CSS file not found: {$cssPath}\n";
    exit(1);
}

$content = file_get_contents($layoutPath);

$marker = 'book-subjects-pagination-fix-v54';
$pattern = '/\s*\{\{--\s*' . preg_quote($marker, '/') . ':start\s*--\}\}.*?\{\{--\s*' . preg_quote($marker, '/') . ':end\s*--\}\}/s';
$content = preg_replace($pattern, '', $content);

$block = <<<'BLADE'

        {{-- book-subjects-pagination-fix-v54:start --}}
        <link rel="stylesheet" href="{{ asset('css/book-subjects-pagination-fix-v54.css') }}?v=54">
        {{-- book-subjects-pagination-fix-v54:end --}}
BLADE;

if (!str_contains($content, '</head>')) {
    echo "[FAIL] </head> not found in app.blade.php\n";
    exit(1);
}

$content = str_replace('</head>', $block . "\n</head>", $content);
file_put_contents($layoutPath, $content);

echo "[OK] Book subjects pagination CSS include added once.\n";

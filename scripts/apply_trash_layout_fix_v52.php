<?php

$basePath = dirname(__DIR__);
$layoutPath = $basePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
$cssPath = $basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'trash-layout-fix-v52.css';
$trashViewPath = $basePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'trash.blade.php';

function v52_fail(string $message): void
{
    fwrite(STDERR, "[FAIL] {$message}" . PHP_EOL);
    exit(1);
}

function v52_ok(string $message): void
{
    echo "[OK] {$message}" . PHP_EOL;
}

if (! is_file($layoutPath)) {
    v52_fail("Layout file not found: {$layoutPath}");
}

if (! is_file($cssPath)) {
    v52_fail("CSS file not found: {$cssPath}");
}

if (! is_file($trashViewPath)) {
    v52_fail("Trash view file not found: {$trashViewPath}");
}

$layout = file_get_contents($layoutPath);
if ($layout === false) {
    v52_fail('Unable to read app.blade.php');
}

$includeBlock = <<<'BLADE'
        {{-- trash-layout-fix-v52:start --}}
        <link rel="stylesheet" href="{{ asset('css/trash-layout-fix-v52.css') }}?v={{ filemtime(public_path('css/trash-layout-fix-v52.css')) }}">
        {{-- trash-layout-fix-v52:end --}}
BLADE;

// Remove any older V52 include block if the script is run more than once.
$startMarker = '        {{-- trash-layout-fix-v52:start --}}';
$endMarker = '        {{-- trash-layout-fix-v52:end --}}';

while (($start = strpos($layout, $startMarker)) !== false) {
    $end = strpos($layout, $endMarker, $start);
    if ($end === false) {
        break;
    }
    $end += strlen($endMarker);
    if (isset($layout[$end]) && $layout[$end] === "\r") {
        $end++;
    }
    if (isset($layout[$end]) && $layout[$end] === "\n") {
        $end++;
    }
    $layout = substr($layout, 0, $start) . substr($layout, $end);
}

// Remove accidental one-line includes without markers.
$lines = preg_split('/\R/', $layout);
$lines = array_values(array_filter($lines, static function ($line) {
    return strpos($line, 'css/trash-layout-fix-v52.css') === false;
}));
$layout = implode(PHP_EOL, $lines);

if (strpos($layout, '</head>') === false) {
    v52_fail('Cannot find </head> in app.blade.php');
}

$layout = str_replace('</head>', $includeBlock . PHP_EOL . '    </head>', $layout);

if (file_put_contents($layoutPath, $layout) === false) {
    v52_fail('Unable to write app.blade.php');
}

v52_ok('app.blade.php updated with trash layout CSS V52 include.');

$trashView = file_get_contents($trashViewPath);
if ($trashView === false) {
    v52_fail('Unable to read trash.blade.php');
}

foreach (['trash-page', 'trash-overview-grid', 'trash-section-card', 'trash-table-wrap'] as $needle) {
    if (strpos($trashView, $needle) === false) {
        v52_fail("trash.blade.php is missing {$needle}. Make sure the V52 view file was copied.");
    }
}

v52_ok('trash.blade.php contains the V52 scoped layout classes.');

require __DIR__ . DIRECTORY_SEPARATOR . 'check_trash_layout_fix_v52.php';

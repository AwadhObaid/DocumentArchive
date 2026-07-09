<?php

$root = dirname(__DIR__);
$layout = $root . '/resources/views/layouts/app.blade.php';
$css = $root . '/public/css/visual-theme-polish-v45.css';

if (! is_file($css)) {
    fwrite(STDERR, "Missing CSS file: {$css}" . PHP_EOL);
    exit(1);
}

if (! is_file($layout)) {
    fwrite(STDERR, "Missing layout file: {$layout}" . PHP_EOL);
    exit(1);
}

$content = file_get_contents($layout);
$markerStart = "{{-- visual-theme-polish-v45:start --}}";
$markerEnd = "{{-- visual-theme-polish-v45:end --}}";
$linkBlock = <<<BLADE
{$markerStart}
<link rel="stylesheet" href="{{ asset('css/visual-theme-polish-v45.css') }}?v={{ filemtime(public_path('css/visual-theme-polish-v45.css')) }}">
{$markerEnd}
BLADE;

if (strpos($content, 'visual-theme-polish-v45.css') === false) {
    $needle = "@yield('content')";
    if (strpos($content, $needle) === false) {
        fwrite(STDERR, "Could not find @yield('content') in layout." . PHP_EOL);
        exit(1);
    }

    $content = str_replace($needle, $needle . PHP_EOL . $linkBlock, $content);
    file_put_contents($layout, $content);
    echo "[OK] visual-theme-polish-v45.css link inserted after @yield('content')." . PHP_EOL;
} else {
    echo "[OK] visual-theme-polish-v45.css link already exists." . PHP_EOL;
}

echo PHP_EOL . "Visual theme polish V45 applied." . PHP_EOL;

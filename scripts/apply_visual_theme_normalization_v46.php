<?php

$root = dirname(__DIR__);
$appBlade = $root . '/resources/views/layouts/app.blade.php';
$cssFile = $root . '/public/css/visual-theme-normalization-v46.css';

if (! is_file($cssFile)) {
    fwrite(STDERR, "Missing CSS file: {$cssFile}" . PHP_EOL);
    exit(1);
}

if (! is_file($appBlade)) {
    fwrite(STDERR, "Missing layout file: {$appBlade}" . PHP_EOL);
    exit(1);
}

$content = file_get_contents($appBlade);
$original = $content;

// Remove old V45 include from the body/content area. V46 must be loaded once from <head> after all module CSS.
$content = preg_replace(
    '/\s*\{\{--\s*visual-theme-polish-v45:start\s*--\}\}\s*\R\s*<link[^\R]*visual-theme-polish-v45\.css[^\R]*>\s*\R\s*\{\{--\s*visual-theme-polish-v45:end\s*--\}\}\s*/u',
    PHP_EOL,
    $content
) ?? $content;

// Remove any previous V46 include to avoid duplicates.
$content = preg_replace(
    '/\s*\{\{--\s*visual-theme-normalization-v46:start\s*--\}\}\s*\R\s*<link[^\R]*visual-theme-normalization-v46\.css[^\R]*>\s*\R\s*\{\{--\s*visual-theme-normalization-v46:end\s*--\}\}\s*/u',
    PHP_EOL,
    $content
) ?? $content;

// Fix accidental duplicate permission wrapper in the sidebar if it exists.
$content = str_replace(
    "            @if(auth()->user()?->hasPermission('document_types.manage'))" . PHP_EOL . "            @if(auth()->user()?->hasPermission('document_types.manage'))" . PHP_EOL,
    "            @if(auth()->user()?->hasPermission('document_types.manage'))" . PHP_EOL,
    $content
);

// Fix newline inside the default avatar fallback.
$content = preg_replace(
    "/auth\(\)->user\(\)\?->name\s*\?\?\s*'م\s*'/u",
    "auth()->user()?->name ?? 'م'",
    $content
) ?? $content;

$include = <<<BLADE
    {{-- visual-theme-normalization-v46:start --}}
    <link rel="stylesheet" href="{{ asset('css/visual-theme-normalization-v46.css') }}?v={{ filemtime(public_path('css/visual-theme-normalization-v46.css')) }}">
    {{-- visual-theme-normalization-v46:end --}}
BLADE;

if (! str_contains($content, '</head>')) {
    fwrite(STDERR, "Could not find </head> in layout." . PHP_EOL);
    exit(1);
}

$content = preg_replace('/\R?\s*<\/head>/u', PHP_EOL . $include . "</head>", $content, 1) ?? $content;

if ($content !== $original) {
    file_put_contents($appBlade, $content);
    echo "[OK] app.blade.php updated for V46 theme normalization." . PHP_EOL;
} else {
    echo "[OK] app.blade.php already normalized for V46." . PHP_EOL;
}

require __DIR__ . '/check_visual_theme_normalization_v46.php';

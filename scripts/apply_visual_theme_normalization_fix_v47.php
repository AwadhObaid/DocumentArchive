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

// V47: remove all old V45/V46 visual-theme includes safely, including duplicates.
// The previous V46 apply script used [^\R] inside a regex character class, which may fail on some PHP builds.
$patterns = [
    '/^[ \t]*\{\{--[ \t]*visual-theme-polish-v45:start[ \t]*--\}\}[ \t]*\R[ \t]*<link\b[^\r\n]*visual-theme-polish-v45\.css[^\r\n]*>[ \t]*\R[ \t]*\{\{--[ \t]*visual-theme-polish-v45:end[ \t]*--\}\}[ \t]*\R?/mu',
    '/^[ \t]*\{\{--[ \t]*visual-theme-normalization-v46:start[ \t]*--\}\}[ \t]*\R[ \t]*<link\b[^\r\n]*visual-theme-normalization-v46\.css[^\r\n]*>[ \t]*\R[ \t]*\{\{--[ \t]*visual-theme-normalization-v46:end[ \t]*--\}\}[ \t]*\R?/mu',
    '/^[ \t]*<link\b[^\r\n]*visual-theme-polish-v45\.css[^\r\n]*>[ \t]*\R?/mu',
    '/^[ \t]*<link\b[^\r\n]*visual-theme-normalization-v46\.css[^\r\n]*>[ \t]*\R?/mu',
    '/^[ \t]*\{\{--[ \t]*visual-theme-polish-v45:(?:start|end)[ \t]*--\}\}[ \t]*\R?/mu',
    '/^[ \t]*\{\{--[ \t]*visual-theme-normalization-v46:(?:start|end)[ \t]*--\}\}[ \t]*\R?/mu',
];

foreach ($patterns as $pattern) {
    $updated = preg_replace($pattern, '', $content);
    if ($updated !== null) {
        $content = $updated;
    }
}

// Fix accidental duplicate permission wrapper in the sidebar if it exists.
$content = str_replace(
    "            @if(auth()->user()?->hasPermission('document_types.manage'))" . PHP_EOL . "            @if(auth()->user()?->hasPermission('document_types.manage'))" . PHP_EOL,
    "            @if(auth()->user()?->hasPermission('document_types.manage'))" . PHP_EOL,
    $content
);

// Fix newline inside the default avatar fallback if it exists.
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

$content = preg_replace('/\R?[ \t]*<\/head>/u', PHP_EOL . $include . PHP_EOL . '</head>', $content, 1) ?? $content;

if ($content !== $original) {
    file_put_contents($appBlade, $content);
    echo "[OK] app.blade.php cleaned and updated: V46 CSS include is now loaded once." . PHP_EOL;
} else {
    echo "[OK] app.blade.php already clean." . PHP_EOL;
}

require __DIR__ . '/check_visual_theme_normalization_fix_v47.php';

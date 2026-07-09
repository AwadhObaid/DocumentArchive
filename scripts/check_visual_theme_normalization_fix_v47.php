<?php

$root = dirname(__DIR__);
$checks = [];

function v47_check(bool $condition, string $message, array &$checks): void
{
    $checks[] = ['ok' => $condition, 'message' => $message];
}

function v47_file_contains(string $path, array $needles, array &$checks): void
{
    v47_check(is_file($path), "File exists: {$path}", $checks);
    if (! is_file($path)) {
        return;
    }

    $content = file_get_contents($path);
    foreach ($needles as $needle) {
        v47_check(str_contains($content, $needle), basename($path) . " contains: {$needle}", $checks);
    }
}

$appBlade = $root . '/resources/views/layouts/app.blade.php';
$cssFile = $root . '/public/css/visual-theme-normalization-v46.css';

v47_file_contains($cssFile, [
    'DocumentArchive Visual Theme Normalization V46',
    'html[data-theme="light"]',
    'html[data-theme="dark"]',
    '.sidebar',
    '.side-nav a.active',
    '.documents-page',
    '.doc-summary-card',
    '.advanced-filter-card',
    '.da-dashboard',
    '.internal-chat-panel',
], $checks);

v47_file_contains($appBlade, [
    'visual-theme-normalization-v46:start',
    'css/visual-theme-normalization-v46.css',
    "auth()->user()?->name ?? 'م'",
], $checks);

if (is_file($appBlade)) {
    $content = file_get_contents($appBlade);
    v47_check(! str_contains($content, 'visual-theme-polish-v45.css'), 'Old V45 CSS include removed from layout', $checks);
    v47_check(substr_count($content, 'visual-theme-normalization-v46:start') === 1, 'V46 start marker appears exactly once', $checks);
    $linkCount = preg_match_all('/<link\b[^\r\n]*visual-theme-normalization-v46\.css[^\r\n]*>/u', $content);
    v47_check($linkCount === 1, 'V46 CSS link appears exactly once', $checks);
    v47_check(! str_contains($content, "document_types.manage'))" . PHP_EOL . "            @if(auth()->user()?->hasPermission('document_types.manage')"), 'No duplicated document_types sidebar permission wrapper', $checks);
    v47_check(preg_match_all('/@if\b/u', $content) === preg_match_all('/@endif\b/u', $content), 'Blade @if/@endif counts are balanced in layout', $checks);
}

$failed = array_filter($checks, fn ($check) => ! $check['ok']);

foreach ($checks as $check) {
    echo ($check['ok'] ? '[OK] ' : '[FAIL] ') . $check['message'] . PHP_EOL;
}

if ($failed) {
    echo PHP_EOL . 'Visual theme normalization fix V47 check failed.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Visual theme normalization fix V47 check passed.' . PHP_EOL;

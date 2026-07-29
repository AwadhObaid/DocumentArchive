<?php

declare(strict_types=1);

$projectRoot = $argv[1] ?? dirname(__DIR__);
$projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');

$paths = [
    'layout' => $projectRoot . '/resources/views/layouts/app.blade.php',
    'css' => $projectRoot . '/public/css/global-operation-loading-v90.css',
    'js' => $projectRoot . '/public/js/global-operation-loading-v90.js',
];

$failures = 0;

function result(bool $condition, string $label): void
{
    global $failures;
    if ($condition) {
        echo "[ OK ] {$label}\n";
        return;
    }
    $failures++;
    echo "[FAIL] {$label}\n";
}

function contains(string $content, string $needle): bool
{
    return str_contains($content, $needle);
}

function exactlyOnce(string $content, string $needle): bool
{
    return substr_count($content, $needle) === 1;
}

echo "\nDocumentArchive Global Operation Loading V90 verification\n";
echo "=======================================================\n";

foreach ($paths as $label => $path) {
    result(is_file($path), ucfirst($label) . " file exists");
}

$layout = is_file($paths['layout']) ? (string) file_get_contents($paths['layout']) : '';
$css = is_file($paths['css']) ? (string) file_get_contents($paths['css']) : '';
$js = is_file($paths['js']) ? (string) file_get_contents($paths['js']) : '';

result(exactlyOnce($layout, 'global-operation-loading-v90-css:start'), 'CSS marker exists exactly once');
result(exactlyOnce($layout, 'global-operation-loading-v90-ui:start'), 'Loading UI marker exists exactly once');
result(exactlyOnce($layout, 'global-operation-loading-v90-js:start'), 'JS marker exists exactly once');
result(contains($layout, "asset('css/global-operation-loading-v90.css')"), 'Layout loads V90 stylesheet');
result(contains($layout, 'id="daGlobalLoading"'), 'Global loading overlay exists');
result(contains($layout, 'data-da-page-progress'), 'Top progress bar exists');
result(contains($layout, "asset('js/global-operation-loading-v90.js')"), 'Layout loads V90 script');

result(contains($css, '.da-page-progress'), 'Top progress style exists');
result(contains($css, '.da-global-loading.is-visible'), 'Loading overlay visible state exists');
result(contains($css, 'html[data-theme="dark"]'), 'Dark mode loading style exists');
result(contains($css, '@media (prefers-reduced-motion: reduce)'), 'Reduced-motion support exists');

result(contains($js, 'window.DocumentArchiveLoading'), 'Public loading API exists');
result(contains($js, "document.addEventListener('submit'"), 'Form submission hook exists');
result(contains($js, "document.addEventListener('click'"), 'Internal navigation hook exists');
result(contains($js, "window.addEventListener('pageshow'"), 'Back-forward cache reset exists');
result(contains($js, "window.addEventListener('beforeunload'"), 'Page unload hook exists');
result(contains($js, "document.addEventListener('da:loading:progress'"), 'Explicit progress event exists');
result(contains($js, "data-da-loading=\"off\""), 'Per-element loading opt-out exists');
result(!contains($js, 'XMLHttpRequest.prototype.open ='), 'Background AJAX is not globally intercepted');
result(!contains($js, 'window.fetch ='), 'Background fetch is not globally intercepted');

if ($failures > 0) {
    echo "\nGlobal Operation Loading V90 verification FAILED.\n";
    echo "Failures: {$failures}\n";
    exit(1);
}

echo "\nGlobal Operation Loading V90 verification PASSED.\n";

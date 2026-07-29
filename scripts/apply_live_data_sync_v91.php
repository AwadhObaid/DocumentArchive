<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$routesPath = $projectRoot . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$layoutPath = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
$dashboardPath = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'index.blade.php';

function readRequired(string $path): string
{
    if (!is_file($path)) {
        throw new RuntimeException("Required file not found: {$path}");
    }

    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Unable to read file: {$path}");
    }

    return $content;
}

function writeRequired(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("Unable to write file: {$path}");
    }
}

function insertAfterOnce(string $content, string $needle, string $addition, string $marker, string $fileLabel): string
{
    if (str_contains($content, $marker)) {
        return $content;
    }

    $position = strpos($content, $needle);
    if ($position === false) {
        throw new RuntimeException("Insertion point not found in {$fileLabel}: {$needle}");
    }

    $position += strlen($needle);

    return substr($content, 0, $position) . $addition . substr($content, $position);
}

function replaceOnce(string $content, string $needle, string $replacement, string $fileLabel): string
{
    if (str_contains($content, $replacement)) {
        return $content;
    }

    $position = strpos($content, $needle);
    if ($position === false) {
        throw new RuntimeException("Replacement point not found in {$fileLabel}: {$needle}");
    }

    return substr_replace($content, $replacement, $position, strlen($needle));
}

$routes = readRequired($routesPath);
$routeNeedle = "    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');";
$routeAddition = "\n\n    // live-data-sync-v91-route:start\n"
    . "    Route::get('/live-sync/status', [\\App\\Http\\Controllers\\LiveSyncController::class, 'status'])\n"
    . "        ->name('live-sync.status');\n"
    . "    // live-data-sync-v91-route:end";
$routes = insertAfterOnce(
    $routes,
    $routeNeedle,
    $routeAddition,
    'live-data-sync-v91-route:start',
    'routes/web.php'
);
writeRequired($routesPath, $routes);

$layout = readRequired($layoutPath);

$bodyNeedle = "<body\n";
$bodyReplacement = "<body\n"
    . "    data-live-sync-url=\"{{ route('live-sync.status') }}\"\n"
    . "    data-live-sync-route=\"{{ request()->route()?->getName() ?? '' }}\"\n";
$layout = replaceOnce($layout, $bodyNeedle, $bodyReplacement, 'resources/views/layouts/app.blade.php');

$cssNeedle = "    {{-- global-operation-loading-v90-css:end --}}";
$cssAddition = "\n    {{-- live-data-sync-v91-css:start --}}\n"
    . "    <link rel=\"stylesheet\" href=\"{{ asset('css/live-data-sync-v91.css') }}?v={{ filemtime(public_path('css/live-data-sync-v91.css')) }}\">\n"
    . "    {{-- live-data-sync-v91-css:end --}}";
$layout = insertAfterOnce(
    $layout,
    $cssNeedle,
    $cssAddition,
    'live-data-sync-v91-css:start',
    'resources/views/layouts/app.blade.php'
);

$jsNeedle = "    {{-- global-operation-loading-v90-js:end --}}";
$jsAddition = "\n    {{-- live-data-sync-v91-js:start --}}\n"
    . "    <script src=\"{{ asset('js/live-data-sync-v91.js') }}?v={{ filemtime(public_path('js/live-data-sync-v91.js')) }}\" defer></script>\n"
    . "    {{-- live-data-sync-v91-js:end --}}";
$layout = insertAfterOnce(
    $layout,
    $jsNeedle,
    $jsAddition,
    'live-data-sync-v91-js:start',
    'resources/views/layouts/app.blade.php'
);

writeRequired($layoutPath, $layout);

$dashboard = readRequired($dashboardPath);

$replacements = [
    '<div class="da-stat-value">{{ $num(da_dashboard_value($stats, \'documents_total\', 0)) }}</div>'
        => '<div class="da-stat-value" data-live-sync-count="documents_total">{{ $num(da_dashboard_value($stats, \'documents_total\', 0)) }}</div>',

    '<div class="da-stat-value">{{ $num(da_dashboard_value($stats, \'documents_active\', 0)) }}</div>'
        => '<div class="da-stat-value" data-live-sync-count="documents_active">{{ $num(da_dashboard_value($stats, \'documents_active\', 0)) }}</div>',

    '<div class="da-stat-value">{{ $num(da_dashboard_value($stats, \'documents_today\', 0)) }}</div>'
        => '<div class="da-stat-value" data-live-sync-count="documents_today">{{ $num(da_dashboard_value($stats, \'documents_today\', 0)) }}</div>',

    '<div class="da-stat-value">{{ $num(da_dashboard_value($stats, \'documents_month\', 0)) }}</div>'
        => '<div class="da-stat-value" data-live-sync-count="documents_month">{{ $num(da_dashboard_value($stats, \'documents_month\', 0)) }}</div>',

    '<div class="da-stat-value">{{ $num(da_dashboard_value($stats, \'documents_with_attachments\', 0)) }}</div>'
        => '<div class="da-stat-value" data-live-sync-count="documents_with_attachments">{{ $num(da_dashboard_value($stats, \'documents_with_attachments\', 0)) }}</div>',

    '<div class="da-stat-value">{{ $num(da_dashboard_value($stats, \'documents_without_attachments\', 0)) }}</div>'
        => '<div class="da-stat-value" data-live-sync-count="documents_without_attachments">{{ $num(da_dashboard_value($stats, \'documents_without_attachments\', 0)) }}</div>',

    '<div class="da-stat-value">{{ $num(da_dashboard_value($stats, \'documents_trashed\', 0)) }}</div>'
        => '<div class="da-stat-value" data-live-sync-count="documents_trashed">{{ $num(da_dashboard_value($stats, \'documents_trashed\', 0)) }}</div>',

    '<div class="da-stat-value">{{ $num(da_dashboard_value($stats, \'attachments_total\', 0)) }}</div>'
        => '<div class="da-stat-value" data-live-sync-count="attachments_total">{{ $num(da_dashboard_value($stats, \'attachments_total\', 0)) }}</div>',

    '<div class="da-module-count">{{ $num(da_dashboard_value($stats, \'memos_total\', 0)) }}</div>'
        => '<div class="da-module-count" data-live-sync-count="memos_total">{{ $num(da_dashboard_value($stats, \'memos_total\', 0)) }}</div>',

    '<div class="da-module-count">{{ $num(da_dashboard_value($stats, \'circulars_total\', 0)) }}</div>'
        => '<div class="da-module-count" data-live-sync-count="circulars_total">{{ $num(da_dashboard_value($stats, \'circulars_total\', 0)) }}</div>',

    '<div class="da-module-count">{{ $num(da_dashboard_value($stats, \'misc_books_total\', 0)) }}</div>'
        => '<div class="da-module-count" data-live-sync-count="misc_books_total">{{ $num(da_dashboard_value($stats, \'misc_books_total\', 0)) }}</div>',
];

foreach ($replacements as $needle => $replacement) {
    $dashboard = replaceOnce(
        $dashboard,
        $needle,
        $replacement,
        'resources/views/dashboard/index.blade.php'
    );
}

if (!str_contains($dashboard, 'LIVE_DATA_SYNC_V91_DASHBOARD')) {
    $markerNeedle = '    <div class="da-grid da-grid-stats">';
    $markerReplacement = "    {{-- LIVE_DATA_SYNC_V91_DASHBOARD --}}\n" . $markerNeedle;
    $dashboard = replaceOnce(
        $dashboard,
        $markerNeedle,
        $markerReplacement,
        'resources/views/dashboard/index.blade.php'
    );
}

writeRequired($dashboardPath, $dashboard);

echo "Live Data Synchronization V91.1.2 patches applied successfully.\n";

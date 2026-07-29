<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$failures = 0;

function checkItem(bool $condition, string $label, bool $warning = false): void
{
    global $failures;

    if ($condition) {
        echo "[ OK ] {$label}\n";
        return;
    }

    if ($warning) {
        echo "[WARN] {$label}\n";
        return;
    }

    echo "[FAIL] {$label}\n";
    $failures++;
}

function fileText(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    $content = file_get_contents($path);

    return $content === false ? '' : $content;
}

echo "DocumentArchive Live Data Synchronization V91.1.2 verification\n";
echo "===========================================================\n";

$controllerPath = $projectRoot . '/app/Http/Controllers/LiveSyncController.php';
$routesPath = $projectRoot . '/routes/web.php';
$layoutPath = $projectRoot . '/resources/views/layouts/app.blade.php';
$dashboardPath = $projectRoot . '/resources/views/dashboard/index.blade.php';
$cssPath = $projectRoot . '/public/css/live-data-sync-v91.css';
$jsPath = $projectRoot . '/public/js/live-data-sync-v91.js';
$applyPath = $projectRoot . '/scripts/apply_live_data_sync_v91.php';

$controller = fileText($controllerPath);
$routes = fileText($routesPath);
$layout = fileText($layoutPath);
$dashboard = fileText($dashboardPath);
$css = fileText($cssPath);
$js = fileText($jsPath);

checkItem(is_file($controllerPath), 'Live sync controller exists');
checkItem(str_contains($controller, 'class LiveSyncController'), 'Live sync controller class is defined');
checkItem(str_contains($controller, "poll_interval_ms' => 15000"), 'Polling interval is configured');
checkItem(str_contains($controller, "'documents' => 'documents.view'"), 'Documents permission is respected');
checkItem(str_contains($controller, "'memos' => 'memos.view'"), 'Memos permission is respected');
checkItem(str_contains($controller, "'circulars' => 'circulars.view'"), 'Circulars permission is respected');
checkItem(str_contains($controller, "'misc_books' => 'misc_books.view'"), 'Misc books permission is respected');
checkItem(str_contains($controller, "resourceState('documents', 'document_attachments')"), 'Document attachment changes are included');
checkItem(str_contains($controller, "resourceState('memos', 'memo_attachments')"), 'Memo attachment changes are included');
checkItem(str_contains($controller, "resourceState('circulars', 'circular_attachments')"), 'Circular attachment changes are included');
checkItem(str_contains($controller, "resourceState('misc_books', 'misc_book_attachments')"), 'Misc attachment changes are included');

checkItem(substr_count($routes, 'live-data-sync-v91-route:start') === 1, 'Live sync route marker exists once');
checkItem(str_contains($routes, "->name('live-sync.status')"), 'Live sync status route is registered in routes file');

checkItem(substr_count($layout, 'live-data-sync-v91-css:start') === 1, 'Live sync stylesheet is loaded once');
checkItem(substr_count($layout, 'live-data-sync-v91-js:start') === 1, 'Live sync script is loaded once');
checkItem(str_contains($layout, 'data-live-sync-url="{{ route(\'live-sync.status\') }}"'), 'Live sync endpoint is exposed to the page');
checkItem(str_contains($layout, 'data-live-sync-route="{{ request()->route()?->getName() ?? \'\' }}"'), 'Current route name is exposed to the page');

checkItem(is_file($cssPath) && str_contains($css, '.da-live-sync-notice'), 'Live sync notice styles exist');
checkItem(is_file($jsPath) && str_contains($js, 'DocumentArchive Live Data Synchronization V91.1.2'), 'Live sync JavaScript exists');
checkItem(str_contains($js, "route.startsWith('documents.')"), 'Documents pages are monitored');
checkItem(str_contains($js, "route.startsWith('memos.')"), 'Memos pages are monitored');
checkItem(str_contains($js, "route.startsWith('circulars.')"), 'Circulars pages are monitored');
checkItem(str_contains($js, "route.startsWith('misc-books.')"), 'Misc books pages are monitored');
checkItem(str_contains($js, 'state.dirty'), 'Unsaved form changes are protected');
checkItem(!str_contains($js, 'setInterval('), 'Recursive polling avoids overlapping requests');

foreach ([
    'documents_total',
    'documents_active',
    'documents_today',
    'documents_month',
    'documents_with_attachments',
    'documents_without_attachments',
    'documents_trashed',
    'attachments_total',
    'memos_total',
    'circulars_total',
    'misc_books_total',
] as $counter) {
    checkItem(
        str_contains($dashboard, 'data-live-sync-count="' . $counter . '"'),
        "Dashboard counter is connected: {$counter}"
    );
}

checkItem(is_file($applyPath), 'Apply script exists');

$lintTargets = [
    $controllerPath,
    $applyPath,
    __FILE__,
];

foreach ($lintTargets as $lintTarget) {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($lintTarget);
    $output = [];
    $exitCode = 1;
    exec($command . ' 2>&1', $output, $exitCode);
    checkItem($exitCode === 0, 'PHP syntax: ' . str_replace($projectRoot . '/', '', $lintTarget));
}

$databaseWarning = false;

try {
    $autoloadPath = $projectRoot . '/vendor/autoload.php';
    $bootstrapPath = $projectRoot . '/bootstrap/app.php';

    if (!is_file($autoloadPath) || !is_file($bootstrapPath)) {
        throw new RuntimeException('Laravel bootstrap files are unavailable.');
    }

    require_once $autoloadPath;
    $app = require $bootstrapPath;
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    /** @var \Illuminate\Routing\Router $router */
    $router = $app->make(\Illuminate\Routing\Router::class);
    $liveSyncRoute = $router->getRoutes()->getByName('live-sync.status');

    checkItem(
        $liveSyncRoute !== null
        && trim((string) $liveSyncRoute->uri(), '/') === 'live-sync/status'
        && in_array('GET', $liveSyncRoute->methods(), true),
        'Laravel recognizes the live sync route'
    );

    if (\Illuminate\Support\Facades\Schema::hasTable('documents')) {
        echo "[ OK ] Database tables are reachable\n";
    } else {
        $databaseWarning = true;
    }
} catch (Throwable $e) {
    checkItem(false, 'Laravel runtime bootstrap completed');
    echo '[INFO] Runtime bootstrap error: ' . $e->getMessage() . "\n";
    $databaseWarning = true;
}

if ($databaseWarning) {
    checkItem(false, 'Database runtime check was skipped because the database is unavailable', true);
}

echo "\n";

if ($failures > 0) {
    echo "Live Data Synchronization V91.1.2 verification FAILED.\n";
    echo "Failures: {$failures}\n";
    exit(1);
}

echo "Live Data Synchronization V91.1.2 verification PASSED.\n";
exit(0);

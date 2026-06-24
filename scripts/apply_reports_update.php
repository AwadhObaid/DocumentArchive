<?php

$root = dirname(__DIR__);
$routesPath = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$layoutPath = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';

function fail_message(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

if (!file_exists($routesPath)) {
    fail_message('routes/web.php not found.');
}

$routes = file_get_contents($routesPath);

if (strpos($routes, 'ReportController') === false) {
    $routes = preg_replace(
        '/use App\\\\Http\\\\Controllers\\\\BackupController;\s*/',
        "use App\\Http\\Controllers\\BackupController;\nuse App\\Http\\Controllers\\ReportController;\n",
        $routes,
        1,
        $count
    );

    if ($count === 0) {
        $routes = preg_replace(
            '/use Illuminate\\\\Support\\\\Facades\\\\Route;\s*/',
            "use App\\Http\\Controllers\\ReportController;\nuse Illuminate\\Support\\Facades\\Route;\n",
            $routes,
            1
        );
    }
}

if (strpos($routes, "reports.index") === false) {
    $reportRoutes = <<<'PHPROUTES'

    // Reports routes
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

PHPROUTES;

    $target = "    Route::resource('documents', DocumentController::class);";
    if (strpos($routes, $target) !== false) {
        $routes = str_replace($target, $reportRoutes . $target, $routes);
    } else {
        // Fallback: append before the last auth group closing if the usual location is not found.
        $routes = preg_replace('/\n\}\);\s*$/', $reportRoutes . "\n});\n", $routes, 1);
    }
}

file_put_contents($routesPath, $routes);

if (file_exists($layoutPath)) {
    $layout = file_get_contents($layoutPath);

    if (strpos($layout, 'REPORTS-SIDEBAR-LINK') === false) {
        $link = <<<'BLADELINK'

                {{-- REPORTS-SIDEBAR-LINK --}}
                <a href="{{ route('reports.index') }}" class="sidebar-link nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <span class="nav-icon">📊</span>
                    <span>التقارير</span>
                </a>
BLADELINK;

        $inserted = false;
        $patterns = [
            '/\n\s*\{\{--\s*BACKUP-SIDEBAR.*?--\}\}/u',
            '/\n\s*<a[^>]+href="\{\{\s*url\(\'\/backups\'\)\s*\}\}"/u',
            '/\n\s*<a[^>]+href="\{\{\s*route\(\'backups\.index\'\)\s*\}\}"/u',
            '/\n\s*<\/nav>/iu',
            '/\n\s*<\/aside>/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $layout, $match, PREG_OFFSET_CAPTURE)) {
                $pos = $match[0][1];
                $layout = substr($layout, 0, $pos) . $link . substr($layout, $pos);
                $inserted = true;
                break;
            }
        }

        if (!$inserted) {
            $layout .= $link . PHP_EOL;
        }

        file_put_contents($layoutPath, $layout);
    }
}

echo "Reports update applied successfully.\n";

<?php

$root = dirname(__DIR__);
$routesPath = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$middlewarePath = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Middleware' . DIRECTORY_SEPARATOR . 'ApplyRoutePermissions.php';
$viewsRoot = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';

if (!file_exists($routesPath)) {
    fwrite(STDERR, "routes/web.php not found. Run this script from project root after extracting the ZIP.\n");
    exit(1);
}

patchRoutes($routesPath);
patchPermissionMiddleware($middlewarePath);
patchSidebarLink($viewsRoot);

echo "Data quality review update applied.\n";
echo "Next commands:\n";
echo "composer dump-autoload\n";
echo "php artisan route:clear\n";
echo "php artisan view:clear\n";
echo "php artisan optimize:clear\n";
echo "php scripts/check_data_quality_update.php\n";

function patchRoutes(string $routesPath): void
{
    $content = file_get_contents($routesPath);
    $original = $content;

    $useLine = "use App\\Http\\Controllers\\DataQualityController;";
    if (!str_contains($content, $useLine)) {
        $anchor = "use App\\Http\\Controllers\\SystemHealthController;";
        if (str_contains($content, $anchor)) {
            $content = str_replace($anchor, $anchor . PHP_EOL . $useLine, $content);
        } else {
            $content = preg_replace('/<\?php\s*/', "<?php\n\n" . $useLine . "\n", $content, 1);
        }
    }

    if (!str_contains($content, "data-quality.index")) {
        $route = <<<PHP_ROUTE

    Route::get('/data-quality', [DataQualityController::class, 'index'])
        ->name('data-quality.index');
PHP_ROUTE;

        if (str_contains($content, "Route::get('/system-health'")) {
            $content = preg_replace(
                "/(Route::get\('\/system-health'[\s\S]*?->name\('system-health\.index'\);)/",
                "$1" . $route,
                $content,
                1
            );
        } elseif (str_contains($content, "Route::get('/dashboard'")) {
            $content = preg_replace(
                "/(Route::get\('\/dashboard'[\s\S]*?->name\('dashboard'\);)/",
                "$1" . $route,
                $content,
                1
            );
        } else {
            $content = preg_replace('/Route::middleware\([^;]+?->group\(function\s*\(\)\s*\{/', '$0' . $route, $content, 1);
        }
    }

    if ($content !== $original) {
        $backup = $routesPath . '.before-data-quality.bak';
        if (!file_exists($backup)) {
            file_put_contents($backup, $original);
        }
        file_put_contents($routesPath, $content);
        echo "routes/web.php updated.\n";
    } else {
        echo "routes/web.php already contains data-quality route.\n";
    }
}

function patchPermissionMiddleware(string $middlewarePath): void
{
    if (!file_exists($middlewarePath)) {
        echo "ApplyRoutePermissions middleware not found. Skipping permission middleware patch.\n";
        return;
    }

    $content = file_get_contents($middlewarePath);
    $original = $content;

    if (!str_contains($content, "data-quality.")) {
        $needle = "        if (str_starts_with(\$name, 'reports.')) {\n            return 'reports.view';\n        }";
        $insert = $needle . "\n\n        if (str_starts_with(\$name, 'data-quality.')) {\n            return 'reports.view';\n        }";

        if (str_contains($content, $needle)) {
            $content = str_replace($needle, $insert, $content);
        } else {
            $content = str_replace("        return null;", "        if (str_starts_with(\$name, 'data-quality.')) {\n            return 'reports.view';\n        }\n\n        return null;", $content);
        }
    }

    if ($content !== $original) {
        $backup = $middlewarePath . '.before-data-quality.bak';
        if (!file_exists($backup)) {
            file_put_contents($backup, $original);
        }
        file_put_contents($middlewarePath, $content);
        echo "ApplyRoutePermissions.php updated.\n";
    } else {
        echo "ApplyRoutePermissions.php already supports data-quality route.\n";
    }
}

function patchSidebarLink(string $viewsRoot): void
{
    if (!is_dir($viewsRoot)) {
        echo "resources/views not found. Skipping sidebar link patch.\n";
        return;
    }

    $linkBlock = <<<'BLADE'
@if(auth()->user()?->hasPermission('reports.view'))
                            <a href="{{ route('data-quality.index') }}">🧭 جودة البيانات</a>
                        @endif
BLADE;

    $updated = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsRoot, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $path = $file->getPathname();
        $relative = str_replace(dirname($viewsRoot) . DIRECTORY_SEPARATOR, '', $path);

        if (!str_contains($relative, 'layouts') && !str_contains($relative, 'partials') && !str_contains($relative, 'components')) {
            continue;
        }

        $content = file_get_contents($path);
        $original = $content;

        if (str_contains($content, "data-quality.index")) {
            continue;
        }

        if (str_contains($content, "route('system-health.index')")) {
            $content = preg_replace(
                '/(<a[^>]+route\(\'system-health\.index\'\)[\s\S]*?<\/a>)/',
                "$1\n                        " . $linkBlock,
                $content,
                1
            );
        } elseif (str_contains($content, "route('reports.index')")) {
            $content = preg_replace(
                '/(<a[^>]+route\(\'reports\.index\'\)[\s\S]*?<\/a>)/',
                "$1\n                        " . $linkBlock,
                $content,
                1
            );
        } elseif (str_contains($content, "route('dashboard')")) {
            $content = preg_replace(
                '/(<a[^>]+route\(\'dashboard\'\)[\s\S]*?<\/a>)/',
                "$1\n                        " . $linkBlock,
                $content,
                1
            );
        }

        if ($content !== $original) {
            $backup = $path . '.before-data-quality-link.bak';
            if (!file_exists($backup)) {
                file_put_contents($backup, $original);
            }
            file_put_contents($path, $content);
            $updated[] = $relative;
        }
    }

    if ($updated === []) {
        echo "No sidebar/layout link was patched. You can open /data-quality directly.\n";
    } else {
        echo "Sidebar/layout link patched in:\n";
        foreach ($updated as $file) {
            echo "- {$file}\n";
        }
    }
}

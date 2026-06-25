<?php

/**
 * DocumentArchive - Profile page patch
 * Adds profile routes, sidebar link, and small CSS helpers.
 */

$root = dirname(__DIR__);
$routesPath = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';

if (!file_exists($routesPath)) {
    fwrite(STDERR, "routes/web.php not found. Run from project root.\n");
    exit(1);
}

patchRoutes($routesPath);
patchLayouts($root);
patchCss($root);

echo "Profile update applied.\n";
echo "Next commands:\n";
echo "composer dump-autoload\n";
echo "php artisan route:clear\n";
echo "php artisan view:clear\n";
echo "php artisan optimize:clear\n";

function patchRoutes(string $routesPath): void
{
    $content = file_get_contents($routesPath);
    $original = $content;

    $useLine = "use App\\Http\\Controllers\\ProfileController;";
    if (!str_contains($content, $useLine)) {
        $controllerUses = [
            "use App\\Http\\Controllers\\UserController;",
            "use App\\Http\\Controllers\\SettingsController;",
            "use App\\Http\\Controllers\\DashboardController;",
        ];

        $inserted = false;
        foreach ($controllerUses as $needle) {
            if (str_contains($content, $needle)) {
                $content = str_replace($needle, $needle . PHP_EOL . $useLine, $content);
                $inserted = true;
                break;
            }
        }

        if (!$inserted) {
            $content = preg_replace('/<\?php\s*/', "<?php\n\n" . $useLine . "\n", $content, 1);
        }
    }

    if (!str_contains($content, "profile.edit")) {
        $profileRoutes = <<<'ROUTES'

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');
ROUTES;

        $dashboardLine = "Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');";
        if (str_contains($content, $dashboardLine)) {
            $content = str_replace($dashboardLine, $dashboardLine . $profileRoutes, $content);
        } else {
            $content = preg_replace(
                "/Route::middleware\(\s*\[?\s*'auth'[^\)]*\)\s*->group\(function\s*\(\)\s*\{\s*/",
                "$0" . $profileRoutes . PHP_EOL,
                $content,
                1
            );
        }
    }

    if ($content !== $original) {
        file_put_contents($routesPath . '.before-profile.bak', $original);
        file_put_contents($routesPath, $content);
        echo "routes/web.php updated.\n";
    } else {
        echo "routes/web.php already contains profile routes.\n";
    }
}

function patchLayouts(string $root): void
{
    $layoutCandidates = [
        $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php',
        $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'admin.blade.php',
    ];

    foreach ($layoutCandidates as $path) {
        if (!file_exists($path)) {
            continue;
        }

        $content = file_get_contents($path);
        $original = $content;

        if (str_contains($content, "route('profile.edit')") || str_contains($content, 'route("profile.edit")')) {
            echo basename($path) . " already has profile link.\n";
            continue;
        }

        $profileLink = "            <a href=\"{{ route('profile.edit') }}\" class=\"{{ request()->routeIs('profile.*') ? 'active' : '' }}\">👤 الملف الشخصي</a>" . PHP_EOL;

        if (str_contains($content, '<nav class="side-nav">')) {
            $content = str_replace('<nav class="side-nav">', '<nav class="side-nav">' . PHP_EOL . $profileLink, $content);
        } elseif (str_contains($content, '<div class="sidebar-footer">')) {
            $footerLink = "            <a href=\"{{ route('profile.edit') }}\" class=\"btn btn-secondary profile-footer-link\">الملف الشخصي</a>" . PHP_EOL;
            $content = str_replace('<div class="sidebar-footer">', '<div class="sidebar-footer">' . PHP_EOL . $footerLink, $content);
        } else {
            echo basename($path) . " was not patched because no sidebar marker was found.\n";
            continue;
        }

        file_put_contents($path . '.before-profile.bak', $original);
        file_put_contents($path, $content);
        echo str_replace($root . DIRECTORY_SEPARATOR, '', $path) . " updated.\n";
    }
}

function patchCss(string $root): void
{
    $cssFiles = [
        $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'app.css',
        $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css',
        $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'admin.css',
    ];

    $css = <<<'CSS'

/* Profile page helpers */
.profile-page {
    display: grid;
    gap: 18px;
}

.profile-card-header {
    display: flex;
    align-items: center;
    gap: 16px;
}

.profile-avatar-lg {
    width: 64px;
    height: 64px;
    border-radius: 20px;
    display: grid;
    place-items: center;
    background: rgba(37, 99, 235, 0.14);
    color: #2563eb;
    font-size: 28px;
    font-weight: 800;
}

.readonly-input {
    opacity: .75;
    cursor: not-allowed;
}

.muted-note {
    color: #64748b;
    margin-top: -6px;
    margin-bottom: 16px;
}

.form-actions {
    display: flex;
    justify-content: flex-start;
    gap: 10px;
    margin-top: 18px;
}

.profile-footer-link {
    text-align: center;
    margin-bottom: 10px;
}
CSS;

    foreach ($cssFiles as $path) {
        if (!file_exists($path)) {
            continue;
        }

        $content = file_get_contents($path);
        if (str_contains($content, 'Profile page helpers')) {
            echo str_replace($root . DIRECTORY_SEPARATOR, '', $path) . " already has profile CSS.\n";
            continue;
        }

        file_put_contents($path . '.before-profile.bak', $content);
        file_put_contents($path, rtrim($content) . PHP_EOL . $css . PHP_EOL);
        echo str_replace($root . DIRECTORY_SEPARATOR, '', $path) . " updated.\n";
        break;
    }
}

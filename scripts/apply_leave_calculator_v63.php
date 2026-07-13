<?php

$root = dirname(__DIR__);

function v63_path(string $relative): string
{
    global $root;
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function v63_fail(string $message): void
{
    fwrite(STDERR, "[FAIL] {$message}" . PHP_EOL);
    exit(1);
}

function v63_ok(string $message): void
{
    echo "[OK] {$message}" . PHP_EOL;
}

function v63_read(string $relative): string
{
    $path = v63_path($relative);
    if (!is_file($path)) {
        v63_fail("Missing file: {$relative}");
    }
    $content = file_get_contents($path);
    if ($content === false) {
        v63_fail("Unable to read: {$relative}");
    }
    return $content;
}

function v63_write(string $relative, string $content): void
{
    $path = v63_path($relative);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (file_put_contents($path, $content) === false) {
        v63_fail("Unable to write: {$relative}");
    }
    v63_ok("Updated: {$relative}");
}

function v63_insert_after(string $content, string $needle, string $insert, string $label): string
{
    if (str_contains($content, trim($insert))) {
        return $content;
    }

    $pos = strpos($content, $needle);
    if ($pos === false) {
        v63_fail("Could not find insertion point for {$label}");
    }

    return substr($content, 0, $pos + strlen($needle)) . $insert . substr($content, $pos + strlen($needle));
}

// routes/web.php
$routes = v63_read('routes/web.php');
if (!str_contains($routes, 'LeaveCalculatorController')) {
    $routes = str_replace(
        "use App\\Http\\Controllers\\LiteController;" . PHP_EOL,
        "use App\\Http\\Controllers\\LiteController;" . PHP_EOL . "use App\\Http\\Controllers\\LeaveCalculatorController;" . PHP_EOL,
        $routes
    );
}

if (!str_contains($routes, "tools.leave-calculator.index")) {
    $dashboardLine = "    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');";
    $routeBlock = PHP_EOL . PHP_EOL . "    Route::get('/tools/leave-calculator', [LeaveCalculatorController::class, 'index'])" . PHP_EOL . "        ->name('tools.leave-calculator.index');";
    $routes = v63_insert_after($routes, $dashboardLine, $routeBlock, 'leave calculator route');
}
v63_write('routes/web.php', $routes);

// ApplyRoutePermissions.php
$middleware = v63_read('app/Http/Middleware/ApplyRoutePermissions.php');
if (!str_contains($middleware, "'tools.leave-calculator.index'")) {
    $middleware = str_replace(
        "        'profile.*' => 'profile.manage'," . PHP_EOL,
        "        'profile.*' => 'profile.manage'," . PHP_EOL . "        'tools.leave-calculator.index' => 'tools.leave_calculator'," . PHP_EOL,
        $middleware
    );
}
v63_write('app/Http/Middleware/ApplyRoutePermissions.php', $middleware);

// PermissionRegistry.php
$registry = v63_read('app/Support/PermissionRegistry.php');
if (!str_contains($registry, "'tools.leave_calculator' =>")) {
    $registry = str_replace(
        "                    'profile.manage' => 'إدارة الملف الشخصي'," . PHP_EOL,
        "                    'profile.manage' => 'إدارة الملف الشخصي'," . PHP_EOL . "                    'tools.leave_calculator' => 'حاسبة الإجازات'," . PHP_EOL,
        $registry
    );
}

if (!str_contains($registry, "'tools.leave_calculator',")) {
    $registry = str_replace(
        "                'profile.manage'," . PHP_EOL,
        "                'profile.manage'," . PHP_EOL . "                'tools.leave_calculator'," . PHP_EOL,
        $registry
    );
}
v63_write('app/Support/PermissionRegistry.php', $registry);

// resources/views/layouts/app.blade.php
$layout = v63_read('resources/views/layouts/app.blade.php');
if (!str_contains($layout, "tools.leave-calculator.index")) {
    $link = PHP_EOL . PHP_EOL . "            @if(auth()->user()?->hasPermission('tools.leave_calculator'))" . PHP_EOL
        . "                <a href=\"{{ route('tools.leave-calculator.index') }}\" class=\"{{ request()->routeIs('tools.leave-calculator.*') ? 'active' : '' }}\">🧮 حاسبة الإجازات</a>" . PHP_EOL
        . "            @endif" . PHP_EOL;

    $liteLine = "            <a href=\"{{ route('lite.index') }}\" class=\"{{ request()->routeIs('lite.*') ? 'active' : '' }}\">📱 نسخة الهاتف لايت</a>";
    if (str_contains($layout, $liteLine)) {
        $layout = v63_insert_after($layout, $liteLine, $link, 'sidebar leave calculator link');
    } else {
        $dashboardEnd = "            @endif" . PHP_EOL . PHP_EOL . "            <a href=\"{{ route('lite.index') }}\"";
        if (!str_contains($layout, $dashboardEnd)) {
            v63_fail('Could not find sidebar insertion point. Add the leave calculator link manually.');
        }
        $layout = str_replace($dashboardEnd, "            @endif" . $link . PHP_EOL . "            <a href=\"{{ route('lite.index') }}\"", $layout);
    }
}
v63_write('resources/views/layouts/app.blade.php', $layout);

echo PHP_EOL . "Leave calculator V63 applied successfully." . PHP_EOL;
echo "No migration required. No npm build required." . PHP_EOL;

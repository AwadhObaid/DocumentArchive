<?php

$root = dirname(__DIR__);

function v64_path(string $relative): string
{
    global $root;
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function v64_fail(string $message): void
{
    fwrite(STDERR, "[FAIL] {$message}" . PHP_EOL);
    exit(1);
}

function v64_ok(string $message): void
{
    echo "[OK] {$message}" . PHP_EOL;
}

function v64_read(string $relative): string
{
    $path = v64_path($relative);
    if (!is_file($path)) {
        v64_fail("Missing file: {$relative}");
    }

    $content = file_get_contents($path);
    if ($content === false) {
        v64_fail("Unable to read: {$relative}");
    }

    return $content;
}

function v64_write(string $relative, string $content): void
{
    $path = v64_path($relative);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    if (file_put_contents($path, $content) === false) {
        v64_fail("Unable to write: {$relative}");
    }

    v64_ok("Updated: {$relative}");
}

// 1) Ensure controller file exists with the correct namespace.
$controller = <<<'PHP'
<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LeaveCalculatorController extends Controller
{
    public function index(): View
    {
        return view('tools.leave-calculator');
    }
}
PHP;

v64_write('app/Http/Controllers/LeaveCalculatorController.php', $controller . PHP_EOL);

// 2) Fix routes/web.php. The previous route may have been registered as a string controller
//    LeaveCalculatorController@index, which Laravel resolves as a non-namespaced class.
$routes = v64_read('routes/web.php');

if (!str_contains($routes, 'use App\\Http\\Controllers\\LeaveCalculatorController;')) {
    $lines = preg_split('/\R/', $routes);
    $inserted = false;
    $lastControllerUseIndex = null;

    foreach ($lines as $index => $line) {
        if (str_starts_with(trim($line), 'use App\\Http\\Controllers\\')) {
            $lastControllerUseIndex = $index;
        }
    }

    if ($lastControllerUseIndex !== null) {
        array_splice($lines, $lastControllerUseIndex + 1, 0, 'use App\\Http\\Controllers\\LeaveCalculatorController;');
        $routes = implode(PHP_EOL, $lines);
        $inserted = true;
    }

    if (!$inserted) {
        $routes = "use App\\Http\\Controllers\\LeaveCalculatorController;" . PHP_EOL . $routes;
    }
}

$routes = str_replace(
    [
        "Route::get('/tools/leave-calculator', 'LeaveCalculatorController@index')",
        'Route::get("/tools/leave-calculator", "LeaveCalculatorController@index")',
        "Route::get('/tools/leave-calculator', \"LeaveCalculatorController@index\")",
        'Route::get("/tools/leave-calculator", \'LeaveCalculatorController@index\')',
    ],
    "Route::get('/tools/leave-calculator', [LeaveCalculatorController::class, 'index'])",
    $routes
);

$routes = str_replace(
    [
        "'LeaveCalculatorController@index'",
        '"LeaveCalculatorController@index"',
        'LeaveCalculatorController@index',
    ],
    "[LeaveCalculatorController::class, 'index']",
    $routes
);

if (!str_contains($routes, "tools.leave-calculator.index")) {
    $routeBlock = PHP_EOL . "    Route::get('/tools/leave-calculator', [LeaveCalculatorController::class, 'index'])" . PHP_EOL
        . "        ->name('tools.leave-calculator.index');" . PHP_EOL;

    $dashboardNeedle = "Route::get('/dashboard'";
    $pos = strpos($routes, $dashboardNeedle);
    if ($pos !== false) {
        $lineEnd = strpos($routes, PHP_EOL, $pos);
        if ($lineEnd !== false) {
            $routes = substr($routes, 0, $lineEnd + strlen(PHP_EOL)) . $routeBlock . substr($routes, $lineEnd + strlen(PHP_EOL));
        } else {
            $routes .= $routeBlock;
        }
    } else {
        $routes .= $routeBlock;
    }
}

v64_write('routes/web.php', $routes);

echo PHP_EOL . "Leave calculator controller route fix V64 applied successfully." . PHP_EOL;
echo "No migration required. No npm build required." . PHP_EOL;

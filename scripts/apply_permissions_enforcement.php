<?php

$root = dirname(__DIR__);
$routesPath = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$middlewarePath = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Middleware' . DIRECTORY_SEPARATOR . 'ApplyRoutePermissions.php';

if (!file_exists($routesPath)) {
    fwrite(STDERR, "routes/web.php not found.\n");
    exit(1);
}

if (!file_exists($middlewarePath)) {
    fwrite(STDERR, "ApplyRoutePermissions.php not found. Make sure you extracted the update into the project root.\n");
    exit(1);
}

$content = file_get_contents($routesPath);
$original = $content;

$useLine = "use App\\Http\\Middleware\\ApplyRoutePermissions;";
if (!str_contains($content, $useLine)) {
    $routeUse = "use Illuminate\\Support\\Facades\\Route;";
    if (str_contains($content, $routeUse)) {
        $content = str_replace($routeUse, $useLine . PHP_EOL . $routeUse, $content);
    } else {
        $content = preg_replace('/<\?php\s*/', "<?php\n\n" . $useLine . "\n", $content, 1);
    }
}

$patterns = [
    "/Route::middleware\(\s*'auth'\s*\)->group\(function\s*\(\)\s*\{/",
    "/Route::middleware\(\s*\[\s*'auth'\s*\]\s*\)->group\(function\s*\(\)\s*\{/",
];

foreach ($patterns as $pattern) {
    $content = preg_replace(
        $pattern,
        "Route::middleware(['auth', ApplyRoutePermissions::class])->group(function () {",
        $content
    );
}

// If a previous run already inserted the middleware, normalize duplicate entries.
$content = str_replace(
    "['auth', ApplyRoutePermissions::class, ApplyRoutePermissions::class]",
    "['auth', ApplyRoutePermissions::class]",
    $content
);

if ($content === $original) {
    echo "No route changes were needed.\n";
} else {
    file_put_contents($routesPath, $content);
    echo "routes/web.php updated successfully.\n";
}

echo "Permissions enforcement middleware is ready.\n";

<?php

$root = dirname(__DIR__);
$routesPath = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$tempDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'mpdf-temp';

function fail_report_pdf_update(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

if (!file_exists($routesPath)) {
    fail_report_pdf_update('routes/web.php not found.');
}

$routes = file_get_contents($routesPath);

if (strpos($routes, 'ReportController') === false) {
    $routes = preg_replace(
        '/use Illuminate\\\\Support\\\\Facades\\\\Route;\s*/',
        "use App\\Http\\Controllers\\ReportController;\nuse Illuminate\\Support\\Facades\\Route;\n",
        $routes,
        1,
        $count
    );

    if ($count === 0) {
        fail_report_pdf_update('Could not add ReportController import.');
    }
}

if (strpos($routes, "reports.pdf") === false) {
    $pdfRoute = "    Route::get('/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');\n";

    if (strpos($routes, "Route::get('/reports/export'") !== false) {
        $routes = preg_replace(
            "/(\s*Route::get\('\/reports\/export',\s*\[ReportController::class,\s*'export'\]\)->name\('reports\.export'\);\s*)/",
            "$1" . $pdfRoute,
            $routes,
            1,
            $count
        );

        if ($count === 0) {
            $routes = str_replace(
                "    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');\n",
                "    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');\n" . $pdfRoute,
                $routes
            );
        }
    } elseif (strpos($routes, "reports.index") !== false) {
        $routes = preg_replace(
            "/(\s*Route::get\('\/reports',\s*\[ReportController::class,\s*'index'\]\)->name\('reports\.index'\);\s*)/",
            "$1" . "    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');\n" . $pdfRoute,
            $routes,
            1
        );
    } else {
        $reportRoutes = <<<'PHPROUTES'

    // Reports routes
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');

PHPROUTES;
        $target = "    Route::resource('documents', DocumentController::class);";
        if (strpos($routes, $target) !== false) {
            $routes = str_replace($target, $reportRoutes . $target, $routes);
        } else {
            $routes .= $reportRoutes;
        }
    }
}

file_put_contents($routesPath, $routes);

if (!is_dir($tempDir)) {
    mkdir($tempDir, 0775, true);
}

echo "Reports PDF update applied successfully.\n";
echo "If mPDF is not installed, run: composer require mpdf/mpdf\n";

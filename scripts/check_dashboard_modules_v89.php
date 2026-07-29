<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$projectRoot = dirname(__DIR__);
$controllerPath = $projectRoot . DIRECTORY_SEPARATOR . 'app/Http/Controllers/DashboardController.php';
$viewPath = $projectRoot . DIRECTORY_SEPARATOR . 'resources/views/dashboard/index.blade.php';

$failures = [];
$warnings = [];

$ok = static function (string $message): void {
    echo "[ OK ] {$message}" . PHP_EOL;
};

$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
    echo "[FAIL] {$message}" . PHP_EOL;
};

$warn = static function (string $message) use (&$warnings): void {
    $warnings[] = $message;
    echo "[WARN] {$message}" . PHP_EOL;
};

echo PHP_EOL;
echo "DocumentArchive Dashboard Modules V89 verification" . PHP_EOL;
echo "==================================================" . PHP_EOL;

foreach ([
    $controllerPath => 'Dashboard controller exists',
    $viewPath => 'Dashboard view exists',
] as $path => $label) {
    if (is_file($path)) {
        $ok($label);
    } else {
        $fail($label . ': ' . $path);
    }
}

if (is_file($controllerPath)) {
    $controller = (string) file_get_contents($controllerPath);

    foreach ([
        "'memos_total' => \$this->countActiveRows('memos')" => 'Memos total is connected',
        "'circulars_total' => \$this->countActiveRows('circulars')" => 'Circulars total is connected',
        "'misc_books_total' => \$this->countActiveRows('misc_books')" => 'Misc books total is connected',
        'private function countActiveRows(string $table): int' => 'Soft-delete-safe counter exists',
        "whereNull(\$table . '.deleted_at')" => 'Deleted rows are excluded',
    ] as $needle => $label) {
        str_contains($controller, $needle) ? $ok($label) : $fail($label);
    }
}

if (is_file($viewPath)) {
    $view = (string) file_get_contents($viewPath);

    foreach ([
        'DASHBOARD_MODULES_V89' => 'Dashboard V89 style marker exists',
        'المذكرات والتعاميم والمتفرقات' => 'Dashboard modules section exists',
        "\$hasPermission('memos.view')" => 'Memos card respects permission',
        "\$hasPermission('circulars.view')" => 'Circulars card respects permission',
        "\$hasPermission('misc_books.view')" => 'Misc books card respects permission',
        "route('memos.index')" => 'Memos card route exists in view',
        "route('circulars.index')" => 'Circulars card route exists in view',
        "route('misc-books.index')" => 'Misc books card route exists in view',
        "'memos_total'" => 'Memos count is displayed',
        "'circulars_total'" => 'Circulars count is displayed',
        "'misc_books_total'" => 'Misc books count is displayed',
    ] as $needle => $label) {
        str_contains($view, $needle) ? $ok($label) : $fail($label);
    }
}

$autoload = $projectRoot . DIRECTORY_SEPARATOR . 'vendor/autoload.php';
$bootstrap = $projectRoot . DIRECTORY_SEPARATOR . 'bootstrap/app.php';

if (!is_file($autoload) || !is_file($bootstrap)) {
    $warn('Laravel runtime checks skipped because vendor/autoload.php or bootstrap/app.php is missing.');
} else {
    try {
        require $autoload;
        $app = require $bootstrap;
        $app->make(Kernel::class)->bootstrap();

        foreach ([
            'dashboard' => 'Dashboard route',
            'memos.index' => 'Memos route',
            'circulars.index' => 'Circulars route',
            'misc-books.index' => 'Misc books route',
        ] as $routeName => $label) {
            Route::has($routeName) ? $ok($label . ' is registered') : $fail($label . ' is not registered');
        }

        foreach ([
            'memos' => 'Memos',
            'circulars' => 'Circulars',
            'misc_books' => 'Misc books',
        ] as $table => $label) {
            if (!Schema::hasTable($table)) {
                $warn($label . " table is not available yet: {$table}");
                continue;
            }

            $query = DB::table($table);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull($table . '.deleted_at');
            }

            $count = (int) $query->count();
            $ok($label . ' active rows: ' . number_format($count));
        }
    } catch (Throwable $e) {
        $fail('Laravel runtime check failed: ' . $e->getMessage());
    }
}

echo PHP_EOL;

if ($failures !== []) {
    echo "Dashboard Modules V89 verification FAILED." . PHP_EOL;
    echo "Failures: " . count($failures) . PHP_EOL;
    exit(1);
}

echo "Dashboard Modules V89 verification passed.";
if ($warnings !== []) {
    echo " Warnings: " . count($warnings) . ".";
}
echo PHP_EOL;

exit(0);

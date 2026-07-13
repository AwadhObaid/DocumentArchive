<?php

declare(strict_types=1);

$root = dirname(__DIR__);

echo PHP_EOL;
echo "Applying / verifying Gemini Smart Reports V65..." . PHP_EOL;

$required = [
    'app/Http/Controllers/SmartReportController.php',
    'app/Models/SmartReportRun.php',
    'app/Services/GeminiSmartReportService.php',
    'database/migrations/2026_07_13_101500_create_smart_report_runs_v65.php',
    'resources/views/smart_reports/index.blade.php',
    'resources/views/smart_reports/word.blade.php',
    'resources/views/smart_reports/pdf.blade.php',
    'public/css/smart-reports-v65.css',
    'public/js/smart-reports-v65.js',
    'routes/web.php',
    'resources/views/settings/edit.blade.php',
    'resources/views/layouts/app.blade.php',
];

$missing = [];

foreach ($required as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (! is_file($path)) {
        $missing[] = $relative;
        echo "[FAIL] Missing: {$relative}" . PHP_EOL;
    } else {
        echo "[OK] File exists: {$relative}" . PHP_EOL;
    }
}

if ($missing !== []) {
    echo PHP_EOL . "V65 files are incomplete. Make sure you extracted the ZIP into the parent folder of DocumentArchive." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "V65 files are in place. Run:" . PHP_EOL;
echo "php artisan migrate" . PHP_EOL;
echo "php scripts/check_smart_reports_gemini_v65.php" . PHP_EOL;
echo PHP_EOL;

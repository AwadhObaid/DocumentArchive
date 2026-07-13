<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failed = false;

function ok(string $message): void
{
    echo "[OK] {$message}" . PHP_EOL;
}

function fail_msg(string $message): void
{
    global $failed;
    $failed = true;
    echo "[FAIL] {$message}" . PHP_EOL;
}

function project_path(string $relative): string
{
    global $root;
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function assert_file_exists_v65(string $relative): void
{
    if (is_file(project_path($relative))) {
        ok("File exists: {$relative}");
    } else {
        fail_msg("Missing file: {$relative}");
    }
}

function assert_contains_v65(string $relative, string $needle): void
{
    $path = project_path($relative);

    if (! is_file($path)) {
        fail_msg("Cannot inspect missing file: {$relative}");
        return;
    }

    $content = file_get_contents($path) ?: '';

    if (str_contains($content, $needle)) {
        ok("{$relative} contains: {$needle}");
    } else {
        fail_msg("{$relative} missing: {$needle}");
    }
}

function assert_php_syntax_v65(string $relative): void
{
    $path = project_path($relative);

    if (! is_file($path)) {
        fail_msg("Cannot lint missing file: {$relative}");
        return;
    }

    $command = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    $output = [];
    $code = 0;
    exec($command, $output, $code);

    if ($code === 0) {
        ok("PHP syntax valid: {$relative}");
    } else {
        fail_msg("PHP syntax error in {$relative}: " . implode(' ', $output));
    }
}

$files = [
    'app/Http/Controllers/SmartReportController.php',
    'app/Models/SmartReportRun.php',
    'app/Services/GeminiSmartReportService.php',
    'database/migrations/2026_07_13_101500_create_smart_report_runs_v65.php',
    'resources/views/smart_reports/index.blade.php',
    'resources/views/smart_reports/word.blade.php',
    'resources/views/smart_reports/pdf.blade.php',
    'public/css/smart-reports-v65.css',
    'public/js/smart-reports-v65.js',
    'scripts/apply_smart_reports_gemini_v65.php',
    'scripts/check_smart_reports_gemini_v65.php',
    'README_SMART_REPORTS_GEMINI_V65.txt',
];

foreach ($files as $file) {
    assert_file_exists_v65($file);
}

foreach ([
    'app/Http/Controllers/SmartReportController.php',
    'app/Models/SmartReportRun.php',
    'app/Services/GeminiSmartReportService.php',
    'database/migrations/2026_07_13_101500_create_smart_report_runs_v65.php',
    'scripts/apply_smart_reports_gemini_v65.php',
    'scripts/check_smart_reports_gemini_v65.php',
] as $file) {
    assert_php_syntax_v65($file);
}

assert_contains_v65('routes/web.php', 'SmartReportController');
assert_contains_v65('routes/web.php', "smart-reports.generate");
assert_contains_v65('app/Http/Middleware/ApplyRoutePermissions.php', "smart_reports.generate");
assert_contains_v65('app/Support/PermissionRegistry.php', "smart_reports.view");
assert_contains_v65('app/Support/PermissionRegistry.php', "smart_reports.generate");
assert_contains_v65('app/Http/Controllers/SettingsController.php', "smart_reports_gemini_api_key");
assert_contains_v65('app/Http/Controllers/SettingsController.php', "Crypt::encryptString");
assert_contains_v65('resources/views/settings/edit.blade.php', "إعدادات التقارير الذكية Gemini");
assert_contains_v65('resources/views/layouts/app.blade.php', "smart-reports.index");
assert_contains_v65('resources/views/smart_reports/index.blade.php', "توليد التقرير الذكي");
assert_contains_v65('app/Services/GeminiSmartReportService.php', "https://generativelanguage.googleapis.com/v1beta/interactions");

if (file_exists(project_path('vendor/autoload.php'))) {
    require project_path('vendor/autoload.php');

    try {
        $app = require project_path('bootstrap/app.php');
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        if (Illuminate\Support\Facades\Schema::hasTable('smart_report_runs')) {
            ok('Database table smart_report_runs exists.');
        } else {
            fail_msg('Database table smart_report_runs does not exist. Run: php artisan migrate');
        }

        if (Illuminate\Support\Facades\Schema::hasTable('settings')) {
            $keys = [
                'smart_reports_enabled',
                'smart_reports_gemini_api_key',
                'smart_reports_gemini_model',
                'smart_reports_include_titles',
            ];

            foreach ($keys as $key) {
                $exists = Illuminate\Support\Facades\DB::table('settings')->where('key', $key)->exists();
                $exists ? ok("Setting exists: {$key}") : fail_msg("Missing setting: {$key}. Run: php artisan migrate");
            }
        }
    } catch (Throwable $exception) {
        echo "[WARN] Database checks skipped: {$exception->getMessage()}" . PHP_EOL;
    }
} else {
    echo "[WARN] vendor/autoload.php not found. Skipping Laravel database checks." . PHP_EOL;
}

if ($failed) {
    echo PHP_EOL . "Gemini Smart Reports V65 check failed." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "Gemini Smart Reports V65 check passed." . PHP_EOL;
echo "Do not commit a real Gemini API Key. The key is saved in the local database only." . PHP_EOL;

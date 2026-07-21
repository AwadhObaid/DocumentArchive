<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$printViewPath = resource_path('views/documents/print-reference.blade.php');
$settingsViewPath = resource_path('views/settings/edit.blade.php');
$documentControllerPath = base_path('app/Http/Controllers/DocumentController.php');
$settingsControllerPath = base_path('app/Http/Controllers/SettingsController.php');

$printView = is_file($printViewPath) ? file_get_contents($printViewPath) : false;
$settingsView = is_file($settingsViewPath) ? file_get_contents($settingsViewPath) : false;
$documentController = is_file($documentControllerPath)
    ? file_get_contents($documentControllerPath)
    : false;
$settingsController = is_file($settingsControllerPath)
    ? file_get_contents($settingsControllerPath)
    : false;

$checks = [
    'Print page exists' => is_string($printView),
    'Global settings marker exists' => is_string($printView)
        && str_contains($printView, 'PRINT_SETTINGS_LIVE_BINDING_V81_8'),
    'Top position reads global setting first' => is_string($printView)
        && str_contains($printView, '$topMm = (float) $setting('),
    'Left position reads global setting first' => is_string($printView)
        && str_contains($printView, '$leftMm = (float) $setting('),
    'Print page exposes global source marker' => is_string($printView)
        && str_contains($printView, 'data-print-settings-source="global"'),
    'Print response disables stale caching' => is_string($documentController)
        && str_contains($documentController, "'Cache-Control', 'no-store, no-cache"),
    'Old mass document update removed' => is_string($settingsController)
        && ! str_contains($settingsController, "Document::query()->update(["),
    'Misleading checkbox removed' => is_string($settingsView)
        && ! str_contains($settingsView, 'name="apply_to_existing_documents"'),
    'Live settings notice exists' => is_string($settingsView)
        && str_contains($settingsView, 'print-settings-live-binding-v81-8:start'),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

echo PHP_EOL;
echo 'Current database values:' . PHP_EOL;

foreach ([
    'print_department_title',
    'print_top_mm',
    'print_left_mm',
    'print_font_size_pt',
    'print_department_font_size_pt',
    'print_label_width_mm',
    'print_colon_width_mm',
    'print_value_width_mm',
    'print_column_gap_mm',
    'print_title_gap_mm',
    'print_row_gap_mm',
] as $key) {
    $value = \App\Models\Setting::getValue($key, '<missing>');
    echo " - {$key}: {$value}" . PHP_EOL;
}

if ($failed) {
    fwrite(STDERR, "Print settings live binding V81.8 check failed.\n");
    exit(1);
}

echo "Print settings live binding V81.8 check passed.\n";

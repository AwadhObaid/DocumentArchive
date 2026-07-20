<?php

declare(strict_types=1);

use App\Services\LegacyArchiveImportService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$servicePath = base_path('app/Services/LegacyArchiveImportService.php');
$viewPath = resource_path('views/legacy_archive_import/index.blade.php');

$service = is_file($servicePath) ? file_get_contents($servicePath) : false;
$view = is_file($viewPath) ? file_get_contents($viewPath) : false;

$checks = [
    'LegacyArchiveImportService exists' => is_string($service),
    'V81.5 existing-document marker exists' => is_string($service)
        && str_contains($service, 'LEGACY_EXISTING_ATTACHMENT_REPAIR_V81_5'),
    'Missing-attachment repair status exists' => is_string($service)
        && str_contains($service, "'attachment_repair_ready'"),
    'Existing attachment is physically checked' => is_string($service)
        && str_contains($service, 'documentHasUsableAttachment($existingDocument)'),
    'Repair execution method exists' => is_string($service)
        && str_contains($service, 'private function repairExistingDocumentAttachment'),
    'Exact Unicode path is retained' => is_string($service)
        && str_contains($service, 'LEGACY_UNICODE_SHARE_PATH_FIX_V81_4_RETAINED'),
    'Path variants helper exists' => is_string($service)
        && str_contains($service, 'private function pathVariants'),
    'V81.5 interface notice exists' => is_string($view)
        && str_contains($view, 'legacy-archive-existing-attachment-repair-v81-5:start'),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if (! $failed) {
    try {
        $instance = app(LegacyArchiveImportService::class);
        $reflection = new ReflectionClass($instance);
        $method = $reflection->getMethod('pathVariants');
        $method->setAccessible(true);

        $exact = "\\\\Server-1\\\u{200F}\u{200F}ارشيف 2026\\الصادر - 2026\\file.pdf";
        $cleaned = "\\\\Server-1\\ارشيف 2026\\الصادر - 2026\\file.pdf";
        $variants = $method->invoke($instance, $exact);

        $exactPresent = in_array($exact, $variants, true);
        $cleanedPresent = in_array($cleaned, $variants, true);

        echo ($exactPresent ? '[ OK ] ' : '[FAIL] ')
            . 'Exact UNC path with direction marks is preserved'
            . PHP_EOL;
        echo ($cleanedPresent ? '[ OK ] ' : '[FAIL] ')
            . 'Cleaned UNC fallback is generated'
            . PHP_EOL;

        $failed = $failed || ! $exactPresent || ! $cleanedPresent;
    } catch (Throwable $exception) {
        echo '[FAIL] Runtime path test: ' . $exception->getMessage() . PHP_EOL;
        $failed = true;
    }
}

if ($failed) {
    fwrite(STDERR, "Legacy existing-attachment repair V81.5 check failed.\n");
    exit(1);
}

echo "Legacy existing-attachment repair V81.5 check passed.\n";

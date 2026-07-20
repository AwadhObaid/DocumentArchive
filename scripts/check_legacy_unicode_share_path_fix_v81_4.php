<?php

declare(strict_types=1);

use App\Services\LegacyArchiveImportService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$target = base_path('app/Services/LegacyArchiveImportService.php');
$contents = is_file($target) ? file_get_contents($target) : false;

$checks = [
    'LegacyArchiveImportService exists' => is_string($contents),
    'V81.4 marker exists' => is_string($contents)
        && str_contains($contents, 'LEGACY_UNICODE_SHARE_PATH_FIX_V81_4'),
    'Exact path is preserved' => is_string($contents)
        && str_contains($contents, '$exact = $this->normalizePath($path, false);'),
    'Cleaned fallback exists' => is_string($contents)
        && str_contains($contents, '$cleaned = $this->normalizePath($path, true);'),
    'OriginalFilePath variants are tested' => is_string($contents)
        && str_contains($contents, "\$this->pathVariants(\$row['OriginalFilePath'] ?? null)"),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if (! $failed) {
    try {
        $service = app(LegacyArchiveImportService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('pathVariants');
        $method->setAccessible(true);

        $shareWithMarks = "\\\\Server-1\\\u{200F}\u{200F}ارشيف 2026\\الصادر - 2026\\file.pdf";
        $variants = $method->invoke($service, $shareWithMarks);

        $preserved = in_array($shareWithMarks, $variants, true);
        $cleaned = in_array(
            "\\\\Server-1\\ارشيف 2026\\الصادر - 2026\\file.pdf",
            $variants,
            true
        );

        echo ($preserved ? '[ OK ] ' : '[FAIL] ')
            . 'Unicode direction marks are preserved in the exact UNC candidate'
            . PHP_EOL;
        echo ($cleaned ? '[ OK ] ' : '[FAIL] ')
            . 'A cleaned UNC fallback is also generated'
            . PHP_EOL;

        $failed = $failed || ! $preserved || ! $cleaned;
    } catch (Throwable $exception) {
        echo '[FAIL] Runtime path-variant test: ' . $exception->getMessage() . PHP_EOL;
        $failed = true;
    }
}

if ($failed) {
    fwrite(STDERR, "Legacy Unicode network-share path V81.4 check failed.\n");
    exit(1);
}

echo "Legacy Unicode network-share path V81.4 check passed.\n";

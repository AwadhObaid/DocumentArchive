<?php

declare(strict_types=1);

use App\Services\LegacyArchiveCsvReader;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$target = base_path('app/Services/LegacyArchiveCsvReader.php');
$contents = is_file($target) ? file_get_contents($target) : false;

$checks = [
    'LegacyArchiveCsvReader exists' => is_string($contents),
    'V81.6 marker exists' => is_string($contents)
        && str_contains($contents, 'LEGACY_CSV_PATH_MARKS_FIX_V81_6'),
    'Path columns are declared' => is_string($contents)
        && str_contains($contents, "'OriginalFilePath'"),
    'normalizeCell accepts preservation flag' => is_string($contents)
        && str_contains($contents, 'bool $preserveDirectionalMarks = false'),
    'Directional marks are conditionally removed' => is_string($contents)
        && str_contains($contents, 'if (! $preserveDirectionalMarks)'),
    'Semicolon delimiter remains supported' => is_string($contents)
        && str_contains($contents, '$candidates = [",", "\t", ";"];'),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if (! $failed) {
    try {
        $reader = app(LegacyArchiveCsvReader::class);
        $reflection = new ReflectionClass($reader);
        $method = $reflection->getMethod('normalizeCell');
        $method->setAccessible(true);

        $path = "\\\\Server-1\\\u{200F}\u{200F}ارشيف 2026\\الصادر - 2026\\file.pdf";
        $preserved = $method->invoke($reader, $path, true);
        $cleaned = $method->invoke($reader, $path, false);

        $preserveOk = $preserved === $path;
        $cleanOk = $cleaned === "\\\\Server-1\\ارشيف 2026\\الصادر - 2026\\file.pdf";

        echo ($preserveOk ? '[ OK ] ' : '[FAIL] ')
            . 'Path direction marks are preserved'
            . PHP_EOL;
        echo ($cleanOk ? '[ OK ] ' : '[FAIL] ')
            . 'Non-path text can still be cleaned'
            . PHP_EOL;

        $failed = $failed || ! $preserveOk || ! $cleanOk;
    } catch (Throwable $exception) {
        echo '[FAIL] Runtime normalization test: '
            . $exception->getMessage()
            . PHP_EOL;
        $failed = true;
    }
}

if ($failed) {
    fwrite(STDERR, "Legacy CSV path-marks fix V81.6 check failed.\n");
    exit(1);
}

echo "Legacy CSV path-marks fix V81.6 check passed.\n";

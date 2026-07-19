<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$target = base_path('app/Services/LegacyArchiveImportService.php');
$contents = is_file($target) ? file_get_contents($target) : false;

$checks = [
    'LegacyArchiveImportService exists' => $contents !== false,
    'Canonical legacy start constant exists' => is_string($contents)
        && str_contains($contents, 'private const LEGACY_REFERENCE_START_NUMBER = 251230000;'),
    'Sequence uses canonical legacy start' => is_string($contents)
        && str_contains($contents, '$sequence = $number - self::LEGACY_REFERENCE_START_NUMBER;'),
    'Counter sync uses canonical legacy start' => is_string($contents)
        && str_contains($contents, '$startNumber = self::LEGACY_REFERENCE_START_NUMBER;'),
    'Counter start is normalized' => is_string($contents)
        && str_contains($contents, '$safeStart = $startNumber;'),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if ($failed) {
    fwrite(STDERR, "Legacy archive reference-sequence V81.2 check failed.\n");
    exit(1);
}

echo "Legacy archive reference-sequence V81.2 check passed.\n";

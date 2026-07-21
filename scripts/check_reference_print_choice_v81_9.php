<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$showPath = resource_path('views/documents/show.blade.php');
$printPath = resource_path('views/documents/print-reference.blade.php');

$show = is_file($showPath) ? file_get_contents($showPath) : false;
$print = is_file($printPath) ? file_get_contents($printPath) : false;

$checks = [
    'Document show page exists' => is_string($show),
    'Print page exists' => is_string($print),

    'V81.9 print choice marker exists' => is_string($show)
        && str_contains($show, 'REFERENCE_PRINT_CHOICE_V81_9'),

    'Direct-print option exists' => is_string($show)
        && str_contains($show, 'data-reference-print-mode="direct"')
        && str_contains($show, '?mode=direct'),

    'Preview option exists' => is_string($show)
        && str_contains($show, 'data-reference-print-mode="preview"')
        && str_contains($show, '?mode=preview'),

    'Remember-choice option exists' => is_string($show)
        && str_contains($show, 'documentArchive.referencePrint.remember'),

    'V81.9 direct-mode marker exists' => is_string($print)
        && str_contains($print, 'REFERENCE_PRINT_DIRECT_MODE_V81_9'),

    'Direct mode reads query string' => is_string($print)
        && str_contains($print, "request()->query('mode') === 'direct'"),

    'Direct mode automatically invokes browser print' => is_string($print)
        && str_contains($print, 'window.print();'),

    'Direct mode closes popup after printing' => is_string($print)
        && str_contains($print, "window.addEventListener('afterprint'"),

    'Preview page retains manual print button' => is_string($print)
        && str_contains($print, 'onclick="window.print()"'),

    'V81.8 live print settings remain intact' => is_string($print)
        && str_contains($print, 'PRINT_SETTINGS_LIVE_BINDING_V81_8'),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if ($failed) {
    fwrite(STDERR, "Reference print choice V81.9 check failed.\n");
    exit(1);
}

echo "Reference print choice V81.9 check passed.\n";

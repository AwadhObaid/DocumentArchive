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

    'V81.9 base print choice remains installed' => is_string($show)
        && str_contains($show, 'REFERENCE_PRINT_CHOICE_V81_9'),

    'V81.9.1 return marker exists on book page' => is_string($show)
        && str_contains($show, 'REFERENCE_PRINT_RETURN_V81_9_1'),

    'Direct print opens from JavaScript' => is_string($show)
        && str_contains($show, "window.open(")
        && str_contains($show, 'documentArchiveReferencePrintWindow'),

    'Primary button intercepts direct mode' => is_string($show)
        && str_contains($show, "primary.addEventListener('click'"),

    'Print completion message is handled' => is_string($show)
        && str_contains(
            $show,
            "documentArchive.referencePrint.finished"
        ),

    'V81.9.1 return marker exists on print page' => is_string($print)
        && str_contains($print, 'REFERENCE_PRINT_RETURN_V81_9_1'),

    'Print page attempts automatic close' => is_string($print)
        && str_contains($print, 'window.close();'),

    'Print page has return-to-book fallback' => is_string($print)
        && str_contains($print, 'window.location.replace(returnToBookUrl);'),

    'Return target is the current book page' => is_string($print)
        && str_contains($print, "url('/documents/' . \$docId)"),

    'V81.8 live settings remain intact' => is_string($print)
        && str_contains($print, 'PRINT_SETTINGS_LIVE_BINDING_V81_8'),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if ($failed) {
    fwrite(STDERR, "Reference print return V81.9.1 check failed.\n");
    exit(1);
}

echo "Reference print return V81.9.1 check passed.\n";

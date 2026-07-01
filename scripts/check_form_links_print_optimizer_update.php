<?php

$root = dirname(__DIR__);
$errors = [];

$requiredFiles = [
    'app/Http/Controllers/FormLinkController.php',
    'app/Http/Middleware/ApplyRoutePermissions.php',
    'resources/views/form-links/index.blade.php',
    'resources/views/form-links/print.blade.php',
    'routes/web.php',
];

foreach ($requiredFiles as $file) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (! is_file($path)) {
        $errors[] = "Missing required file: {$file}";
    }
}

$checks = [
    'routes/web.php' => [
        "Route::get('/form-links/{formLink}/print'" => 'Missing optimized print route.',
        "->name('form-links.print')" => 'Missing form-links.print route name.',
    ],
    'app/Http/Controllers/FormLinkController.php' => [
        'public function print(Request $request, FormLink $formLink)' => 'Missing print controller action.',
        'preparePrintableHtml' => 'Missing printable HTML optimizer method.',
        'decodeRemoteHtml' => 'Missing remote HTML decoding method.',
        'DocumentArchivePrintOptimizer/1.0' => 'Missing custom print optimizer user agent.',
        'size: A4 portrait' => 'Missing A4 print CSS rule in optimizer.',
    ],
    'resources/views/form-links/index.blade.php' => [
        'route(\'form-links.print\', $formLink)' => 'Missing optimized print button in forms index.',
        'طباعة محسّنة' => 'Missing Arabic optimized print label.',
    ],
    'resources/views/form-links/print.blade.php' => [
        'printPreparedFrame' => 'Missing print frame JavaScript function.',
        'التحجيم' => 'Missing print scale controls.',
        'رجوع لإدارة النماذج' => 'Missing back link label.',
    ],
    'app/Http/Middleware/ApplyRoutePermissions.php' => [
        "'form-links.print' => 'form_links.view'" => 'Missing permission mapping for optimized print route.',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (! is_file($path)) {
        continue;
    }

    $contents = file_get_contents($path);
    foreach ($needles as $needle => $message) {
        if (! str_contains($contents, $needle)) {
            $errors[] = $message . " ({$file})";
        }
    }
}

if (! empty($errors)) {
    echo "Form links print optimizer update check failed:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "Form links print optimizer update check passed.\n";
echo "Optimized print route: form-links.print\n";
echo "Open any form card and use: طباعة محسّنة\n";

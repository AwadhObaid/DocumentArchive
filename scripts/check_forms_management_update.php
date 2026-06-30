<?php

$root = realpath(__DIR__ . '/..');

if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$checks = [
    'Controller exists' => 'app/Http/Controllers/FormLinkController.php',
    'Model exists' => 'app/Models/FormLink.php',
    'Migration exists' => 'database/migrations/2026_06_30_131900_create_form_links_table.php',
    'Index view exists' => 'resources/views/form-links/index.blade.php',
    'Create view exists' => 'resources/views/form-links/create.blade.php',
    'Edit view exists' => 'resources/views/form-links/edit.blade.php',
    'Form partial exists' => 'resources/views/form-links/_form.blade.php',
];

$failed = false;

foreach ($checks as $label => $relativePath) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($path)) {
        echo "[OK] {$label}: {$relativePath}\n";
    } else {
        echo "[FAIL] {$label}: {$relativePath}\n";
        $failed = true;
    }
}

$contentChecks = [
    'routes/web.php' => [
        "FormLinkController" => 'Controller import is registered',
        "Route::resource('form-links'" => 'Form links resource route exists',
    ],
    'resources/views/layouts/app.blade.php' => [
        "route('form-links.index')" => 'Sidebar link route exists',
        "إدارة النماذج" => 'Sidebar label exists',
    ],
    'app/Support/PermissionRegistry.php' => [
        "form_links.view" => 'View permission exists',
        "form_links.manage" => 'Manage permission exists',
    ],
    'app/Http/Middleware/ApplyRoutePermissions.php' => [
        "form-links.index" => 'Index permission mapping exists',
        "form-links.destroy" => 'Destroy permission mapping exists',
    ],
];

foreach ($contentChecks as $relativePath => $needles) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $content = is_file($path) ? file_get_contents($path) : '';

    foreach ($needles as $needle => $label) {
        if (str_contains((string) $content, $needle)) {
            echo "[OK] {$label}\n";
        } else {
            echo "[FAIL] {$label}\n";
            $failed = true;
        }
    }
}

$phpFiles = [
    'app/Http/Controllers/FormLinkController.php',
    'app/Models/FormLink.php',
    'app/Support/PermissionRegistry.php',
    'app/Http/Middleware/ApplyRoutePermissions.php',
    'database/migrations/2026_06_30_131900_create_form_links_table.php',
    'routes/web.php',
];

foreach ($phpFiles as $relativePath) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (! is_file($path)) {
        continue;
    }

    $command = PHP_BINARY . ' -l ' . escapeshellarg($path);
    exec($command, $output, $status);

    if ($status === 0) {
        echo "[OK] PHP syntax: {$relativePath}\n";
    } else {
        echo "[FAIL] PHP syntax: {$relativePath}\n";
        echo implode("\n", $output) . "\n";
        $failed = true;
    }
}

if ($failed) {
    echo "\nForms management update check failed.\n";
    exit(1);
}

echo "\nForms management update check passed.\n";

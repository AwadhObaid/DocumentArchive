<?php

$root = realpath(__DIR__ . '/..');

if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$checks = [
    'Seed migration exists' => 'database/migrations/2026_06_30_132100_seed_ministry_form_links.php',
    'FormLinksSeeder exists' => 'database/seeders/FormLinksSeeder.php',
    'DatabaseSeeder exists' => 'database/seeders/DatabaseSeeder.php',
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
    'database/migrations/2026_06_30_132100_seed_ministry_form_links.php' => [
        '231000-40.htm' => 'Attendance form URL exists',
        '231000-06.htm' => 'Resignation/retirement form URL exists',
        '230000-13.htm' => 'Supervisory nomination form URL exists',
        '231000-27.htm' => 'Internal transfer/delegation form URL exists',
        '230000-12.htm' => 'Leave and return declaration form URL exists',
        '230000-37.htm' => 'Transaction request form URL exists',
        '231000-12.htm' => 'ID request form URL exists',
        '230000-43.htm' => 'Allowances form URL exists',
        '231000-08%20.htm' => 'Exit permit form URL exists',
        '240000-23.htm' => 'Custody clearance form URL exists',
        '232000-08.htm' => 'Special contracts clearance form URL exists',
        '230000-41.htm' => 'Employee treatment form URL exists',
        '231000-26.htm' => 'Emergency leave notice form URL exists',
    ],
    'database/seeders/DatabaseSeeder.php' => [
        'FormLinksSeeder::class' => 'DatabaseSeeder calls FormLinksSeeder',
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
    'database/migrations/2026_06_30_132100_seed_ministry_form_links.php',
    'database/seeders/FormLinksSeeder.php',
    'database/seeders/DatabaseSeeder.php',
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

$migrationPath = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '2026_06_30_132100_seed_ministry_form_links.php';
$migrationContent = is_file($migrationPath) ? file_get_contents($migrationPath) : '';
$linkCount = substr_count((string) $migrationContent, "'url' => 'https://www.mod.gov.kw/resources/skins/EformImages/000000/");

if ($linkCount === 13) {
    echo "[OK] Unique seeded links count: 13\n";
} else {
    echo "[FAIL] Unique seeded links count expected 13, found {$linkCount}\n";
    $failed = true;
}

if ($failed) {
    echo "\nForm links seed update check failed.\n";
    exit(1);
}

echo "\nForm links seed update check passed.\n";

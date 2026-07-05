<?php

$root = dirname(__DIR__);
$file = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'departments' . DIRECTORY_SEPARATOR . 'index.blade.php';

if (! file_exists($file)) {
    fwrite(STDERR, "ERROR: resources/views/departments/index.blade.php not found\n");
    exit(1);
}

$content = file_get_contents($file);
$checks = [
    'data-departments-scroll-v12',
    'departments-table-scroll-fix-v12:start',
    'departments-table-scroll-fix-v12:js:start',
    'forceDepartmentsTableScroll',
];

$ok = true;
foreach ($checks as $needle) {
    if (strpos($content, $needle) === false) {
        echo "MISSING: {$needle}\n";
        $ok = false;
    } else {
        echo "OK: {$needle}\n";
    }
}

echo $ok ? "RESULT: OK - Departments table scroll V12 is installed.\n" : "RESULT: FAILED\n";
exit($ok ? 0 : 1);

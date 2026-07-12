<?php

$root = dirname(__DIR__);
$layoutPath = $root . '/resources/views/layouts/app.blade.php';
$cssPath = $root . '/public/css/book-subjects-pagination-fix-v54.css';
$ok = true;

function pass($message) { echo "[OK] {$message}\n"; }
function fail_check($message) { global $ok; $ok = false; echo "[FAIL] {$message}\n"; }

if (file_exists($cssPath)) {
    pass('CSS file exists');
    $css = file_get_contents($cssPath);
    foreach ([
        'DocumentArchive Book Subjects Pagination Fix V54',
        'nav[role="navigation"] svg',
        'width: 16px !important',
        'height: 16px !important',
        'white-space: nowrap !important',
        'html[data-theme="dark"]',
    ] as $needle) {
        str_contains($css, $needle) ? pass("CSS contains: {$needle}") : fail_check("CSS missing: {$needle}");
    }
} else {
    fail_check('CSS file missing');
}

if (file_exists($layoutPath)) {
    pass('app.blade.php exists');
    $layout = file_get_contents($layoutPath);
    foreach ([
        'book-subjects-pagination-fix-v54:start',
        "css/book-subjects-pagination-fix-v54.css",
        'book-subjects-pagination-fix-v54:end',
    ] as $needle) {
        str_contains($layout, $needle) ? pass("app.blade.php contains: {$needle}") : fail_check("app.blade.php missing: {$needle}");
    }

    $count = substr_count($layout, "css/book-subjects-pagination-fix-v54.css");
    $count === 1 ? pass('V54 CSS include appears exactly once') : fail_check("V54 CSS include count is {$count}, expected 1");
} else {
    fail_check('app.blade.php missing');
}

if ($ok) {
    echo "\nBook subjects pagination fix V54 check passed.\n";
    exit(0);
}

echo "\nBook subjects pagination fix V54 check failed.\n";
exit(1);

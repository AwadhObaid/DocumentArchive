<?php

$basePath = dirname(__DIR__);
$layoutPath = $basePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';
$cssPath = $basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'trash-layout-fix-v52.css';
$trashViewPath = $basePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'trash.blade.php';

$errors = 0;

$check = static function (bool $condition, string $message) use (&$errors): void {
    if ($condition) {
        echo "[OK] {$message}" . PHP_EOL;
    } else {
        echo "[FAIL] {$message}" . PHP_EOL;
        $errors++;
    }
};

$check(is_file($cssPath), "File exists: {$cssPath}");
$check(is_file($layoutPath), "File exists: {$layoutPath}");
$check(is_file($trashViewPath), "File exists: {$trashViewPath}");

$css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';
$layout = is_file($layoutPath) ? (string) file_get_contents($layoutPath) : '';
$trashView = is_file($trashViewPath) ? (string) file_get_contents($trashViewPath) : '';

foreach ([
    'DocumentArchive Trash Layout Fix V52',
    '.trash-page',
    '.trash-overview-grid',
    '.trash-overview-card',
    '.trash-section-header',
    '.trash-table-wrap',
    '.trash-row-actions',
] as $needle) {
    $check(strpos($css, $needle) !== false, "trash-layout-fix-v52.css contains: {$needle}");
}

foreach ([
    'trash-layout-fix-v52:start',
    "css/trash-layout-fix-v52.css",
    'trash-layout-fix-v52:end',
] as $needle) {
    $check(strpos($layout, $needle) !== false, "app.blade.php contains: {$needle}");
}

$check(substr_count($layout, 'css/trash-layout-fix-v52.css') === 1, 'V52 CSS include appears exactly once');

foreach ([
    'class="trash-page"',
    'trash-page-title',
    'trash-overview-grid',
    'trash-overview-card',
    'trash-section-card',
    'trash-table-wrap',
    'trash-table',
] as $needle) {
    $check(strpos($trashView, $needle) !== false, "trash.blade.php contains: {$needle}");
}

$check(strpos($trashView, 'class="stat-card"') === false, 'trash.blade.php no longer uses the generic stat-card layout');

if ($errors > 0) {
    echo PHP_EOL . "Trash layout fix V52 check failed." . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "Trash layout fix V52 check passed." . PHP_EOL;

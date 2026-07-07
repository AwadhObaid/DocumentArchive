<?php

$root = dirname(__DIR__);

$requiredFiles = [
    'app/Models/AttachmentTextIndex.php',
    'resources/views/pdf-search/index.blade.php',
];

$errors = [];
$warnings = [];

foreach ($requiredFiles as $file) {
    if (! is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $errors[] = "Missing file: {$file}";
    }
}

$viewPath = $root . DIRECTORY_SEPARATOR . 'resources/views/pdf-search/index.blade.php';
$modelPath = $root . DIRECTORY_SEPARATOR . 'app/Models/AttachmentTextIndex.php';

if (is_file($viewPath)) {
    $view = file_get_contents($viewPath);
    foreach ([
        'استخراج النص من PDF',
        'تحويل PDF إلى صور',
        'التعرف الضوئي على النصوص',
        'طريقة المعالجة',
        'تشغيل التعرف الضوئي',
    ] as $needle) {
        if (! str_contains($view, $needle)) {
            $errors[] = "Missing Arabic UI phrase in pdf-search view: {$needle}";
        }
    }
}

if (is_file($modelPath)) {
    $model = file_get_contents($modelPath);
    foreach ([
        "protected \$table = 'attachment_text_indexes'",
        'getExtractorNameAttribute',
        'friendlyErrorMessage',
        'يحتاج تعرفًا ضوئيًا',
    ] as $needle) {
        if (! str_contains($model, $needle)) {
            $errors[] = "Missing model update marker: {$needle}";
        }
    }
}

try {
    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    if (! Illuminate\Support\Facades\Route::has('pdf-search.index')) {
        $errors[] = 'Missing route: pdf-search.index';
    }

    if (! Illuminate\Support\Facades\Schema::hasTable('attachment_text_indexes')) {
        $warnings[] = 'Table attachment_text_indexes is not created yet. Run: php artisan migrate';
    }
} catch (Throwable $e) {
    $warnings[] = 'Laravel/database inspection skipped: ' . $e->getMessage();
}

if ($errors) {
    echo "PDF/OCR Arabic UI V30 check FAILED\n";
    foreach ($errors as $error) {
        echo "[ERROR] {$error}\n";
    }
    foreach ($warnings as $warning) {
        echo "[WARN] {$warning}\n";
    }
    exit(1);
}

echo "PDF/OCR Arabic UI V30 check OK\n";
foreach ($warnings as $warning) {
    echo "[WARN] {$warning}\n";
}
echo "Page: /pdf-search\n";

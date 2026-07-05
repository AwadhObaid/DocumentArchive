<?php

$root = realpath(__DIR__ . '/..');

if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$checks = [
    'BookSubject model' => 'app/Models/BookSubject.php',
    'BookSubject controller' => 'app/Http/Controllers/BookSubjectController.php',
    'Book subjects migration' => 'database/migrations/2026_07_02_131000_create_book_subjects_and_add_to_documents_table.php',
    'Memo model' => 'app/Models/Memo.php',
    'MemoAttachment model' => 'app/Models/MemoAttachment.php',
    'MemoCounter model' => 'app/Models/MemoCounter.php',
    'Memo controller' => 'app/Http/Controllers/MemoController.php',
    'Memo number generator' => 'app/Services/MemoNumberGenerator.php',
    'Memos migration' => 'database/migrations/2026_07_02_131100_create_memos_module_tables.php',
    'Book subjects views' => 'resources/views/book-subjects/index.blade.php',
    'Memos views' => 'resources/views/memos/index.blade.php',
    'Memo attachment preview view' => 'resources/views/memos/attachment-preview.blade.php',
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
        'BookSubjectController' => 'Routes import BookSubjectController',
        'MemoController' => 'Routes import MemoController',
        "Route::resource('book-subjects'" => 'Book subjects resource route exists',
        "Route::resource('memos'" => 'Memos resource route exists',
        'memos.attachments.preview' => 'Memo attachment preview route exists',
        'memos.attachments.data' => 'Memo attachment data route exists',
        'memos.attachments.inline' => 'Memo attachment inline route exists',
        'memos.attachments.download' => 'Memo attachment download route exists',
    ],
    'app/Http/Controllers/DocumentController.php' => [
        'BookSubject::query()' => 'Documents controller loads book subjects',
        'book_subject_id' => 'Documents controller validates book_subject_id',
    ],
    'app/Support/PermissionRegistry.php' => [
        'book_subjects.manage' => 'Book subjects permission exists',
        'memos.view' => 'Memos view permission exists',
        'memos.create' => 'Memos create permission exists',
    ],
    'app/Services/MemoNumberGenerator.php' => [
        '2600001' => 'Memo numbering starts at 2600001',
    ],
    'app/Http/Middleware/ApplyRoutePermissions.php' => [
        'memos.attachments.preview' => 'Memo attachment preview permission mapping exists',
        'memos.attachments.data' => 'Memo attachment data permission mapping exists',
    ],
    'resources/views/memos/show.blade.php' => [
        'مرفقات المذكرة' => 'Memo show displays attachments table',
        'حالة التخزين' => 'Memo show displays storage status like documents',
        'memos.attachments.preview' => 'Memo show links to attachment preview page',
    ],
    'resources/views/memos/attachment-preview.blade.php' => [
        'attachment-preview-box-clean' => 'Memo preview uses the same attachment preview layout as documents',
        'memos.attachments.data' => 'Memo preview loads attachment data through JSON endpoint',
        'طباعة المرفق' => 'Memo preview supports the same print action',
    ],
    'app/Http/Controllers/MemoController.php' => [
        'function attachmentData' => 'Memo controller provides attachment data endpoint',
        'base64_encode($binary)' => 'Memo attachment data returns base64 preview payload',
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
    'app/Models/BookSubject.php',
    'app/Models/Memo.php',
    'app/Models/MemoAttachment.php',
    'app/Models/MemoCounter.php',
    'app/Http/Controllers/BookSubjectController.php',
    'app/Http/Controllers/MemoController.php',
    'app/Services/MemoNumberGenerator.php',
    'database/migrations/2026_07_02_131000_create_book_subjects_and_add_to_documents_table.php',
    'database/migrations/2026_07_02_131100_create_memos_module_tables.php',
    'routes/web.php',
    'app/Support/PermissionRegistry.php',
    'app/Http/Middleware/ApplyRoutePermissions.php',
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
    echo "\nMemos and book subjects update check failed.\n";
    exit(1);
}

echo "\nMemos and book subjects update check passed.\n";

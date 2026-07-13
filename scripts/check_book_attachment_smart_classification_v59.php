<?php

$root = dirname(__DIR__);
$failed = false;

function ok(string $message): void { echo "[OK] {$message}\n"; }
function fail(string $message): void { global $failed; $failed = true; echo "[FAIL] {$message}\n"; }
function file_contains(string $path, string $needle, string $label): void
{
    if (! file_exists($path)) {
        fail("File missing: {$path}");
        return;
    }

    $content = file_get_contents($path);
    if (str_contains($content, $needle)) {
        ok("{$label} contains: {$needle}");
    } else {
        fail("{$label} missing: {$needle}");
    }
}

$files = [
    'app/Http/Controllers/DocumentController.php',
    'app/Models/Document.php',
    'app/Models/DocumentAttachment.php',
    'app/Models/BookAttachmentCompany.php',
    'app/Models/BookAttachmentOperation.php',
    'app/Services/BookAttachmentSmartPathService.php',
    'database/migrations/2026_07_13_070000_add_book_attachment_smart_classification_v59.php',
    'resources/views/documents/create.blade.php',
    'resources/views/documents/edit.blade.php',
    'resources/views/documents/show.blade.php',
];

foreach ($files as $relative) {
    $path = $root . '/' . $relative;
    if (file_exists($path)) {
        ok("File exists: {$relative}");
    } else {
        fail("File missing: {$relative}");
    }
}

$phpFiles = array_filter($files, fn ($file) => str_ends_with($file, '.php'));
foreach ($phpFiles as $relative) {
    $path = $root . '/' . $relative;
    if (! file_exists($path)) {
        continue;
    }

    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $output, $code);
    if ($code === 0) {
        ok("PHP syntax valid: {$relative}");
    } else {
        fail("PHP syntax invalid: {$relative}\n" . implode("\n", $output));
    }
}

file_contains($root . '/app/Http/Controllers/DocumentController.php', 'BookAttachmentSmartPathService', 'DocumentController.php');
file_contains($root . '/app/Http/Controllers/DocumentController.php', 'attachment_company_name', 'DocumentController.php');
file_contains($root . '/app/Http/Controllers/DocumentController.php', 'classification_folder', 'DocumentController.php');
file_contains($root . '/app/Services/BookAttachmentSmartPathService.php', "Books/", 'BookAttachmentSmartPathService.php');
file_contains($root . '/app/Services/BookAttachmentSmartPathService.php', 'sanitizeFolderName', 'BookAttachmentSmartPathService.php');
file_contains($root . '/app/Models/Document.php', "'attachment_company_name'", 'Document.php');
file_contains($root . '/app/Models/DocumentAttachment.php', "'classification_folder'", 'DocumentAttachment.php');
file_contains($root . '/database/migrations/2026_07_13_070000_add_book_attachment_smart_classification_v59.php', 'book_attachment_companies', 'V59 migration');
file_contains($root . '/database/migrations/2026_07_13_070000_add_book_attachment_smart_classification_v59.php', 'book_attachment_operations', 'V59 migration');
file_contains($root . '/resources/views/documents/create.blade.php', 'شركة / جهة حفظ المرفقات', 'documents/create.blade.php');
file_contains($root . '/resources/views/documents/create.blade.php', 'نوع عملية حفظ المرفقات', 'documents/create.blade.php');
file_contains($root . '/resources/views/documents/edit.blade.php', 'شركة / جهة حفظ المرفقات', 'documents/edit.blade.php');
file_contains($root . '/resources/views/documents/show.blade.php', 'مسار الحفظ', 'documents/show.blade.php');

$memoController = $root . '/app/Http/Controllers/MemoController.php';
if (file_exists($memoController)) {
    $memo = file_get_contents($memoController);
    if (str_contains($memo, 'BookAttachmentSmartPathService') || str_contains($memo, 'attachment_company_name')) {
        fail('MemoController.php should not be changed for book attachment classification.');
    } else {
        ok('MemoController.php is not touched by V59 classification logic.');
    }
} else {
    ok('MemoController.php not included in this review pack.');
}

if ($failed) {
    echo "\nBook attachment smart classification V59 check failed.\n";
    exit(1);
}

echo "\nBook attachment smart classification V59 check passed.\n";

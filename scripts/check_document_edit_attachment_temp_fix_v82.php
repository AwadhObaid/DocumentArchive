<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

$projectRoot = dirname(__DIR__);
$controllerPath = $projectRoot . DIRECTORY_SEPARATOR . 'app'
    . DIRECTORY_SEPARATOR . 'Http'
    . DIRECTORY_SEPARATOR . 'Controllers'
    . DIRECTORY_SEPARATOR . 'DocumentController.php';

if (! is_file($controllerPath)) {
    fwrite(STDERR, "[FAIL] DocumentController.php was not found.\n");
    exit(1);
}

$source = file_get_contents($controllerPath);
if ($source === false) {
    fwrite(STDERR, "[FAIL] DocumentController.php could not be read.\n");
    exit(1);
}

$checks = [
    'DOCUMENT_ATTACHMENT_METADATA_BEFORE_MOVE_V82_START',
    '$originalName = $file->getClientOriginalName();',
    '$fileSize = $file->getSize();',
    '$mimeType = $file->getMimeType();',
    '$storedFile = $pathService->storeUploadedFile($document, $file, $versionNo);',
    '$absolutePath = $storedFile[\'absolute_path\'] ?? null;',
];

$failed = false;

foreach ($checks as $check) {
    if (! str_contains($source, $check)) {
        fwrite(STDERR, "[FAIL] Missing expected code: {$check}\n");
        $failed = true;
    }
}

$metadataMarker = strpos($source, 'DOCUMENT_ATTACHMENT_METADATA_BEFORE_MOVE_V82_START');
$storeCall = strpos($source, '$storedFile = $pathService->storeUploadedFile($document, $file, $versionNo);');
$mimeRead = strpos($source, '$mimeType = $file->getMimeType();');
$sizeRead = strpos($source, '$fileSize = $file->getSize();');

if ($metadataMarker === false || $storeCall === false || $mimeRead === false || $sizeRead === false
    || $mimeRead > $storeCall || $sizeRead > $storeCall) {
    fwrite(STDERR, "[FAIL] Upload metadata is not captured before the file move.\n");
    $failed = true;
}

if ($failed) {
    exit(1);
}

require $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$app = require $projectRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
$app->make(Kernel::class)->bootstrap();

if (! class_exists(\App\Http\Controllers\DocumentController::class)) {
    fwrite(STDERR, "[FAIL] DocumentController class could not be autoloaded.\n");
    exit(1);
}

echo "Document edit attachment temporary-file fix V82 check passed.\n";

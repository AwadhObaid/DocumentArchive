<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$attachmentModel = base_path('app/Models/DocumentAttachment.php');
$controller = base_path(
    'app/Http/Controllers/DocumentAttachmentManagementController.php'
);
$showView = resource_path('views/documents/show.blade.php');
$historyView = resource_path(
    'views/documents/attachment-history.blade.php'
);
$routes = base_path('routes/web.php');
$permissions = base_path(
    'app/Http/Middleware/ApplyRoutePermissions.php'
);

$attachmentText = is_file($attachmentModel)
    ? file_get_contents($attachmentModel)
    : false;
$controllerText = is_file($controller)
    ? file_get_contents($controller)
    : false;
$showText = is_file($showView)
    ? file_get_contents($showView)
    : false;
$historyText = is_file($historyView)
    ? file_get_contents($historyView)
    : false;
$routeText = is_file($routes)
    ? file_get_contents($routes)
    : false;
$permissionText = is_file($permissions)
    ? file_get_contents($permissions)
    : false;

$checks = [
    'DocumentAttachment model exists' => is_string($attachmentText),
    'SoftDeletes enabled' => is_string($attachmentText)
        && str_contains($attachmentText, 'use SoftDeletes;'),
    'Replacement relationships exist' => is_string($attachmentText)
        && str_contains($attachmentText, 'replacedByAttachment'),
    'Management controller exists' => is_string($controllerText),
    'Replacement action exists' => is_string($controllerText)
        && str_contains($controllerText, 'public function replace'),
    'Soft delete action exists' => is_string($controllerText)
        && str_contains($controllerText, 'public function destroy'),
    'Restore action exists' => is_string($controllerText)
        && str_contains($controllerText, 'public function restore'),
    'Physical file is preserved on soft delete' => is_string($controllerText)
        && str_contains($controllerText, "'physical_file_deleted' => false"),
    'Show page buttons exist' => is_string($showText)
        && str_contains($showText, 'attachment-management-v83:start')
        && str_contains($showText, 'اعتماد الاستبدال'),
    'History page exists' => is_string($historyText)
        && str_contains($historyText, 'سجل مرفقات الكتاب'),
    'Routes exist' => is_string($routeText)
        && str_contains($routeText, 'attachment-management-v83:start')
        && str_contains($routeText, "name('attachments.replace')"),
    'Permissions are protected' => is_string($permissionText)
        && str_contains(
            $permissionText,
            "'attachments.replace' => 'documents.edit'"
        ),
    'Migration applied: deleted_at' => Schema::hasColumn(
        'document_attachments',
        'deleted_at'
    ),
    'Migration applied: deleted_by' => Schema::hasColumn(
        'document_attachments',
        'deleted_by'
    ),
    'Migration applied: replaced_by_attachment_id' => Schema::hasColumn(
        'document_attachments',
        'replaced_by_attachment_id'
    ),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if ($failed) {
    fwrite(STDERR, "Attachment management V83 check failed.\n");
    exit(1);
}

echo "Attachment management V83 check passed.\n";

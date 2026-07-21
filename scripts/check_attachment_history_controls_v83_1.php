<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$controllerPath = base_path(
    'app/Http/Controllers/DocumentAttachmentManagementController.php'
);
$historyPath = resource_path(
    'views/documents/attachment-history.blade.php'
);
$routesPath = base_path('routes/web.php');
$permissionsPath = base_path(
    'app/Http/Middleware/ApplyRoutePermissions.php'
);
$activityPath = base_path('app/Models/ActivityLog.php');

$controller = is_file($controllerPath)
    ? file_get_contents($controllerPath)
    : false;
$history = is_file($historyPath)
    ? file_get_contents($historyPath)
    : false;
$routes = is_file($routesPath)
    ? file_get_contents($routesPath)
    : false;
$permissions = is_file($permissionsPath)
    ? file_get_contents($permissionsPath)
    : false;
$activity = is_file($activityPath)
    ? file_get_contents($activityPath)
    : false;

$checks = [
    'V83 management controller exists' => is_string($controller),
    'Archived preview action exists' => is_string($controller)
        && str_contains($controller, 'public function archivedPreview'),
    'Permanent delete action exists' => is_string($controller)
        && str_contains($controller, 'public function forceDelete'),
    'Permanent delete requires documents.force_delete' => is_string($controller)
        && str_contains(
            $controller,
            "hasPermission('documents.force_delete')"
        ),
    'Permanent delete requires typed confirmation' => is_string($controller)
        && str_contains($controller, "'in:حذف نهائي'"),
    'Shared physical file protection exists' => is_string($controller)
        && str_contains($controller, '$sameFileUsedElsewhere'),
    'Preview button exists' => is_string($history)
        && str_contains($history, '>معاينة</a>'),
    'Permanent delete button exists' => is_string($history)
        && str_contains($history, 'تنفيذ الحذف النهائي'),
    'Irreversible-operation warning exists' => is_string($history)
        && str_contains($history, 'هذه العملية غير قابلة للاستعادة'),
    'Preview route exists' => is_string($routes)
        && str_contains($routes, "name('attachments.archived-preview')"),
    'Permanent delete route exists' => is_string($routes)
        && str_contains($routes, "name('attachments.force-delete')"),
    'Preview permission is protected' => is_string($permissions)
        && str_contains(
            $permissions,
            "'attachments.archived-preview' => 'attachments.preview'"
        ),
    'Permanent delete permission is protected' => is_string($permissions)
        && str_contains(
            $permissions,
            "'attachments.force-delete' => 'documents.force_delete'"
        ),
    'Activity labels exist' => is_string($activity)
        && str_contains($activity, 'attachment.version_previewed')
        && str_contains($activity, 'attachment.force_deleted'),
];

$failed = false;

foreach ($checks as $label => $ok) {
    echo ($ok ? '[ OK ] ' : '[FAIL] ') . $label . PHP_EOL;
    $failed = $failed || ! $ok;
}

if ($failed) {
    fwrite(
        STDERR,
        "Attachment history controls V83.1 check failed.\n"
    );
    exit(1);
}

echo "Attachment history controls V83.1 check passed.\n";

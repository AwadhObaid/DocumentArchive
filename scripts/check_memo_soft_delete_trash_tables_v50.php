<?php

$root = dirname(__DIR__);
$failed = false;

function ok(string $message): void
{
    echo "[OK] {$message}" . PHP_EOL;
}

function failCheck(string $message): void
{
    global $failed;
    $failed = true;
    echo "[FAIL] {$message}" . PHP_EOL;
}

function fileContains(string $path, string $needle, string $label): void
{
    if (! is_file($path)) {
        failCheck("Missing file: {$path}");
        return;
    }

    $content = file_get_contents($path) ?: '';
    if (str_contains($content, $needle)) {
        ok($label);
        return;
    }

    failCheck("Missing expected content in {$path}: {$needle}");
}

$memoController = $root . '/app/Http/Controllers/MemoController.php';
$documentController = $root . '/app/Http/Controllers/DocumentController.php';
$routes = $root . '/routes/web.php';
$trashView = $root . '/resources/views/documents/trash.blade.php';
$middleware = $root . '/app/Http/Middleware/ApplyRoutePermissions.php';
$registry = $root . '/app/Support/PermissionRegistry.php';
$activityLog = $root . '/app/Models/ActivityLog.php';
$migration = $root . '/database/migrations/2026_07_09_120000_ensure_memos_soft_delete_trash_v50.php';

fileContains($memoController, 'public function restore(int $id)', 'Memo restore method exists');
fileContains($memoController, 'public function forceDelete(int $id)', 'Memo force delete method exists');
fileContains($memoController, 'Memo::onlyTrashed()', 'Memo trash operations use onlyTrashed');
fileContains($memoController, "'memo.deleted'", 'Memo soft delete activity is logged');
fileContains($memoController, "'memo.restored'", 'Memo restore activity is logged');
fileContains($memoController, "'memo.force_deleted'", 'Memo force delete activity is logged');

fileContains($documentController, '$memos = Memo::onlyTrashed()', 'Document trash page loads deleted memos');
fileContains($documentController, "paginate(10, ['*'], 'documents_page')", 'Documents pagination has isolated page name');
fileContains($documentController, "paginate(10, ['*'], 'memos_page')", 'Memos pagination has isolated page name');

fileContains($routes, "->name('memos.restore')", 'Memo restore route exists');
fileContains($routes, "->name('memos.force-delete')", 'Memo force delete route exists');
fileContains($routes, "Route::resource('memos', MemoController::class)", 'Memo resource route still exists');

fileContains($trashView, 'الكتب المحذوفة', 'Trash view contains documents table title');
fileContains($trashView, 'المذكرات المحذوفة', 'Trash view contains memos table title');
fileContains($trashView, "route('memos.restore'", 'Trash view contains memo restore form');
fileContains($trashView, "route('memos.force-delete'", 'Trash view contains memo force delete form');

fileContains($middleware, "'memos.restore'", 'Middleware protects memo restore route');
fileContains($middleware, "'memos.force-delete'", 'Middleware protects memo force delete route');
fileContains($middleware, 'userHasAnyPermission', 'Middleware supports OR permissions');

fileContains($registry, "'documents.force_delete'", 'Document force-delete permission registered');
fileContains($registry, "'memos.restore'", 'Memo restore permission registered');
fileContains($registry, "'memos.force_delete'", 'Memo force-delete permission registered');

fileContains($activityLog, "'memo.restored'", 'Memo restore activity label registered');
fileContains($activityLog, "'memo.force_deleted'", 'Memo force delete activity label registered');

fileContains($migration, "Schema::hasColumn('memos', 'deleted_at')", 'Migration safely checks deleted_at column');
fileContains($migration, '$table->softDeletes()', 'Migration adds soft delete column when missing');

if ($failed) {
    echo PHP_EOL . 'Memo soft delete trash tables V50 check failed.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Memo soft delete trash tables V50 check passed.' . PHP_EOL;

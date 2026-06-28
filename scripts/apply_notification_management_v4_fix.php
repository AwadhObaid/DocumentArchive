<?php
/**
 * Notification routes/actions V4 compatibility fix.
 *
 * Run from Laravel project root:
 *   php scripts/apply_notification_routes_v4_fix.php
 */

function root_path_v4(): string
{
    $cwd = getcwd();
    if (is_file($cwd . DIRECTORY_SEPARATOR . 'artisan')) {
        return $cwd;
    }

    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    throw new RuntimeException('تعذر تحديد جذر مشروع Laravel. شغل السكربت من داخل مجلد المشروع.');
}

function ensure_dir_v4(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function remove_backup_dirs_v4(string $root): void
{
    $targets = [
        $root . DIRECTORY_SEPARATOR . 'app',
        $root . DIRECTORY_SEPARATOR . 'resources',
        $root . DIRECTORY_SEPARATOR . 'routes',
    ];

    foreach ($targets as $target) {
        if (!is_dir($target)) {
            continue;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $item) {
            if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
                remove_dir_v4($item->getPathname());
            }
        }
    }
}

function remove_dir_v4(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($dir);
}

function backup_file_v4(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    $backupDir = dirname($file) . DIRECTORY_SEPARATOR . '_backup_notification_routes_v4_' . date('Ymd_His');
    ensure_dir_v4($backupDir);
    copy($file, $backupDir . DIRECTORY_SEPARATOR . basename($file));
}

function class_end_position_v4(string $code): int
{
    $len = strlen($code);
    $last = strrpos($code, '}');
    if ($last === false) {
        throw new RuntimeException('تعذر تحديد نهاية الكلاس.');
    }
    return $last;
}

function ensure_use_v4(string $code, string $useLine): string
{
    if (str_contains($code, $useLine)) {
        return $code;
    }

    $namespacePos = strpos($code, "namespace ");
    if ($namespacePos === false) {
        return $useLine . PHP_EOL . $code;
    }

    $semicolonPos = strpos($code, ';', $namespacePos);
    if ($semicolonPos === false) {
        return $useLine . PHP_EOL . $code;
    }

    return substr($code, 0, $semicolonPos + 1) . PHP_EOL . $useLine . substr($code, $semicolonPos + 1);
}

function ensure_method_v4(string $code, string $methodName, string $methodCode): string
{
    if (strpos($code, 'function ' . $methodName . '(') !== false || strpos($code, 'function ' . $methodName . ' (') !== false) {
        return $code;
    }

    $pos = class_end_position_v4($code);
    return substr($code, 0, $pos) . PHP_EOL . $methodCode . PHP_EOL . substr($code, $pos);
}

function ensure_routes_v4(string $routesFile): void
{
    if (!is_file($routesFile)) {
        throw new RuntimeException('ملف routes/web.php غير موجود.');
    }

    $code = file_get_contents($routesFile);
    backup_file_v4($routesFile);

    if (!str_contains($code, 'use App\Http\Controllers\NotificationCenterController;')) {
        $insert = "use App\\Http\\Controllers\\NotificationCenterController;" . PHP_EOL;
        if (str_starts_with($code, "<?php")) {
            $code = preg_replace('/<\?php\s*/', "<?php\n" . $insert, $code, 1);
        } else {
            $code = "<?php\n" . $insert . $code;
        }
    }

    $block = <<<'PHP'

/*
|--------------------------------------------------------------------------
| Notification management compatibility routes - V4
|--------------------------------------------------------------------------
| These aliases keep old and new notification buttons working.
*/
Route::middleware(['auth'])->group(function () {
    Route::post('/notifications/read-all', [NotificationCenterController::class, 'markAllRead'])->name('notifications.read_all');
    Route::post('/notifications/mark-all-read', [NotificationCenterController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/hide-read', [NotificationCenterController::class, 'hideRead'])->name('notifications.hide-read');
    Route::post('/notifications/delete-hidden', [NotificationCenterController::class, 'deleteHidden'])->name('notifications.delete-hidden');
    Route::post('/notifications/purge-hidden', [NotificationCenterController::class, 'deleteHidden'])->name('notifications.purge-hidden');
    Route::post('/notifications/clear-hidden', [NotificationCenterController::class, 'deleteHidden'])->name('notifications.clear-hidden');
});
PHP;

    if (!str_contains($code, 'Notification management compatibility routes - V4')) {
        $code .= PHP_EOL . $block . PHP_EOL;
    }

    file_put_contents($routesFile, $code);
}

function ensure_controller_v4(string $controllerFile): void
{
    if (!is_file($controllerFile)) {
        throw new RuntimeException('ملف NotificationCenterController.php غير موجود.');
    }

    $code = file_get_contents($controllerFile);
    backup_file_v4($controllerFile);

    $code = ensure_use_v4($code, 'use Illuminate\Support\Facades\Schema;');
    $code = ensure_use_v4($code, 'use Illuminate\Support\Facades\DB;');

    $helperMethod = <<<'PHP'

    private function notificationQueryForCurrentUser()
    {
        $userId = auth()->id();

        return DB::table('system_notifications')
            ->where(function ($query) use ($userId) {
                $query->whereNull('user_id');

                if ($userId) {
                    $query->orWhere('user_id', $userId);
                }
            });
    }
PHP;

    $markAllReadMethod = <<<'PHP'

    public function markAllRead()
    {
        if (!Schema::hasTable('system_notifications')) {
            return redirect()->route('notifications.index')->with('warning', 'جدول الإشعارات غير موجود.');
        }

        $data = ['read_at' => now()];

        if (Schema::hasColumn('system_notifications', 'updated_at')) {
            $data['updated_at'] = now();
        }

        $this->notificationQueryForCurrentUser()
            ->whereNull('read_at')
            ->update($data);

        return redirect()->route('notifications.index')->with('success', 'تم تعليم جميع الإشعارات كمقروءة.');
    }
PHP;

    $readAllMethod = <<<'PHP'

    public function readAll()
    {
        return $this->markAllRead();
    }
PHP;

    $hideReadMethod = <<<'PHP'

    public function hideRead()
    {
        if (!Schema::hasTable('system_notifications')) {
            return redirect()->route('notifications.index')->with('warning', 'جدول الإشعارات غير موجود.');
        }

        $query = $this->notificationQueryForCurrentUser()->whereNotNull('read_at');

        if (Schema::hasColumn('system_notifications', 'is_hidden') || Schema::hasColumn('system_notifications', 'hidden_at')) {
            $data = [];

            if (Schema::hasColumn('system_notifications', 'is_hidden')) {
                $data['is_hidden'] = true;
            }

            if (Schema::hasColumn('system_notifications', 'hidden_at')) {
                $data['hidden_at'] = now();
            }

            if (Schema::hasColumn('system_notifications', 'updated_at')) {
                $data['updated_at'] = now();
            }

            $query->update($data);
        } else {
            $query->delete();
        }

        return redirect()->route('notifications.index')->with('success', 'تم إخفاء الإشعارات المقروءة.');
    }
PHP;

    $deleteHiddenMethod = <<<'PHP'

    public function deleteHidden()
    {
        if (!Schema::hasTable('system_notifications')) {
            return redirect()->route('notifications.index')->with('warning', 'جدول الإشعارات غير موجود.');
        }

        $query = $this->notificationQueryForCurrentUser();

        if (Schema::hasColumn('system_notifications', 'is_hidden') || Schema::hasColumn('system_notifications', 'hidden_at')) {
            $query->where(function ($q) {
                if (Schema::hasColumn('system_notifications', 'is_hidden')) {
                    $q->orWhere('is_hidden', true);
                }

                if (Schema::hasColumn('system_notifications', 'hidden_at')) {
                    $q->orWhereNotNull('hidden_at');
                }
            })->delete();
        }

        return redirect()->route('notifications.index')->with('success', 'تم حذف الإشعارات المخفية.');
    }
PHP;

    $purgeHiddenMethod = <<<'PHP'

    public function purgeHidden()
    {
        return $this->deleteHidden();
    }
PHP;

    $clearHiddenMethod = <<<'PHP'

    public function clearHidden()
    {
        return $this->deleteHidden();
    }
PHP;

    $code = ensure_method_v4($code, 'notificationQueryForCurrentUser', $helperMethod);
    $code = ensure_method_v4($code, 'markAllRead', $markAllReadMethod);
    $code = ensure_method_v4($code, 'readAll', $readAllMethod);
    $code = ensure_method_v4($code, 'hideRead', $hideReadMethod);
    $code = ensure_method_v4($code, 'deleteHidden', $deleteHiddenMethod);
    $code = ensure_method_v4($code, 'purgeHidden', $purgeHiddenMethod);
    $code = ensure_method_v4($code, 'clearHidden', $clearHiddenMethod);

    file_put_contents($controllerFile, $code);
}

function ensure_migration_v4(string $root): void
{
    $dir = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    ensure_dir_v4($dir);

    $file = $dir . DIRECTORY_SEPARATOR . '2026_06_28_000030_add_hidden_fields_to_system_notifications_v4.php';

    if (is_file($file)) {
        return;
    }

    $migration = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_notifications')) {
            return;
        }

        Schema::table('system_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('system_notifications', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->after('read_at');
            }

            if (!Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->after('is_hidden');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_notifications')) {
            return;
        }

        Schema::table('system_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->dropColumn('hidden_at');
            }

            if (Schema::hasColumn('system_notifications', 'is_hidden')) {
                $table->dropColumn('is_hidden');
            }
        });
    }
};
PHP;

    file_put_contents($file, $migration);
}

try {
    $root = root_path_v4();

    remove_backup_dirs_v4($root);
    ensure_controller_v4($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'NotificationCenterController.php');
    ensure_routes_v4($root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php');
    ensure_migration_v4($root);

    echo "DONE: تم إصلاح مسارات وأزرار إدارة الإشعارات V4.\n";
    echo "NEXT: php artisan migrate && php artisan route:clear && php artisan view:clear && php artisan optimize:clear\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

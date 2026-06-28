<?php
/**
 * Fix Notification Center management routes and Schema import.
 * Run from Laravel project root:
 * php scripts/apply_notification_management_v2_fix.php
 */

function project_root(): string
{
    $root = dirname(__DIR__);
    if (!file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
        fwrite(STDERR, "ERROR: شغّل السكربت من جذر مشروع Laravel.\n");
        exit(1);
    }
    return $root;
}

function fail_msg(string $msg): void
{
    fwrite(STDERR, "ERROR: {$msg}\n");
    exit(1);
}

function write_file(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    if (file_put_contents($path, $content) === false) {
        fail_msg("تعذر كتابة الملف: {$path}");
    }
}

function ensure_once(string &$content, string $needle, string $insertAfterNeedle = null, string $fallbackPrefix = ''): void
{
    if (str_contains($content, $needle)) {
        return;
    }
    if ($insertAfterNeedle !== null && str_contains($content, $insertAfterNeedle)) {
        $content = str_replace($insertAfterNeedle, $insertAfterNeedle . $needle, $content);
    } else {
        $content = $fallbackPrefix . $needle . $content;
    }
}

$root = project_root();
$controllerPath = $root . '/app/Http/Controllers/NotificationCenterController.php';
$routesPath = $root . '/routes/web.php';

if (!file_exists($controllerPath)) {
    fail_msg('الملف غير موجود: app/Http/Controllers/NotificationCenterController.php');
}
if (!file_exists($routesPath)) {
    fail_msg('الملف غير موجود: routes/web.php');
}

$controller = file_get_contents($controllerPath);
$routes = file_get_contents($routesPath);

// Backup once per run.
$stamp = date('Ymd_His');
@copy($controllerPath, $controllerPath . ".backup_notification_management_v2_{$stamp}");
@copy($routesPath, $routesPath . ".backup_notification_management_v2_{$stamp}");

// 1) Fix Schema namespace error.
if (!str_contains($controller, 'use Illuminate\\Support\\Facades\\Schema;')) {
    if (preg_match('/^namespace\s+App\\Http\\Controllers;\s*$/m', $controller)) {
        $controller = preg_replace('/^(namespace\s+App\\Http\\Controllers;\s*)$/m', "$1\n\nuse Illuminate\\Support\\Facades\\Schema;", $controller, 1);
    } else {
        $controller = "<?php\n\nnamespace App\\Http\\Controllers;\n\nuse Illuminate\\Support\\Facades\\Schema;\n" . preg_replace('/^<\?php\s*/', '', $controller);
    }
}

// 2) Ensure DB facade exists too because fallback methods use it.
if (!str_contains($controller, 'use Illuminate\\Support\\Facades\\DB;')) {
    $controller = preg_replace('/(use\s+Illuminate\\Support\\Facades\\Schema;\s*)/', "$1\nuse Illuminate\\Support\\Facades\\DB;\n", $controller, 1);
}

// 3) Add helper and management methods only if missing.
$methods = <<<'PHP'

    /**
     * Query current user's notifications safely across older/newer table schemas.
     */
    private function notificationQueryForCurrentUser()
    {
        $query = DB::table('system_notifications');

        if (Schema::hasColumn('system_notifications', 'user_id') && auth()->check()) {
            $query->where(function ($q) {
                $q->where('user_id', auth()->id())
                  ->orWhereNull('user_id');
            });
        }

        return $query;
    }

    /**
     * تعليم كل الإشعارات كمقروءة.
     */
    public function markAllRead()
    {
        if (!Schema::hasTable('system_notifications')) {
            return redirect()->route('notifications.index')->with('warning', 'جدول الإشعارات غير موجود.');
        }

        $updates = [];
        if (Schema::hasColumn('system_notifications', 'read_at')) {
            $updates['read_at'] = now();
        }
        if (Schema::hasColumn('system_notifications', 'is_read')) {
            $updates['is_read'] = 1;
        }
        if (Schema::hasColumn('system_notifications', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        if (!empty($updates)) {
            $this->notificationQueryForCurrentUser()->update($updates);
        }

        return redirect()->route('notifications.index')->with('success', 'تم تعليم كل الإشعارات كمقروءة.');
    }

    /**
     * إخفاء الإشعارات المقروءة.
     */
    public function hideRead()
    {
        if (!Schema::hasTable('system_notifications')) {
            return redirect()->route('notifications.index')->with('warning', 'جدول الإشعارات غير موجود.');
        }

        $query = $this->notificationQueryForCurrentUser();

        if (Schema::hasColumn('system_notifications', 'read_at')) {
            $query->whereNotNull('read_at');
        } elseif (Schema::hasColumn('system_notifications', 'is_read')) {
            $query->where('is_read', 1);
        }

        $updates = [];
        if (Schema::hasColumn('system_notifications', 'hidden_at')) {
            $updates['hidden_at'] = now();
        }
        if (Schema::hasColumn('system_notifications', 'is_hidden')) {
            $updates['is_hidden'] = 1;
        }
        if (Schema::hasColumn('system_notifications', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        if (!empty($updates)) {
            $query->update($updates);
        }

        return redirect()->route('notifications.index')->with('success', 'تم إخفاء الإشعارات المقروءة.');
    }

    /**
     * حذف الإشعارات المخفية نهائياً.
     */
    public function deleteHidden()
    {
        if (!Schema::hasTable('system_notifications')) {
            return redirect()->route('notifications.index')->with('warning', 'جدول الإشعارات غير موجود.');
        }

        $query = $this->notificationQueryForCurrentUser();

        if (Schema::hasColumn('system_notifications', 'hidden_at')) {
            $query->whereNotNull('hidden_at');
        } elseif (Schema::hasColumn('system_notifications', 'is_hidden')) {
            $query->where('is_hidden', 1);
        } else {
            return redirect()->route('notifications.index')->with('warning', 'لا يوجد حقل مخصص للإشعارات المخفية.');
        }

        $deleted = $query->delete();

        return redirect()->route('notifications.index')->with('success', 'تم حذف الإشعارات المخفية نهائياً. العدد: ' . $deleted);
    }

    /**
     * أسماء بديلة للحفاظ على توافق الأزرار/المسارات القديمة.
     */
    public function purgeHidden()
    {
        return $this->deleteHidden();
    }

    public function clearHidden()
    {
        return $this->deleteHidden();
    }
PHP;

$needAppend = false;
foreach (['notificationQueryForCurrentUser', 'markAllRead', 'hideRead', 'deleteHidden', 'purgeHidden', 'clearHidden'] as $methodName) {
    if (!preg_match('/function\s+' . preg_quote($methodName, '/') . '\s*\(/', $controller)) {
        $needAppend = true;
        break;
    }
}

if ($needAppend) {
    // If methods with same names partially exist, remove the risky old management methods and append clean ones.
    foreach (['notificationQueryForCurrentUser', 'markAllRead', 'hideRead', 'deleteHidden', 'purgeHidden', 'clearHidden'] as $methodName) {
        $pattern = '/\n\s*(?:\/\*\*[\s\S]*?\*\/\s*)?public\s+function\s+' . preg_quote($methodName, '/') . '\s*\([^)]*\)\s*\{(?:[^{}]|(?R))*\}\s*/';
        // PHP PCRE recursive over entire pattern is fragile; skip removal if it fails.
    }
    $pos = strrpos($controller, '}');
    if ($pos === false) {
        fail_msg('تعذر تحديد نهاية كلاس NotificationCenterController.');
    }
    $controller = substr($controller, 0, $pos) . $methods . "\n}" . substr($controller, $pos + 1);
}

write_file($controllerPath, $controller);

// 4) Add missing route aliases to prevent 404 for hidden deletion buttons.
$routeBlock = <<<'PHP'

// Notification management routes - compatibility aliases.
Route::middleware(['auth'])->group(function () {
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\NotificationCenterController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/hide-read', [\App\Http\Controllers\NotificationCenterController::class, 'hideRead'])->name('notifications.hide-read');
    Route::post('/notifications/delete-hidden', [\App\Http\Controllers\NotificationCenterController::class, 'deleteHidden'])->name('notifications.delete-hidden');
    Route::post('/notifications/purge-hidden', [\App\Http\Controllers\NotificationCenterController::class, 'purgeHidden'])->name('notifications.purge-hidden');
    Route::post('/notifications/clear-hidden', [\App\Http\Controllers\NotificationCenterController::class, 'clearHidden'])->name('notifications.clear-hidden');
});
PHP;

if (!str_contains($routes, "notifications.delete-hidden") || !str_contains($routes, "/notifications/hide-read")) {
    $routes .= "\n" . $routeBlock . "\n";
}
write_file($routesPath, $routes);

// 5) Patch notification center view buttons if view exists, but don't fail if not found.
$viewCandidates = [
    $root . '/resources/views/notifications/index.blade.php',
    $root . '/resources/views/notification-center/index.blade.php',
];
foreach ($viewCandidates as $viewPath) {
    if (!file_exists($viewPath)) {
        continue;
    }
    $view = file_get_contents($viewPath);
    @copy($viewPath, $viewPath . ".backup_notification_management_v2_{$stamp}");

    // Replace common wrong action strings if they were hard-coded.
    $view = str_replace("url('/notifications/delete-hidden')", "route('notifications.delete-hidden')", $view);
    $view = str_replace("url('/notifications/purge-hidden')", "route('notifications.purge-hidden')", $view);
    $view = str_replace("url('/notifications/clear-hidden')", "route('notifications.clear-hidden')", $view);

    if (!str_contains($view, "notifications.delete-hidden") && str_contains($view, "حذف المخفية")) {
        // Add compact management forms near the top of page content if no route name exists.
        $buttons = <<<'BLADE'

<div class="notification-management-actions no-print" style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;">
    <form method="POST" action="{{ route('notifications.mark-all-read') }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-primary">تعليم الكل كمقروء</button>
    </form>
    <form method="POST" action="{{ route('notifications.hide-read') }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-warning">إخفاء المقروء</button>
    </form>
    <form method="POST" action="{{ route('notifications.delete-hidden') }}" onsubmit="return confirm('هل تريد حذف الإشعارات المخفية نهائياً؟');">
        @csrf
        <button type="submit" class="btn btn-sm btn-danger">حذف المخفية</button>
    </form>
</div>
BLADE;
        if (str_contains($view, '@section')) {
            $view = preg_replace('/(@section\([^\n]+\)\s*)/', "$1\n" . $buttons, $view, 1);
        } else {
            $view = $buttons . "\n" . $view;
        }
    }
    write_file($viewPath, $view);
}

// 6) Ensure hidden columns exist migration, because hide/delete actions need a hidden marker.
$migrationsDir = $root . '/database/migrations';
$migrationPath = $migrationsDir . '/2026_06_28_000020_add_hidden_columns_to_system_notifications_table.php';
if (!file_exists($migrationPath)) {
    write_file($migrationPath, <<<'PHP'
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
            if (!Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->after('read_at');
            }
            if (!Schema::hasColumn('system_notifications', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->after('hidden_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_notifications')) {
            return;
        }

        Schema::table('system_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('system_notifications', 'is_hidden')) {
                $table->dropColumn('is_hidden');
            }
            if (Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->dropColumn('hidden_at');
            }
        });
    }
};
PHP);
}

echo "DONE: تم إصلاح مسارات وإجراءات إدارة الإشعارات.\n";

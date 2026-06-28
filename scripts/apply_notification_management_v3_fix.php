<?php

/**
 * إصلاح إدارة الإشعارات V3
 * - لا يستخدم Regex مع backslashes تسبب أخطاء PCRE في Windows.
 * - ينظف مجلدات _backup* القديمة حتى لا تظهر تحذيرات Composer PSR-4.
 * - يستبدل NotificationCenterController بنسخة متوافقة وآمنة.
 * - يضيف routes الناقصة.
 * - يضيف migration لحقول إخفاء الإشعارات.
 */

function root_path_from_script(): string
{
    $root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
        fwrite(STDERR, "ERROR: شغّل السكربت من داخل جذر مشروع Laravel.\n");
        exit(1);
    }
    return $root;
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("تعذر إنشاء المجلد: {$dir}");
    }
}

function write_file(string $path, string $content): void
{
    ensure_dir(dirname($path));
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("تعذر كتابة الملف: {$path}");
    }
}

function remove_dir_recursive(string $dir): void
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

function cleanup_backup_dirs(string $root): void
{
    $scanRoots = [
        $root . DIRECTORY_SEPARATOR . 'app',
        $root . DIRECTORY_SEPARATOR . 'resources',
        $root . DIRECTORY_SEPARATOR . 'routes',
    ];

    foreach ($scanRoots as $scanRoot) {
        if (!is_dir($scanRoot)) {
            continue;
        }

        $dirsToDelete = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
                $dirsToDelete[] = $item->getPathname();
            }
        }

        // حذف الأطول أولاً لتجنب محاولة حذف مجلد أب قبل الابن.
        usort($dirsToDelete, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach (array_unique($dirsToDelete) as $dir) {
            remove_dir_recursive($dir);
            echo "Deleted backup dir: {$dir}\n";
        }
    }
}

$root = root_path_from_script();

try {
    cleanup_backup_dirs($root);

    $controllerPath = $root . DIRECTORY_SEPARATOR . 'app/Http/Controllers/NotificationCenterController.php';

    if (file_exists($controllerPath)) {
        $backupDir = $root . DIRECTORY_SEPARATOR . 'storage/app/private/code-backups/notification-management-v3-' . date('Ymd-His');
        ensure_dir($backupDir);
        copy($controllerPath, $backupDir . DIRECTORY_SEPARATOR . 'NotificationCenterController.php');
        echo "Controller backup saved to: {$backupDir}\n";
    }

    $controller = <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function index(Request $request): View
    {
        $this->syncNotificationsSafely();

        if (!Schema::hasTable('system_notifications')) {
            $notifications = $this->emptyPaginator($request);
            $unreadCount = 0;
            $readCount = 0;
            $hiddenCount = 0;

            return view('notifications.index', [
                'notifications' => $notifications,
                'items' => $notifications,
                'unreadCount' => $unreadCount,
                'readCount' => $readCount,
                'hiddenCount' => $hiddenCount,
            ]);
        }

        $notifications = $this->notificationQueryForCurrentUser(false)
            ->orderByDesc($this->createdColumn())
            ->paginate(20)
            ->withQueryString();

        $unreadCount = $this->notificationQueryForCurrentUser(false)
            ->when(Schema::hasColumn('system_notifications', 'read_at'), fn ($q) => $q->whereNull('read_at'))
            ->count();

        $readCount = $this->notificationQueryForCurrentUser(false)
            ->when(Schema::hasColumn('system_notifications', 'read_at'), fn ($q) => $q->whereNotNull('read_at'))
            ->count();

        $hiddenCount = $this->notificationQueryForCurrentUser(true)
            ->tap(fn ($q) => $this->applyHiddenOnly($q))
            ->count();

        return view('notifications.index', [
            'notifications' => $notifications,
            'items' => $notifications,
            'unreadCount' => $unreadCount,
            'readCount' => $readCount,
            'hiddenCount' => $hiddenCount,
        ]);
    }

    public function markAllRead(): RedirectResponse
    {
        if (!Schema::hasTable('system_notifications') || !Schema::hasColumn('system_notifications', 'read_at')) {
            return back()->with('warning', 'جدول الإشعارات غير جاهز لتعليم الإشعارات كمقروءة.');
        }

        $this->notificationQueryForCurrentUser(true)->update([
            'read_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'تم تعليم كل الإشعارات كمقروءة.');
    }

    public function readAll(): RedirectResponse
    {
        return $this->markAllRead();
    }

    public function markAsRead(int|string $notification): RedirectResponse
    {
        if (!Schema::hasTable('system_notifications') || !Schema::hasColumn('system_notifications', 'read_at')) {
            return back()->with('warning', 'جدول الإشعارات غير جاهز.');
        }

        $this->notificationQueryForCurrentUser(true)
            ->where('id', $notification)
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'تم تعليم الإشعار كمقروء.');
    }

    public function hide(int|string $notification): RedirectResponse
    {
        if (!$this->hasHiddenSupport()) {
            return back()->with('warning', 'حقول إخفاء الإشعارات غير موجودة. نفّذ php artisan migrate.');
        }

        $payload = $this->hiddenPayload();

        $this->notificationQueryForCurrentUser(true)
            ->where('id', $notification)
            ->update($payload);

        return back()->with('success', 'تم إخفاء الإشعار.');
    }

    public function hideRead(): RedirectResponse
    {
        if (!$this->hasHiddenSupport()) {
            return back()->with('warning', 'حقول إخفاء الإشعارات غير موجودة. نفّذ php artisan migrate.');
        }

        $query = $this->notificationQueryForCurrentUser(true);

        if (Schema::hasColumn('system_notifications', 'read_at')) {
            $query->whereNotNull('read_at');
        }

        $affected = $query->update($this->hiddenPayload());

        return back()->with('success', "تم إخفاء الإشعارات المقروءة. العدد: {$affected}");
    }

    public function deleteHidden(): RedirectResponse
    {
        if (!Schema::hasTable('system_notifications')) {
            return back()->with('warning', 'جدول الإشعارات غير موجود.');
        }

        $query = $this->notificationQueryForCurrentUser(true);
        $this->applyHiddenOnly($query);

        $affected = $query->delete();

        return back()->with('success', "تم حذف الإشعارات المخفية نهائياً. العدد: {$affected}");
    }

    public function purgeHidden(): RedirectResponse
    {
        return $this->deleteHidden();
    }

    public function clearHidden(): RedirectResponse
    {
        return $this->deleteHidden();
    }

    public function destroy(int|string $notification): RedirectResponse
    {
        if (!Schema::hasTable('system_notifications')) {
            return back()->with('warning', 'جدول الإشعارات غير موجود.');
        }

        $this->notificationQueryForCurrentUser(true)
            ->where('id', $notification)
            ->delete();

        return back()->with('success', 'تم حذف الإشعار.');
    }

    private function syncNotificationsSafely(): void
    {
        try {
            if (class_exists(SystemNotificationService::class)) {
                $service = app(SystemNotificationService::class);
                if (method_exists($service, 'syncForCurrentUser')) {
                    $service->syncForCurrentUser();
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function notificationQueryForCurrentUser(bool $includeHidden = false)
    {
        $query = DB::table('system_notifications');

        $userId = Auth::id();

        if (Schema::hasColumn('system_notifications', 'user_id')) {
            $query->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            });
        } elseif (Schema::hasColumn('system_notifications', 'recipient_user_id')) {
            $query->where(function ($q) use ($userId) {
                $q->whereNull('recipient_user_id')->orWhere('recipient_user_id', $userId);
            });
        }

        if (!$includeHidden) {
            $query->where(function ($q) {
                if (Schema::hasColumn('system_notifications', 'is_hidden')) {
                    $q->whereNull('is_hidden')->orWhere('is_hidden', false)->orWhere('is_hidden', 0);
                }

                if (Schema::hasColumn('system_notifications', 'hidden_at')) {
                    $q->whereNull('hidden_at');
                }
            });
        }

        return $query;
    }

    private function applyHiddenOnly($query): void
    {
        $query->where(function ($q) {
            $hasCondition = false;

            if (Schema::hasColumn('system_notifications', 'is_hidden')) {
                $q->where('is_hidden', true)->orWhere('is_hidden', 1);
                $hasCondition = true;
            }

            if (Schema::hasColumn('system_notifications', 'hidden_at')) {
                $method = $hasCondition ? 'orWhereNotNull' : 'whereNotNull';
                $q->{$method}('hidden_at');
            }
        });
    }

    private function hiddenPayload(): array
    {
        $payload = ['updated_at' => now()];

        if (Schema::hasColumn('system_notifications', 'is_hidden')) {
            $payload['is_hidden'] = true;
        }

        if (Schema::hasColumn('system_notifications', 'hidden_at')) {
            $payload['hidden_at'] = now();
        }

        return $payload;
    }

    private function hasHiddenSupport(): bool
    {
        return Schema::hasTable('system_notifications')
            && (
                Schema::hasColumn('system_notifications', 'is_hidden')
                || Schema::hasColumn('system_notifications', 'hidden_at')
            );
    }

    private function createdColumn(): string
    {
        return Schema::hasColumn('system_notifications', 'created_at') ? 'created_at' : 'id';
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            new Collection(),
            0,
            20,
            LengthAwarePaginator::resolveCurrentPage(),
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}
PHP;

    write_file($controllerPath, $controller);

    $migrationPath = $root . DIRECTORY_SEPARATOR . 'database/migrations/2026_06_28_000030_add_hidden_columns_to_system_notifications_table.php';
    if (!file_exists($migrationPath)) {
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
        write_file($migrationPath, $migration);
    }

    $routesPath = $root . DIRECTORY_SEPARATOR . 'routes/web.php';
    if (!file_exists($routesPath)) {
        throw new RuntimeException('ملف routes/web.php غير موجود.');
    }

    $routes = file_get_contents($routesPath);
    if ($routes === false) {
        throw new RuntimeException('تعذر قراءة routes/web.php');
    }

    if (!str_contains($routes, 'use App\Http\Controllers\NotificationCenterController;')) {
        $routes = preg_replace('/^<\?php\s*/', "<?php\n\nuse App\\Http\\Controllers\\NotificationCenterController;\n", $routes, 1);
    }

    $routeBlock = <<<'PHP'


/*
|--------------------------------------------------------------------------
| Notification management fallback routes
|--------------------------------------------------------------------------
| Safe compatibility routes for notification center actions.
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationCenterController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/mark-all-read', [NotificationCenterController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/hide-read', [NotificationCenterController::class, 'hideRead'])->name('notifications.hide-read');
    Route::post('/notifications/delete-hidden', [NotificationCenterController::class, 'deleteHidden'])->name('notifications.delete-hidden');
    Route::post('/notifications/purge-hidden', [NotificationCenterController::class, 'purgeHidden'])->name('notifications.purge-hidden');
    Route::post('/notifications/clear-hidden', [NotificationCenterController::class, 'clearHidden'])->name('notifications.clear-hidden');
    Route::post('/notifications/{notification}/read', [NotificationCenterController::class, 'markAsRead'])->whereNumber('notification')->name('notifications.read');
    Route::post('/notifications/{notification}/hide', [NotificationCenterController::class, 'hide'])->whereNumber('notification')->name('notifications.hide');
    Route::delete('/notifications/{notification}', [NotificationCenterController::class, 'destroy'])->whereNumber('notification')->name('notifications.destroy');
});
PHP;

    $neededNames = [
        'notifications.hide-read',
        'notifications.delete-hidden',
        'notifications.purge-hidden',
        'notifications.clear-hidden',
    ];

    $missingAny = false;
    foreach ($neededNames as $name) {
        if (!str_contains($routes, $name)) {
            $missingAny = true;
            break;
        }
    }

    if ($missingAny) {
        $routes .= $routeBlock;
        file_put_contents($routesPath, $routes);
    }

    echo "DONE: تم تطبيق إصلاح إدارة الإشعارات V3 بنجاح.\n";
    echo "NEXT: نفّذ الأوامر التالية:\n";
    echo "php artisan migrate\n";
    echo "composer dump-autoload\n";
    echo "php artisan route:clear\n";
    echo "php artisan view:clear\n";
    echo "php artisan optimize:clear\n";
    echo "php scripts/check_notification_management_v3_fix.php\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

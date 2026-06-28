<?php
/**
 * DocumentArchive - Notification Management V5 Stable Fix
 * يعالج مشكلة تمرير stdClass إلى route()، ويوحّد كنترولر ومشهد ومسارات مركز الإشعارات.
 */

function project_root(): string
{
    $dir = getcwd();
    while ($dir && $dir !== dirname($dir)) {
        if (file_exists($dir . DIRECTORY_SEPARATOR . 'artisan')) {
            return $dir;
        }
        $dir = dirname($dir);
    }
    fwrite(STDERR, "ERROR: لم يتم العثور على ملف artisan. شغل السكربت من داخل جذر مشروع Laravel.\n");
    exit(1);
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("تعذر إنشاء المجلد: {$dir}");
    }
}

function backup_file(string $root, string $file): void
{
    if (!is_file($file)) {
        return;
    }
    $relative = str_replace([$root . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $file);
    $backupDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'code-backups' . DIRECTORY_SEPARATOR . 'notification_management_v5_' . date('Ymd_His') . DIRECTORY_SEPARATOR . dirname($relative);
    ensure_dir($backupDir);
    copy($file, $backupDir . DIRECTORY_SEPARATOR . basename($file));
}

function write_file(string $root, string $path, string $content): void
{
    $full = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    ensure_dir(dirname($full));
    backup_file($root, $full);
    if (file_put_contents($full, $content) === false) {
        throw new RuntimeException("تعذر كتابة الملف: {$path}");
    }
}

function delete_backup_dirs(string $root): void
{
    foreach (['app', 'resources', 'routes'] as $base) {
        $basePath = $root . DIRECTORY_SEPARATOR . $base;
        if (!is_dir($basePath)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
                $dir = $item->getPathname();
                $rit = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($rit as $child) {
                    $child->isDir() ? @rmdir($child->getPathname()) : @unlink($child->getPathname());
                }
                @rmdir($dir);
            }
        }
    }
}

$root = project_root();
delete_backup_dirs($root);

$controller = <<<'PHPCTRL'
<?php

namespace App\Http\Controllers;

use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    protected string $table = 'system_notifications';

    public function index(Request $request): View
    {
        if (class_exists(SystemNotificationService::class) && method_exists(SystemNotificationService::class, 'syncForCurrentUser')) {
            try {
                app(SystemNotificationService::class)->syncForCurrentUser();
            } catch (\Throwable $e) {
                // لا نكسر صفحة الإشعارات إذا فشل توليد التنبيهات التلقائية.
            }
        }

        if (!Schema::hasTable($this->table)) {
            return view('notifications.index', [
                'notifications' => collect(),
                'unreadCount' => 0,
                'readCount' => 0,
                'hiddenCount' => 0,
                'totalCount' => 0,
            ]);
        }

        $query = $this->baseQuery(false)->orderByDesc($this->createdAtColumn());

        $notifications = $query->paginate(20)->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $this->baseQuery(false)->whereNull($this->readColumn())->count(),
            'readCount' => $this->baseQuery(false)->whereNotNull($this->readColumn())->count(),
            'hiddenCount' => $this->baseQuery(true)->count(),
            'totalCount' => $this->baseQuery(false)->count(),
        ]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        if (Schema::hasTable($this->table)) {
            $updates = [];
            if (Schema::hasColumn($this->table, 'read_at')) {
                $updates['read_at'] = now();
            }
            if (Schema::hasColumn($this->table, 'is_read')) {
                $updates['is_read'] = 1;
            }
            if (!empty($updates)) {
                $this->baseQuery(false)->update($updates);
            }
        }

        return redirect()->route('notifications.index')->with('success', 'تم تعليم جميع الإشعارات كمقروءة.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        return $this->readAll($request);
    }

    public function read(Request $request, $notification): RedirectResponse
    {
        $id = $this->extractId($notification);
        if ($id && Schema::hasTable($this->table)) {
            $updates = [];
            if (Schema::hasColumn($this->table, 'read_at')) {
                $updates['read_at'] = now();
            }
            if (Schema::hasColumn($this->table, 'is_read')) {
                $updates['is_read'] = 1;
            }
            if (!empty($updates)) {
                $this->baseQuery(null)->where('id', $id)->update($updates);
            }
        }

        return redirect()->route('notifications.index')->with('success', 'تم تعليم الإشعار كمقروء.');
    }

    public function markAsRead(Request $request, $notification): RedirectResponse
    {
        return $this->read($request, $notification);
    }

    public function hideRead(Request $request): RedirectResponse
    {
        if (Schema::hasTable($this->table)) {
            $updates = [];
            if (Schema::hasColumn($this->table, 'is_hidden')) {
                $updates['is_hidden'] = 1;
            }
            if (Schema::hasColumn($this->table, 'hidden_at')) {
                $updates['hidden_at'] = now();
            }

            if (!empty($updates)) {
                $this->baseQuery(false)->whereNotNull($this->readColumn())->update($updates);
            }
        }

        return redirect()->route('notifications.index')->with('success', 'تم إخفاء الإشعارات المقروءة.');
    }

    public function hide(Request $request, $notification): RedirectResponse
    {
        $id = $this->extractId($notification);
        if ($id && Schema::hasTable($this->table)) {
            $updates = [];
            if (Schema::hasColumn($this->table, 'is_hidden')) {
                $updates['is_hidden'] = 1;
            }
            if (Schema::hasColumn($this->table, 'hidden_at')) {
                $updates['hidden_at'] = now();
            }
            if (!empty($updates)) {
                $this->baseQuery(null)->where('id', $id)->update($updates);
            }
        }

        return redirect()->route('notifications.index')->with('success', 'تم إخفاء الإشعار.');
    }

    public function deleteHidden(Request $request): RedirectResponse
    {
        if (Schema::hasTable($this->table)) {
            $this->baseQuery(true)->delete();
        }

        return redirect()->route('notifications.index')->with('success', 'تم حذف الإشعارات المخفية نهائياً.');
    }

    public function purgeHidden(Request $request): RedirectResponse
    {
        return $this->deleteHidden($request);
    }

    public function clearHidden(Request $request): RedirectResponse
    {
        return $this->deleteHidden($request);
    }

    public function destroy(Request $request, $notification): RedirectResponse
    {
        $id = $this->extractId($notification);
        if ($id && Schema::hasTable($this->table)) {
            $this->baseQuery(null)->where('id', $id)->delete();
        }

        return redirect()->route('notifications.index')->with('success', 'تم حذف الإشعار.');
    }

    protected function baseQuery(?bool $hiddenOnly = false)
    {
        $query = DB::table($this->table);

        if (Schema::hasColumn($this->table, 'user_id')) {
            $userId = Auth::id();
            $query->where(function ($q) use ($userId) {
                $q->whereNull('user_id');
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
            });
        }

        if ($hiddenOnly === true) {
            $query->where(function ($q) {
                if (Schema::hasColumn($this->table, 'is_hidden')) {
                    $q->orWhere('is_hidden', 1)->orWhere('is_hidden', true);
                }
                if (Schema::hasColumn($this->table, 'hidden_at')) {
                    $q->orWhereNotNull('hidden_at');
                }
            });
        } elseif ($hiddenOnly === false) {
            $query->where(function ($q) {
                if (Schema::hasColumn($this->table, 'is_hidden')) {
                    $q->whereNull('is_hidden')->orWhere('is_hidden', 0)->orWhere('is_hidden', false);
                }
                if (Schema::hasColumn($this->table, 'hidden_at')) {
                    $q->whereNull('hidden_at');
                }
            });
        }

        return $query;
    }

    protected function readColumn(): string
    {
        return Schema::hasColumn($this->table, 'read_at') ? 'read_at' : 'created_at';
    }

    protected function createdAtColumn(): string
    {
        return Schema::hasColumn($this->table, 'created_at') ? 'created_at' : 'id';
    }

    protected function extractId($value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_object($value) && isset($value->id) && is_numeric($value->id)) {
            return (int) $value->id;
        }
        if (is_array($value) && isset($value['id']) && is_numeric($value['id'])) {
            return (int) $value['id'];
        }
        return null;
    }
}
PHPCTRL;

$view = <<<'BLADE'
@extends('layouts.app')

@section('title', 'مركز الإشعارات')

@section('content')
@php
    use Illuminate\Support\Facades\Route;

    $readAllAction = Route::has('notifications.read_all') ? route('notifications.read_all') : url('/notifications/read-all');
    $hideReadAction = Route::has('notifications.hide-read') ? route('notifications.hide-read') : url('/notifications/hide-read');
    $deleteHiddenAction = Route::has('notifications.delete-hidden') ? route('notifications.delete-hidden') : url('/notifications/delete-hidden');
    $settingsUrl = Route::has('notification-settings.index') ? route('notification-settings.index') : url('/notification-settings');

    $items = $notifications ?? collect();
@endphp

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h1 class="page-title mb-1">🔔 مركز الإشعارات</h1>
        <p class="text-muted mb-0">متابعة تنبيهات النظام والعمليات المهمة.</p>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ $settingsUrl }}" class="btn btn-outline-secondary">⚙️ إعدادات الإشعارات</a>

        <form method="POST" action="{{ $readAllAction }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary">تعليم الكل كمقروءة</button>
        </form>

        <form method="POST" action="{{ $hideReadAction }}" class="d-inline" onsubmit="return confirm('هل تريد إخفاء كل الإشعارات المقروءة؟')">
            @csrf
            <button type="submit" class="btn btn-outline-warning">إخفاء المقروء</button>
        </form>

        <form method="POST" action="{{ $deleteHiddenAction }}" class="d-inline" onsubmit="return confirm('سيتم حذف الإشعارات المخفية نهائياً. هل تريد المتابعة؟')">
            @csrf
            <button type="submit" class="btn btn-outline-danger">حذف المخفية</button>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">الإجمالي</div><div class="fs-3 fw-bold">{{ $totalCount ?? 0 }}</div></div></div></div>
    <div class="col-md-3 col-6"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">غير مقروءة</div><div class="fs-3 fw-bold text-danger">{{ $unreadCount ?? 0 }}</div></div></div></div>
    <div class="col-md-3 col-6"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">مقروءة</div><div class="fs-3 fw-bold text-success">{{ $readCount ?? 0 }}</div></div></div></div>
    <div class="col-md-3 col-6"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">مخفية</div><div class="fs-3 fw-bold text-secondary">{{ $hiddenCount ?? 0 }}</div></div></div></div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-transparent">
        <strong>قائمة الإشعارات</strong>
    </div>
    <div class="card-body p-0">
        @if(method_exists($items, 'count') && $items->count())
            <div class="list-group list-group-flush">
                @foreach($items as $notification)
                    @php
                        $notificationId = data_get($notification, 'id');
                        $title = data_get($notification, 'title') ?: data_get($notification, 'subject') ?: 'إشعار نظام';
                        $body = data_get($notification, 'body') ?: data_get($notification, 'message') ?: data_get($notification, 'description') ?: '';
                        $type = data_get($notification, 'type') ?: data_get($notification, 'level') ?: 'info';
                        $readAt = data_get($notification, 'read_at');
                        $createdAt = data_get($notification, 'created_at');
                        $rawLink = data_get($notification, 'link') ?: data_get($notification, 'url') ?: data_get($notification, 'action_url');
                        $safeLink = (is_string($rawLink) && trim($rawLink) !== '') ? $rawLink : null;

                        $markReadAction = $notificationId
                            ? (Route::has('notifications.read') ? route('notifications.read', ['notification' => $notificationId]) : url('/notifications/' . $notificationId . '/read'))
                            : null;
                        $hideAction = $notificationId
                            ? (Route::has('notifications.hide') ? route('notifications.hide', ['notification' => $notificationId]) : url('/notifications/' . $notificationId . '/hide'))
                            : null;
                        $deleteAction = $notificationId
                            ? (Route::has('notifications.destroy') ? route('notifications.destroy', ['notification' => $notificationId]) : url('/notifications/' . $notificationId))
                            : null;

                        $badgeClass = match($type) {
                            'success' => 'bg-success',
                            'warning' => 'bg-warning text-dark',
                            'error', 'danger' => 'bg-danger',
                            default => 'bg-info text-dark',
                        };
                    @endphp

                    <div class="list-group-item notification-item {{ $readAt ? '' : 'bg-light' }}">
                        <div class="d-flex justify-content-between gap-3 flex-wrap">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    <span class="badge {{ $badgeClass }}">{{ $type }}</span>
                                    <strong>{{ $title }}</strong>
                                    @unless($readAt)
                                        <span class="badge bg-danger">جديد</span>
                                    @endunless
                                </div>

                                @if($body)
                                    <div class="text-muted small mb-2">{{ $body }}</div>
                                @endif

                                <div class="text-muted small">{{ $createdAt }}</div>
                            </div>

                            <div class="d-flex align-items-start gap-2 flex-wrap">
                                @if($safeLink)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ $safeLink }}">فتح</a>
                                @endif

                                @if($markReadAction && !$readAt)
                                    <form method="POST" action="{{ $markReadAction }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" type="submit">مقروء</button>
                                    </form>
                                @endif

                                @if($hideAction)
                                    <form method="POST" action="{{ $hideAction }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning" type="submit">إخفاء</button>
                                    </form>
                                @endif

                                @if($deleteAction)
                                    <form method="POST" action="{{ $deleteAction }}" class="d-inline" onsubmit="return confirm('هل تريد حذف هذا الإشعار؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">حذف</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-5 text-center text-muted">
                لا توجد إشعارات حالياً.
            </div>
        @endif
    </div>

    @if(is_object($items) && method_exists($items, 'links'))
        <div class="card-footer bg-transparent">
            {{ $items->links() }}
        </div>
    @endif
</div>
@endsection
BLADE;

$migration = <<<'MIG'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_notifications')) {
            Schema::create('system_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('type')->default('info');
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->text('message')->nullable();
                $table->string('link')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->boolean('is_hidden')->default(false)->index();
                $table->timestamp('hidden_at')->nullable()->index();
                $table->timestamps();
            });
            return;
        }

        Schema::table('system_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('system_notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('link');
            }
            if (!Schema::hasColumn('system_notifications', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->index()->after('read_at');
            }
            if (!Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->index()->after('is_hidden');
            }
        });
    }

    public function down(): void
    {
        // لا نحذف الأعمدة حفاظاً على بيانات الإشعارات.
    }
};
MIG;

$routeBlock = <<<'ROUTES'

// Notification Center V5 stable compatibility routes
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [\App\Http\Controllers\NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationCenterController::class, 'readAll'])->name('notifications.read_all');
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\NotificationCenterController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/hide-read', [\App\Http\Controllers\NotificationCenterController::class, 'hideRead'])->name('notifications.hide-read');
    Route::post('/notifications/delete-hidden', [\App\Http\Controllers\NotificationCenterController::class, 'deleteHidden'])->name('notifications.delete-hidden');
    Route::post('/notifications/purge-hidden', [\App\Http\Controllers\NotificationCenterController::class, 'purgeHidden'])->name('notifications.purge-hidden');
    Route::post('/notifications/clear-hidden', [\App\Http\Controllers\NotificationCenterController::class, 'clearHidden'])->name('notifications.clear-hidden');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationCenterController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/{notification}/hide', [\App\Http\Controllers\NotificationCenterController::class, 'hide'])->name('notifications.hide');
    Route::delete('/notifications/{notification}', [\App\Http\Controllers\NotificationCenterController::class, 'destroy'])->name('notifications.destroy');
});
ROUTES;

write_file($root, 'app/Http/Controllers/NotificationCenterController.php', $controller);
write_file($root, 'resources/views/notifications/index.blade.php', $view);
write_file($root, 'database/migrations/2026_06_28_000050_stabilize_system_notifications_v5.php', $migration);

$routesPath = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
if (!is_file($routesPath)) {
    throw new RuntimeException('ملف routes/web.php غير موجود.');
}
$routes = file_get_contents($routesPath);
backup_file($root, $routesPath);
if (!str_contains($routes, 'Notification Center V5 stable compatibility routes')) {
    $routes .= $routeBlock . PHP_EOL;
    file_put_contents($routesPath, $routes);
}

echo "DONE: تم تطبيق إصلاح مركز الإشعارات V5 المستقر.\n";
echo "NEXT: php artisan migrate && composer dump-autoload && php artisan route:clear && php artisan view:clear && php artisan optimize:clear\n";

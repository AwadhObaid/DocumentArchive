<?php
/**
 * DocumentArchive Notification Management Update
 * Adds bulk notification actions: mark all as read, hide read, clear hidden.
 * Run from Laravel project root:
 *   php scripts/apply_notification_management_update.php
 */

declare(strict_types=1);

function project_root(): string
{
    $cwd = getcwd() ?: __DIR__;
    if (is_file($cwd . DIRECTORY_SEPARATOR . 'artisan')) {
        return $cwd;
    }
    $parent = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    if ($parent && is_file($parent . DIRECTORY_SEPARATOR . 'artisan')) {
        return $parent;
    }
    throw new RuntimeException('تعذر تحديد جذر مشروع Laravel. نفّذ السكربت من داخل جذر المشروع.');
}

function normalize(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function read_file(string $file): string
{
    if (!is_file($file)) {
        throw new RuntimeException('الملف غير موجود: ' . $file);
    }
    $content = file_get_contents($file);
    if ($content === false) {
        throw new RuntimeException('تعذر قراءة الملف: ' . $file);
    }
    return $content;
}

function write_file(string $file, string $content): void
{
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('تعذر إنشاء المجلد: ' . $dir);
    }
    if (file_put_contents($file, $content) === false) {
        throw new RuntimeException('تعذر كتابة الملف: ' . $file);
    }
}

function backup_once(string $file, string $tag): void
{
    if (!is_file($file)) {
        return;
    }
    $backupDir = dirname($file) . DIRECTORY_SEPARATOR . '_backup_' . $tag . '_' . date('Ymd_His');
    if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
        throw new RuntimeException('تعذر إنشاء مجلد النسخة الاحتياطية: ' . $backupDir);
    }
    copy($file, $backupDir . DIRECTORY_SEPARATOR . basename($file));
}

function insert_before_closing_brace(string $content, string $insert): string
{
    $pos = strrpos($content, '}');
    if ($pos === false) {
        throw new RuntimeException('تعذر العثور على نهاية الكلاس لإضافة الدوال.');
    }
    return substr($content, 0, $pos) . "\n" . $insert . "\n" . substr($content, $pos);
}

$root = project_root();
$tag = 'notification_management';

$routesFile = $root . normalize('/routes/web.php');
$controllerFile = $root . normalize('/app/Http/Controllers/NotificationCenterController.php');
$viewFile = $root . normalize('/resources/views/notifications/index.blade.php');
$cssFile = $root . normalize('/public/css/notification-management.css');

if (!is_file($routesFile)) {
    throw new RuntimeException('ملف routes/web.php غير موجود.');
}
if (!is_file($controllerFile)) {
    throw new RuntimeException('ملف NotificationCenterController.php غير موجود. تأكد أن مركز الإشعارات مركب.');
}
if (!is_file($viewFile)) {
    throw new RuntimeException('ملف resources/views/notifications/index.blade.php غير موجود.');
}

// 1) CSS
write_file($cssFile, <<<'CSS'
/* Notification center management actions */
.notification-management-panel {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0 0 18px 0;
    padding: 14px;
    border: 1px solid rgba(15, 23, 42, .10);
    border-radius: 16px;
    background: rgba(255, 255, 255, .86);
    box-shadow: 0 10px 25px rgba(15, 23, 42, .06);
}
.notification-management-panel .notification-management-title {
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 3px;
}
.notification-management-panel .notification-management-subtitle {
    color: #64748b;
    font-size: .9rem;
}
.notification-management-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.notification-management-actions form {
    display: inline-flex;
    margin: 0;
}
.notification-management-btn {
    border: 0;
    border-radius: 12px;
    padding: 9px 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    color: #0f172a;
    background: #f1f5f9;
    transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
}
.notification-management-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(15, 23, 42, .12);
}
.notification-management-btn.primary {
    color: #fff;
    background: #2563eb;
}
.notification-management-btn.warning {
    color: #78350f;
    background: #fef3c7;
}
.notification-management-btn.danger {
    color: #fff;
    background: #dc2626;
}
html.dark .notification-management-panel,
body.dark .notification-management-panel,
[data-theme="dark"] .notification-management-panel {
    background: rgba(15, 23, 42, .92);
    border-color: rgba(148, 163, 184, .22);
    box-shadow: 0 12px 30px rgba(0, 0, 0, .25);
}
html.dark .notification-management-panel .notification-management-title,
body.dark .notification-management-panel .notification-management-title,
[data-theme="dark"] .notification-management-panel .notification-management-title {
    color: #e5e7eb;
}
html.dark .notification-management-panel .notification-management-subtitle,
body.dark .notification-management-panel .notification-management-subtitle,
[data-theme="dark"] .notification-management-panel .notification-management-subtitle {
    color: #94a3b8;
}
html.dark .notification-management-btn,
body.dark .notification-management-btn,
[data-theme="dark"] .notification-management-btn {
    color: #e5e7eb;
    background: rgba(51, 65, 85, .9);
}
@media print {
    .notification-management-panel { display: none !important; }
}
CSS);

// 2) Routes
$routes = read_file($routesFile);
if (strpos($routes, 'notifications.mark-all-read') === false) {
    backup_once($routesFile, $tag);
    $routeBlock = <<<'PHP'

// Notification center management actions
Route::middleware(['auth'])->group(function () {
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\NotificationCenterController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::post('/notifications/hide-read', [\App\Http\Controllers\NotificationCenterController::class, 'hideRead'])->name('notifications.hide-read');
    Route::delete('/notifications/clear-hidden', [\App\Http\Controllers\NotificationCenterController::class, 'clearHidden'])->name('notifications.clear-hidden');
});
PHP;
    $routes = rtrim($routes) . $routeBlock . PHP_EOL;
    write_file($routesFile, $routes);
}

// 3) Controller methods and imports
$controller = read_file($controllerFile);
backup_once($controllerFile, $tag);

if (strpos($controller, 'use Illuminate\Support\Facades\DB;') === false) {
    $controller = preg_replace('/namespace\s+App\\Http\\Controllers;\s*/', "namespace App\\Http\\Controllers;\n\nuse Illuminate\\Support\\Facades\\DB;\nuse Illuminate\\Support\\Facades\\Schema;\n", $controller, 1) ?? $controller;
}
if (strpos($controller, 'use Illuminate\Support\Facades\Schema;') === false) {
    $controller = str_replace("use Illuminate\\Support\\Facades\\DB;\n", "use Illuminate\\Support\\Facades\\DB;\nuse Illuminate\\Support\\Facades\\Schema;\n", $controller);
}

if (strpos($controller, 'public function markAllRead(') === false) {
    $methods = <<<'PHP'

    /**
     * تعليم كل إشعارات المستخدم الحالي كمقروءة.
     */
    public function markAllRead()
    {
        $user = auth()->user();
        if (!$user || !Schema::hasTable('system_notifications')) {
            return back()->with('warning', 'تعذر تحديث الإشعارات حالياً.');
        }

        $updates = [];
        if (Schema::hasColumn('system_notifications', 'is_read')) {
            $updates['is_read'] = 1;
        }
        if (Schema::hasColumn('system_notifications', 'read_at')) {
            $updates['read_at'] = now();
        }
        if (empty($updates)) {
            return back()->with('info', 'لا يوجد عمود مخصص لحالة القراءة في جدول الإشعارات.');
        }

        $query = DB::table('system_notifications');
        if (Schema::hasColumn('system_notifications', 'user_id')) {
            $query->where('user_id', $user->id);
        }
        if (Schema::hasColumn('system_notifications', 'hidden_at')) {
            $query->whereNull('hidden_at');
        }
        if (Schema::hasColumn('system_notifications', 'is_hidden')) {
            $query->where(function ($q) {
                $q->whereNull('is_hidden')->orWhere('is_hidden', 0);
            });
        }

        $count = $query->update($updates);

        return back()->with('success', 'تم تعليم الإشعارات كمقروءة بنجاح.');
    }

    /**
     * إخفاء الإشعارات المقروءة من مركز الإشعارات دون حذفها نهائياً عند توفر عمود hidden_at.
     */
    public function hideRead()
    {
        $user = auth()->user();
        if (!$user || !Schema::hasTable('system_notifications')) {
            return back()->with('warning', 'تعذر تحديث الإشعارات حالياً.');
        }

        $updates = [];
        if (Schema::hasColumn('system_notifications', 'hidden_at')) {
            $updates['hidden_at'] = now();
        } elseif (Schema::hasColumn('system_notifications', 'is_hidden')) {
            $updates['is_hidden'] = 1;
        } else {
            return back()->with('warning', 'جدول الإشعارات لا يحتوي على عمود مخصص للإخفاء.');
        }

        $query = DB::table('system_notifications');
        if (Schema::hasColumn('system_notifications', 'user_id')) {
            $query->where('user_id', $user->id);
        }
        if (Schema::hasColumn('system_notifications', 'is_read')) {
            $query->where('is_read', 1);
        } elseif (Schema::hasColumn('system_notifications', 'read_at')) {
            $query->whereNotNull('read_at');
        }
        if (Schema::hasColumn('system_notifications', 'hidden_at')) {
            $query->whereNull('hidden_at');
        }
        if (Schema::hasColumn('system_notifications', 'is_hidden')) {
            $query->where(function ($q) {
                $q->whereNull('is_hidden')->orWhere('is_hidden', 0);
            });
        }

        $query->update($updates);

        return back()->with('success', 'تم إخفاء الإشعارات المقروءة.');
    }

    /**
     * حذف الإشعارات المخفية نهائياً للمستخدم الحالي.
     */
    public function clearHidden()
    {
        $user = auth()->user();
        if (!$user || !Schema::hasTable('system_notifications')) {
            return back()->with('warning', 'تعذر حذف الإشعارات حالياً.');
        }

        $query = DB::table('system_notifications');
        if (Schema::hasColumn('system_notifications', 'user_id')) {
            $query->where('user_id', $user->id);
        }

        if (Schema::hasColumn('system_notifications', 'hidden_at')) {
            $query->whereNotNull('hidden_at');
        } elseif (Schema::hasColumn('system_notifications', 'is_hidden')) {
            $query->where('is_hidden', 1);
        } else {
            return back()->with('warning', 'لا توجد إشعارات مخفية قابلة للحذف.');
        }

        $query->delete();

        return back()->with('success', 'تم حذف الإشعارات المخفية نهائياً.');
    }
PHP;
    $controller = insert_before_closing_brace($controller, $methods);
    write_file($controllerFile, $controller);
}

// 4) View panel and CSS link
$view = read_file($viewFile);
backup_once($viewFile, $tag);

if (strpos($view, 'notification-management.css') === false) {
    $cssLink = "\n<link rel=\"stylesheet\" href=\"{{ asset('css/notification-management.css') }}\">\n";
    if (preg_match('/@push\([\'\"]styles[\'\"]\)/', $view)) {
        $view = preg_replace('/@push\([\'\"]styles[\'\"]\)/', "$0" . $cssLink, $view, 1) ?? $view;
    } elseif (strpos($view, '@section') !== false) {
        $view = $cssLink . $view;
    } else {
        $view = $cssLink . $view;
    }
}

if (strpos($view, 'notification-management-panel') === false) {
    $panel = <<<'BLADE'

<div class="notification-management-panel">
    <div>
        <div class="notification-management-title">إدارة الإشعارات</div>
        <div class="notification-management-subtitle">إجراءات سريعة لتنظيم مركز الإشعارات دون التأثير على بيانات النظام.</div>
    </div>
    <div class="notification-management-actions">
        <form method="POST" action="{{ route('notifications.mark-all-read') }}">
            @csrf
            <button type="submit" class="notification-management-btn primary">تعليم الكل كمقروء</button>
        </form>
        <form method="POST" action="{{ route('notifications.hide-read') }}">
            @csrf
            <button type="submit" class="notification-management-btn warning">إخفاء المقروءة</button>
        </form>
        <form method="POST" action="{{ route('notifications.clear-hidden') }}" onsubmit="return confirm('سيتم حذف الإشعارات المخفية نهائياً. هل تريد المتابعة؟');">
            @csrf
            @method('DELETE')
            <button type="submit" class="notification-management-btn danger">حذف المخفية</button>
        </form>
        @if (Route::has('notification-settings.index'))
            <a href="{{ route('notification-settings.index') }}" class="notification-management-btn">إعدادات الإشعارات</a>
        @elseif (Route::has('notifications.settings'))
            <a href="{{ route('notifications.settings') }}" class="notification-management-btn">إعدادات الإشعارات</a>
        @endif
    </div>
</div>
BLADE;

    if (strpos($view, '@section') !== false && strpos($view, '@section(\'content\'') !== false || strpos($view, '@section("content"') !== false) {
        $view = preg_replace('/(@section\([\'\"]content[\'\"]\)\s*)/', "$1" . $panel . "\n", $view, 1) ?? ($panel . $view);
    } elseif (strpos($view, '@section(') !== false) {
        $view = preg_replace('/(@section\([^\)]*\)\s*)/', "$1" . $panel . "\n", $view, 1) ?? ($panel . $view);
    } else {
        $view = $panel . $view;
    }
}

write_file($viewFile, $view);

echo "DONE: تم إضافة أدوات إدارة الإشعارات بنجاح.\n";

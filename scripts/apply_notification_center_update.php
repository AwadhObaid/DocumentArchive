<?php

declare(strict_types=1);

function projectRoot(): string
{
    $dir = realpath(__DIR__ . '/..');
    while ($dir && $dir !== dirname($dir)) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan')) {
            return $dir;
        }
        $dir = dirname($dir);
    }

    $cwd = getcwd();
    if ($cwd && is_file($cwd . DIRECTORY_SEPARATOR . 'artisan')) {
        return $cwd;
    }

    throw new RuntimeException('تعذر تحديد جذر مشروع Laravel. شغّل السكربت من داخل مجلد المشروع الذي يحتوي ملف artisan.');
}

function ensureDir(string $path): void
{
    if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
        throw new RuntimeException('تعذر إنشاء المجلد: ' . $path);
    }
}

function writeFileIfChanged(string $path, string $content): void
{
    ensureDir(dirname($path));
    if (is_file($path) && file_get_contents($path) === $content) {
        echo "UNCHANGED: {$path}\n";
        return;
    }
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('تعذر كتابة الملف: ' . $path);
    }
    echo "WROTE: {$path}\n";
}

function insertBeforeBodyClose(string $file, string $snippet, string $marker): void
{
    if (! is_file($file)) {
        echo "WARN: ملف layout غير موجود: {$file}\n";
        return;
    }

    $content = file_get_contents($file);
    if ($content === false) {
        throw new RuntimeException('تعذر قراءة الملف: ' . $file);
    }

    if (str_contains($content, $marker) || str_contains($content, "partials.notification-center")) {
        echo "SKIP: include موجود مسبقاً داخل {$file}\n";
        return;
    }

    if (stripos($content, '</body>') !== false) {
        $content = preg_replace('/<\/body>/i', $snippet . "\n</body>", $content, 1);
    } else {
        $content .= "\n" . $snippet . "\n";
    }

    file_put_contents($file, $content);
    echo "UPDATED: {$file}\n";
}

function appendRoutes(string $file): void
{
    if (! is_file($file)) {
        throw new RuntimeException('ملف routes/web.php غير موجود.');
    }

    $content = file_get_contents($file);
    if ($content === false) {
        throw new RuntimeException('تعذر قراءة routes/web.php');
    }

    if (! str_contains($content, 'NotificationCenterController')) {
        $use = "use App\\Http\\Controllers\\NotificationCenterController;\n";
        $firstUsePos = strpos($content, 'use ');
        if ($firstUsePos !== false) {
            $lastUsePos = 0;
            if (preg_match_all('/^use\s+[^;]+;\s*$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $last = end($matches[0]);
                $lastUsePos = $last[1] + strlen($last[0]);
                $content = substr($content, 0, $lastUsePos) . "\n" . $use . substr($content, $lastUsePos);
            } else {
                $content = $use . $content;
            }
        } else {
            $content = "<?php\n\n" . $use . preg_replace('/^<\?php\s*/', '', $content);
        }
    }

    if (! str_contains($content, "notifications.index") && ! str_contains($content, "notification-center-routes")) {
        $routes = <<<'PHP_ROUTES'

// notification-center-routes
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationCenterController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationCenterController::class, 'markAllAsRead'])->name('notifications.read_all');
    Route::delete('/notifications/{notification}', [NotificationCenterController::class, 'destroy'])->name('notifications.destroy');
});
PHP_ROUTES;
        $content = rtrim($content) . "\n" . $routes . "\n";
    }

    file_put_contents($file, $content);
    echo "UPDATED: {$file}\n";
}

$root = projectRoot();
echo "Project root: {$root}\n";

$model = <<<'PHP_MODEL'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'unique_key',
        'type',
        'title',
        'body',
        'link',
        'source',
        'payload',
        'read_at',
        'dismissed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisible($query)
    {
        return $query->whereNull('dismissed_at');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at')->whereNull('dismissed_at');
    }
}
PHP_MODEL;

$service = <<<'PHP_SERVICE'
<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemNotificationService
{
    public static function createForUser(User $user, string $title, ?string $body = null, string $type = 'info', ?string $link = null, ?string $uniqueKey = null, string $source = 'manual', array $payload = []): ?SystemNotification
    {
        if (! Schema::hasTable('system_notifications')) {
            return null;
        }

        $data = [
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'source' => $source,
            'payload' => $payload,
            'dismissed_at' => null,
        ];

        if ($uniqueKey) {
            $notification = SystemNotification::updateOrCreate(
                ['user_id' => $user->id, 'unique_key' => $uniqueKey],
                $data + ['unique_key' => $uniqueKey]
            );

            if ($notification->wasChanged(['title', 'body', 'type', 'link', 'payload', 'dismissed_at'])) {
                $notification->read_at = null;
                $notification->save();
            }

            return $notification;
        }

        return SystemNotification::create($data);
    }

    public static function syncForCurrentUser(?User $user): void
    {
        if (! $user || ! Schema::hasTable('system_notifications')) {
            return;
        }

        if (! self::isAdmin($user)) {
            return;
        }

        try {
            $activeKeys = [];

            foreach (self::buildSystemAlerts() as $alert) {
                $key = 'system:' . $alert['key'];
                $activeKeys[] = $key;

                self::createForUser(
                    user: $user,
                    title: $alert['title'],
                    body: $alert['body'] ?? null,
                    type: $alert['type'] ?? 'warning',
                    link: $alert['link'] ?? null,
                    uniqueKey: $key,
                    source: 'system_monitor',
                    payload: $alert['payload'] ?? []
                );
            }

            SystemNotification::where('user_id', $user->id)
                ->where('source', 'system_monitor')
                ->whereNotIn('unique_key', $activeKeys)
                ->whereNull('dismissed_at')
                ->update([
                    'dismissed_at' => now(),
                    'read_at' => now(),
                ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public static function buildSystemAlerts(): array
    {
        $alerts = [];

        if (Schema::hasTable('documents') && Schema::hasTable('document_attachments')) {
            $withoutAttachments = DB::table('documents')
                ->whereNull('documents.deleted_at')
                ->leftJoin('document_attachments', 'documents.id', '=', 'document_attachments.document_id')
                ->whereNull('document_attachments.id')
                ->count();

            if ($withoutAttachments > 0) {
                $alerts[] = [
                    'key' => 'documents_without_attachments',
                    'type' => 'warning',
                    'title' => 'كتب بدون مرفقات',
                    'body' => "يوجد {$withoutAttachments} كتاب لم يتم رفع مرفقات لها بعد.",
                    'link' => url('/data-quality'),
                    'payload' => ['count' => $withoutAttachments],
                ];
            }
        }

        if (Schema::hasTable('documents')) {
            foreach ([
                'main_policy_number' => ['key' => 'duplicate_main_policy', 'title' => 'بوالص رئيسية مكررة'],
                'sub_policy_number' => ['key' => 'duplicate_sub_policy', 'title' => 'بوالص فرعية مكررة'],
            ] as $column => $meta) {
                if (! Schema::hasColumn('documents', $column)) {
                    continue;
                }

                $duplicates = DB::table('documents')
                    ->select($column)
                    ->whereNull('deleted_at')
                    ->whereNotNull($column)
                    ->where($column, '<>', '')
                    ->groupBy($column)
                    ->havingRaw('COUNT(*) > 1')
                    ->count();

                if ($duplicates > 0) {
                    $alerts[] = [
                        'key' => $meta['key'],
                        'type' => 'warning',
                        'title' => $meta['title'],
                        'body' => "يوجد {$duplicates} رقم بوليصة مكرر يحتاج مراجعة.",
                        'link' => url('/data-quality'),
                        'payload' => ['count' => $duplicates],
                    ];
                }
            }

            if (Schema::hasColumn('documents', 'deleted_at')) {
                $trashed = DB::table('documents')->whereNotNull('deleted_at')->count();
                if ($trashed > 0) {
                    $alerts[] = [
                        'key' => 'trashed_documents',
                        'type' => 'info',
                        'title' => 'كتب في سلة المحذوفات',
                        'body' => "يوجد {$trashed} كتاب في سلة المحذوفات.",
                        'link' => url('/documents/trash'),
                        'payload' => ['count' => $trashed],
                    ];
                }
            }
        }

        $backupDir = storage_path('app/private/backups');
        if (! is_dir($backupDir)) {
            $alerts[] = [
                'key' => 'backup_folder_missing',
                'type' => 'danger',
                'title' => 'مجلد النسخ الاحتياطي غير موجود',
                'body' => 'يرجى مراجعة صفحة النسخ الاحتياطي وإنشاء نسخة جديدة.',
                'link' => url('/backups'),
            ];
        } else {
            $files = collect(File::glob($backupDir . DIRECTORY_SEPARATOR . '*.zip'))
                ->filter(fn ($file) => is_file($file));

            if ($files->isEmpty()) {
                $alerts[] = [
                    'key' => 'no_backup_found',
                    'type' => 'danger',
                    'title' => 'لا توجد نسخة احتياطية',
                    'body' => 'لم يتم العثور على أي نسخة احتياطية. يفضل إنشاء نسخة كاملة الآن.',
                    'link' => url('/backups'),
                ];
            } else {
                $latest = $files->sortByDesc(fn ($file) => filemtime($file))->first();
                $latestTime = Carbon::createFromTimestamp(filemtime($latest));
                if ($latestTime->lt(now()->subDays(7))) {
                    $alerts[] = [
                        'key' => 'old_backup_found',
                        'type' => 'warning',
                        'title' => 'آخر نسخة احتياطية قديمة',
                        'body' => 'آخر نسخة احتياطية أقدم من 7 أيام. يفضل إنشاء نسخة حديثة.',
                        'link' => url('/backups'),
                        'payload' => ['latest_backup_at' => $latestTime->toDateTimeString()],
                    ];
                }
            }
        }

        if (config('app.debug') === true) {
            $alerts[] = [
                'key' => 'app_debug_enabled',
                'type' => 'info',
                'title' => 'وضع التطوير مفعل',
                'body' => 'APP_DEBUG=true مناسب للتطوير فقط، ويجب جعله false عند التشغيل النهائي.',
                'link' => url('/system-health'),
            ];
        }

        return $alerts;
    }

    private static function isAdmin(User $user): bool
    {
        $role = (string) ($user->role ?? '');

        return $role === 'admin'
            || str_contains($role, 'مدير')
            || method_exists($user, 'hasPermission') && $user->hasPermission('backups.view');
    }
}
PHP_SERVICE;

$controller = <<<'PHP_CONTROLLER'
<?php

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function index(Request $request): View
    {
        SystemNotificationService::syncForCurrentUser($request->user());

        $notifications = SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->visible()
            ->latest()
            ->paginate(20);

        $unreadCount = SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->unread()
            ->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markAsRead(Request $request, SystemNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);

        $notification->update(['read_at' => now()]);

        return back()->with('success', 'تم تعليم الإشعار كمقروء.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return back()->with('success', 'تم تعليم جميع الإشعارات كمقروءة.');
    }

    public function destroy(Request $request, SystemNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);

        $notification->update([
            'dismissed_at' => now(),
            'read_at' => $notification->read_at ?: now(),
        ]);

        return back()->with('success', 'تم إخفاء الإشعار.');
    }
}
PHP_CONTROLLER;

$migration = <<<'PHP_MIGRATION'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_notifications')) {
            return;
        }

        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unique_key')->nullable();
            $table->string('type')->default('info');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link')->nullable();
            $table->string('source')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'dismissed_at']);
            $table->unique(['user_id', 'unique_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
PHP_MIGRATION;

$partial = <<<'BLADE_PARTIAL'
@php
    use App\Models\SystemNotification;
    use App\Services\SystemNotificationService;
    use Illuminate\Support\Facades\Schema;

    $notificationCenterReady = auth()->check() && Schema::hasTable('system_notifications');
    $notificationCenterItems = collect();
    $notificationCenterUnread = 0;

    if ($notificationCenterReady) {
        try {
            SystemNotificationService::syncForCurrentUser(auth()->user());
            $notificationCenterItems = SystemNotification::query()
                ->where('user_id', auth()->id())
                ->visible()
                ->latest()
                ->limit(6)
                ->get();
            $notificationCenterUnread = SystemNotification::query()
                ->where('user_id', auth()->id())
                ->unread()
                ->count();
        } catch (Throwable $e) {
            report($e);
            $notificationCenterReady = false;
        }
    }
@endphp

@if($notificationCenterReady)
    <style>
        .notification-center-floating {
            position: fixed;
            left: 22px;
            bottom: 22px;
            z-index: 9998;
            direction: rtl;
            font-family: inherit;
        }
        .notification-center-btn {
            width: 54px;
            height: 54px;
            border: 1px solid rgba(148, 163, 184, .35);
            border-radius: 18px;
            background: #2563eb;
            color: #fff;
            box-shadow: 0 16px 35px rgba(37, 99, 235, .35);
            cursor: pointer;
            font-size: 23px;
            position: relative;
        }
        .notification-center-count {
            position: absolute;
            top: -7px;
            right: -7px;
            min-width: 24px;
            height: 24px;
            padding: 0 6px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
            border: 2px solid #0f172a;
        }
        .notification-center-panel {
            position: absolute;
            left: 0;
            bottom: 66px;
            width: min(390px, calc(100vw - 28px));
            max-height: min(540px, calc(100vh - 120px));
            overflow: hidden;
            border-radius: 22px;
            background: #0f172a;
            color: #e5e7eb;
            border: 1px solid rgba(148, 163, 184, .28);
            box-shadow: 0 24px 70px rgba(0, 0, 0, .42);
            display: none;
        }
        .notification-center-panel.is-open { display: block; }
        .notification-center-header {
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(148, 163, 184, .20);
            background: rgba(15, 23, 42, .96);
        }
        .notification-center-title { font-weight: 900; font-size: 16px; }
        .notification-center-link { color: #93c5fd; text-decoration: none; font-size: 12px; font-weight: 700; }
        .notification-center-body { max-height: 410px; overflow: auto; padding: 10px; }
        .notification-center-item {
            display: block;
            padding: 12px;
            border-radius: 16px;
            color: inherit;
            text-decoration: none;
            background: rgba(30, 41, 59, .75);
            border: 1px solid rgba(148, 163, 184, .14);
            margin-bottom: 8px;
        }
        .notification-center-item.unread { border-color: rgba(59, 130, 246, .45); background: rgba(30, 64, 175, .20); }
        .notification-center-item-title { font-weight: 900; font-size: 14px; margin-bottom: 4px; display:flex; gap:8px; align-items:center; }
        .notification-center-dot { width: 9px; height: 9px; border-radius: 999px; background: #60a5fa; flex: 0 0 auto; }
        .notification-center-dot.warning { background: #f59e0b; }
        .notification-center-dot.danger { background: #ef4444; }
        .notification-center-dot.success { background: #22c55e; }
        .notification-center-item-body { color: #cbd5e1; font-size: 12px; line-height: 1.7; }
        .notification-center-time { color: #94a3b8; font-size: 11px; margin-top: 6px; }
        .notification-center-empty { padding: 28px 14px; text-align: center; color: #94a3b8; }
        .notification-center-footer { padding: 12px; border-top: 1px solid rgba(148, 163, 184, .20); display: flex; gap: 8px; }
        .notification-center-footer form { margin: 0; flex: 1; }
        .notification-center-small-btn {
            width: 100%; border: 0; border-radius: 12px; padding: 9px 10px; cursor: pointer;
            background: rgba(59, 130, 246, .18); color: #bfdbfe; font-weight: 800;
        }
        @media print { .notification-center-floating { display: none !important; } }
    </style>

    <div class="notification-center-floating" data-notification-center>
        <button type="button" class="notification-center-btn" data-notification-toggle aria-label="مركز الإشعارات">
            🔔
            @if($notificationCenterUnread > 0)
                <span class="notification-center-count">{{ $notificationCenterUnread > 99 ? '99+' : $notificationCenterUnread }}</span>
            @endif
        </button>

        <div class="notification-center-panel" data-notification-panel>
            <div class="notification-center-header">
                <div class="notification-center-title">مركز الإشعارات</div>
                <a class="notification-center-link" href="{{ route('notifications.index') }}">عرض الكل</a>
            </div>
            <div class="notification-center-body">
                @forelse($notificationCenterItems as $item)
                    <a class="notification-center-item {{ $item->read_at ? '' : 'unread' }}" href="{{ $item->link ?: route('notifications.index') }}">
                        <div class="notification-center-item-title">
                            <span class="notification-center-dot {{ $item->type }}"></span>
                            <span>{{ $item->title }}</span>
                        </div>
                        @if($item->body)
                            <div class="notification-center-item-body">{{ $item->body }}</div>
                        @endif
                        <div class="notification-center-time">{{ optional($item->created_at)->diffForHumans() }}</div>
                    </a>
                @empty
                    <div class="notification-center-empty">لا توجد إشعارات حالياً.</div>
                @endforelse
            </div>
            <div class="notification-center-footer">
                <form method="POST" action="{{ route('notifications.read_all') }}">
                    @csrf
                    <button class="notification-center-small-btn" type="submit">تعليم الكل كمقروء</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const root = document.querySelector('[data-notification-center]');
            if (!root || root.dataset.ready === '1') return;
            root.dataset.ready = '1';
            const button = root.querySelector('[data-notification-toggle]');
            const panel = root.querySelector('[data-notification-panel]');
            button && button.addEventListener('click', function (event) {
                event.stopPropagation();
                panel && panel.classList.toggle('is-open');
            });
            document.addEventListener('click', function (event) {
                if (panel && !root.contains(event.target)) {
                    panel.classList.remove('is-open');
                }
            });
        })();
    </script>
@endif
BLADE_PARTIAL;

$indexView = <<<'BLADE_INDEX'
@extends('layouts.app')

@section('content')
<div class="page-header" style="margin-bottom: 20px;">
    <h1>🔔 مركز الإشعارات</h1>
    <p>متابعة التنبيهات المهمة الخاصة بالنظام وجودة البيانات والنسخ الاحتياطي.</p>
</div>

<div class="card" style="padding: 18px; margin-bottom: 18px;">
    <div style="display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
        <strong>الإشعارات غير المقروءة: {{ $unreadCount }}</strong>
        <form method="POST" action="{{ route('notifications.read_all') }}" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-primary">تعليم الكل كمقروء</button>
        </form>
    </div>
</div>

<div class="card" style="padding: 0; overflow:hidden;">
    @forelse($notifications as $notification)
        <div style="padding:16px 18px; border-bottom:1px solid rgba(148,163,184,.22); {{ $notification->read_at ? '' : 'background:rgba(37,99,235,.10);' }}">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;">
                <div style="flex:1; min-width:240px;">
                    <div style="font-weight:900; font-size:16px; margin-bottom:6px;">
                        @if(!$notification->read_at)<span style="color:#60a5fa;">●</span>@endif
                        {{ $notification->title }}
                    </div>
                    @if($notification->body)
                        <div style="color:var(--muted, #94a3b8); line-height:1.8;">{{ $notification->body }}</div>
                    @endif
                    <div style="font-size:12px; color:var(--muted, #94a3b8); margin-top:7px;">
                        {{ optional($notification->created_at)->format('Y-m-d H:i') }}
                    </div>
                </div>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    @if($notification->link)
                        <a href="{{ $notification->link }}" class="btn btn-secondary">فتح</a>
                    @endif
                    @if(!$notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}" style="margin:0;">
                            @csrf
                            <button class="btn btn-primary" type="submit">مقروء</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('notifications.destroy', $notification) }}" style="margin:0;">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger" type="submit" onclick="return confirm('هل تريد إخفاء هذا الإشعار؟')">إخفاء</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div style="padding: 38px; text-align:center; color:var(--muted, #94a3b8);">
            لا توجد إشعارات حالياً.
        </div>
    @endforelse
</div>

<div style="margin-top:16px;">
    {{ $notifications->links() }}
</div>
@endsection
BLADE_INDEX;

$check = <<<'PHP_CHECK'
<?php

declare(strict_types=1);

$root = getcwd();
$errors = [];

$required = [
    'app/Models/SystemNotification.php',
    'app/Services/SystemNotificationService.php',
    'app/Http/Controllers/NotificationCenterController.php',
    'resources/views/partials/notification-center.blade.php',
    'resources/views/notifications/index.blade.php',
];

foreach ($required as $file) {
    if (! is_file($root . DIRECTORY_SEPARATOR . $file)) {
        $errors[] = "ملف مفقود: {$file}";
    }
}

$migrations = glob($root . '/database/migrations/*create_system_notifications_table.php');
if (! $migrations) {
    $errors[] = 'Migration إنشاء جدول system_notifications غير موجود.';
}

$routes = is_file($root . '/routes/web.php') ? file_get_contents($root . '/routes/web.php') : '';
if (! str_contains($routes, 'notifications.index')) {
    $errors[] = 'مسارات مركز الإشعارات غير موجودة في routes/web.php.';
}

$layout = is_file($root . '/resources/views/layouts/app.blade.php') ? file_get_contents($root . '/resources/views/layouts/app.blade.php') : '';
if (! str_contains($layout, 'partials.notification-center')) {
    $errors[] = 'تضمين مركز الإشعارات غير موجود داخل layout.';
}

if ($errors) {
    echo "ERROR: لم يكتمل تحديث مركز الإشعارات.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: تحديث مركز الإشعارات مكتمل.\n";
PHP_CHECK;

writeFileIfChanged($root . '/app/Models/SystemNotification.php', $model);
writeFileIfChanged($root . '/app/Services/SystemNotificationService.php', $service);
writeFileIfChanged($root . '/app/Http/Controllers/NotificationCenterController.php', $controller);
writeFileIfChanged($root . '/database/migrations/2026_06_28_000001_create_system_notifications_table.php', $migration);
writeFileIfChanged($root . '/resources/views/partials/notification-center.blade.php', $partial);
writeFileIfChanged($root . '/resources/views/notifications/index.blade.php', $indexView);
writeFileIfChanged($root . '/scripts/check_notification_center_update.php', $check);

appendRoutes($root . '/routes/web.php');

$layout = $root . '/resources/views/layouts/app.blade.php';
$snippet = "    {{-- notification-center-include --}}\n    @include('partials.notification-center')";
insertBeforeBodyClose($layout, $snippet, 'notification-center-include');

echo "\nDONE: تم تركيب مركز الإشعارات. نفّذ الآن:\n";
echo "php artisan migrate\n";
echo "composer dump-autoload\n";
echo "php artisan route:clear\nphp artisan view:clear\nphp artisan optimize:clear\n";
echo "php scripts/check_notification_center_update.php\n";

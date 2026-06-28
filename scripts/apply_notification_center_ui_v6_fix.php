<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false || !is_file($root . DIRECTORY_SEPARATOR . 'artisan')) {
    $candidate = getcwd();
    if (is_file($candidate . DIRECTORY_SEPARATOR . 'artisan')) {
        $root = $candidate;
    }
}

if ($root === false || !is_file($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: تأكد أنك تشغل السكربت من جذر مشروع Laravel.\n");
    exit(1);
}

$viewDir = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'notifications';
if (!is_dir($viewDir) && !mkdir($viewDir, 0777, true) && !is_dir($viewDir)) {
    fwrite(STDERR, "ERROR: تعذر إنشاء مجلد views/notifications.\n");
    exit(1);
}

$viewPath = $viewDir . DIRECTORY_SEPARATOR . 'index.blade.php';
$view = <<<'BLADE'
@extends('layouts.app')

@section('title', 'مركز الإشعارات')

@section('content')
@php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;

    $notificationItems = collect($notifications ?? []);
    $isPaginator = is_object($notifications ?? null) && method_exists($notifications, 'links');

    $totalValue = $totalCount ?? $total ?? $notificationItems->count();
    $unreadValue = $unreadCount ?? $unread ?? 0;
    $readValue = $readCount ?? $read ?? 0;
    $hiddenValue = $hiddenCount ?? $hidden ?? 0;

    $urlForFirstAvailableRoute = function (array $routeNames, string $fallback, array $params = []) {
        foreach ($routeNames as $routeName) {
            if (Route::has($routeName)) {
                return route($routeName, $params);
            }
        }
        return url($fallback);
    };

    $markAllReadUrl = $urlForFirstAvailableRoute([
        'notifications.read_all',
        'notifications.read-all',
        'notifications.mark-all-read',
        'notifications.mark_all_read',
        'notifications.readAll',
    ], '/notifications/read-all');

    $hideReadUrl = $urlForFirstAvailableRoute([
        'notifications.hide-read',
        'notifications.hide_read',
        'notifications.hideRead',
    ], '/notifications/hide-read');

    $deleteHiddenUrl = $urlForFirstAvailableRoute([
        'notifications.delete-hidden',
        'notifications.delete_hidden',
        'notifications.purge-hidden',
        'notifications.clear-hidden',
        'notifications.deleteHidden',
    ], '/notifications/delete-hidden');

    $settingsUrl = Route::has('notification-settings.index')
        ? route('notification-settings.index')
        : (Route::has('notifications.settings') ? route('notifications.settings') : url('/notification-settings'));

    $safeBadgeClass = function ($type) {
        $type = strtolower((string) $type);
        return match (true) {
            str_contains($type, 'error'), str_contains($type, 'danger'), str_contains($type, 'delete') => 'danger',
            str_contains($type, 'warning'), str_contains($type, 'backup'), str_contains($type, 'duplicate') => 'warning',
            str_contains($type, 'success'), str_contains($type, 'created'), str_contains($type, 'updated'), str_contains($type, 'restored') => 'success',
            default => 'info',
        };
    };

    $formatDate = function ($value) {
        if (!$value) {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    };
@endphp

<style>
    /* notification-center-ui-v6 */
    .notification-center-page {
        display: grid;
        gap: 18px;
        direction: rtl;
    }

    .notification-hero {
        background: linear-gradient(135deg, rgba(37, 99, 235, .12), rgba(14, 165, 233, .06));
        border: 1px solid rgba(148, 163, 184, .20);
        border-radius: 22px;
        padding: 24px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
    }

    .notification-hero-title {
        margin: 0;
        font-size: clamp(1.55rem, 3vw, 2.3rem);
        font-weight: 900;
        color: var(--text-primary, #f8fafc);
        letter-spacing: -0.03em;
    }

    .notification-hero-subtitle {
        margin: 8px 0 0;
        color: var(--text-muted, #94a3b8);
        font-weight: 700;
        line-height: 1.8;
    }

    .notification-actions-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        justify-content: flex-end;
    }

    .notification-actions-toolbar form {
        margin: 0;
    }

    .notification-btn {
        border: 0;
        border-radius: 12px;
        padding: 10px 14px;
        font-weight: 900;
        font-size: .88rem;
        line-height: 1;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 40px;
        transition: transform .15s ease, opacity .15s ease, box-shadow .15s ease;
        white-space: nowrap;
    }

    .notification-btn:hover {
        transform: translateY(-1px);
        opacity: .95;
    }

    .notification-btn-primary { background: #2563eb; color: #fff; }
    .notification-btn-muted { background: rgba(148, 163, 184, .16); color: var(--text-primary, #f8fafc); border: 1px solid rgba(148, 163, 184, .20); }
    .notification-btn-warning { background: #f59e0b; color: #111827; }
    .notification-btn-danger { background: #dc2626; color: #fff; }
    .notification-btn-light { background: #f8fafc; color: #0f172a; }

    .notification-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .notification-stat-card {
        background: rgba(15, 23, 42, .70);
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 18px;
        padding: 18px;
        min-height: 110px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 12px 30px rgba(0, 0, 0, .12);
    }

    .notification-stat-label {
        color: var(--text-muted, #94a3b8);
        font-weight: 800;
        font-size: .9rem;
    }

    .notification-stat-value {
        color: var(--text-primary, #f8fafc);
        font-size: 1.85rem;
        font-weight: 950;
        line-height: 1;
    }

    .notification-list-panel {
        background: rgba(15, 23, 42, .70);
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 22px;
        padding: 18px;
        overflow: hidden;
    }

    .notification-list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding-bottom: 14px;
        margin-bottom: 14px;
        border-bottom: 1px solid rgba(148, 163, 184, .16);
    }

    .notification-list-title {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 950;
        color: var(--text-primary, #f8fafc);
    }

    .notification-list-meta {
        color: var(--text-muted, #94a3b8);
        font-size: .85rem;
        font-weight: 800;
    }

    .notification-items {
        display: grid;
        gap: 12px;
    }

    .notification-item-card {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: 14px;
        align-items: start;
        background: rgba(2, 6, 23, .35);
        border: 1px solid rgba(148, 163, 184, .14);
        border-radius: 18px;
        padding: 16px;
        position: relative;
    }

    .notification-item-card.is-unread {
        border-color: rgba(59, 130, 246, .55);
        box-shadow: inset 4px 0 0 rgba(59, 130, 246, .85);
    }

    .notification-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        background: rgba(59, 130, 246, .14);
        border: 1px solid rgba(59, 130, 246, .24);
    }

    .notification-content {
        min-width: 0;
    }

    .notification-title-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-bottom: 7px;
    }

    .notification-title {
        margin: 0;
        color: var(--text-primary, #f8fafc);
        font-size: 1rem;
        font-weight: 950;
        line-height: 1.6;
    }

    .notification-message {
        margin: 0;
        color: var(--text-muted, #cbd5e1);
        font-weight: 700;
        line-height: 1.8;
        overflow-wrap: anywhere;
    }

    .notification-date {
        margin-top: 9px;
        color: #93c5fd;
        font-size: .82rem;
        font-weight: 800;
    }

    .notification-type-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 950;
        direction: ltr;
        unicode-bidi: plaintext;
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .notification-type-badge.success { background: rgba(34, 197, 94, .16); color: #86efac; border: 1px solid rgba(34, 197, 94, .28); }
    .notification-type-badge.warning { background: rgba(245, 158, 11, .16); color: #fcd34d; border: 1px solid rgba(245, 158, 11, .28); }
    .notification-type-badge.danger { background: rgba(239, 68, 68, .16); color: #fca5a5; border: 1px solid rgba(239, 68, 68, .28); }
    .notification-type-badge.info { background: rgba(59, 130, 246, .16); color: #93c5fd; border: 1px solid rgba(59, 130, 246, .28); }

    .notification-item-actions {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-start;
        gap: 8px;
        min-width: 185px;
    }

    .notification-item-actions form {
        margin: 0;
    }

    .notification-empty-state {
        text-align: center;
        padding: 46px 18px;
        color: var(--text-muted, #94a3b8);
        font-weight: 800;
        line-height: 1.9;
    }

    .notification-pagination {
        margin-top: 16px;
    }

    body:not(.dark) .notification-hero,
    body:not(.dark) .notification-stat-card,
    body:not(.dark) .notification-list-panel,
    body:not(.dark) .notification-item-card {
        background: #fff;
        border-color: #e2e8f0;
        color: #0f172a;
        box-shadow: 0 10px 26px rgba(15, 23, 42, .06);
    }

    body:not(.dark) .notification-hero-title,
    body:not(.dark) .notification-stat-value,
    body:not(.dark) .notification-list-title,
    body:not(.dark) .notification-title {
        color: #0f172a;
    }

    body:not(.dark) .notification-hero-subtitle,
    body:not(.dark) .notification-stat-label,
    body:not(.dark) .notification-list-meta,
    body:not(.dark) .notification-message,
    body:not(.dark) .notification-empty-state {
        color: #64748b;
    }

    @media (max-width: 1100px) {
        .notification-stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .notification-hero { grid-template-columns: 1fr; }
        .notification-actions-toolbar { justify-content: stretch; }
        .notification-actions-toolbar .notification-btn,
        .notification-actions-toolbar form { width: 100%; }
    }

    @media (max-width: 720px) {
        .notification-stats-grid { grid-template-columns: 1fr; }
        .notification-item-card { grid-template-columns: 1fr; }
        .notification-item-actions { min-width: 0; width: 100%; justify-content: stretch; }
        .notification-item-actions .notification-btn,
        .notification-item-actions form { flex: 1 1 auto; }
    }

    @media print {
        .notification-actions-toolbar,
        .notification-item-actions,
        .notification-pagination { display: none !important; }
        .notification-center-page { color: #111827; }
        .notification-hero,
        .notification-stat-card,
        .notification-list-panel,
        .notification-item-card { background: #fff !important; color: #111827 !important; box-shadow: none !important; border-color: #d1d5db !important; }
    }
</style>

<div class="notification-center-page">
    <section class="notification-hero">
        <div>
            <h1 class="notification-hero-title">🔔 مركز الإشعارات</h1>
            <p class="notification-hero-subtitle">متابعة تنبيهات النظام والعمليات المهمة بشكل مرتب وواضح.</p>
        </div>

        <div class="notification-actions-toolbar" aria-label="إجراءات مركز الإشعارات">
            <a class="notification-btn notification-btn-muted" href="{{ $settingsUrl }}">⚙️ إعدادات الإشعارات</a>

            <form method="POST" action="{{ $markAllReadUrl }}">
                @csrf
                <button type="submit" class="notification-btn notification-btn-primary">✅ تعليم الكل كمقروءة</button>
            </form>

            <form method="POST" action="{{ $hideReadUrl }}">
                @csrf
                <button type="submit" class="notification-btn notification-btn-warning">🙈 إخفاء المقروء</button>
            </form>

            <form method="POST" action="{{ $deleteHiddenUrl }}" onsubmit="return confirm('سيتم حذف الإشعارات المخفية نهائياً. هل تريد المتابعة؟')">
                @csrf
                @method('DELETE')
                <button type="submit" class="notification-btn notification-btn-danger">🗑️ حذف المخفية</button>
            </form>
        </div>
    </section>

    <section class="notification-stats-grid" aria-label="ملخص الإشعارات">
        <article class="notification-stat-card">
            <span class="notification-stat-label">الإجمالي</span>
            <strong class="notification-stat-value">{{ number_format((int) $totalValue) }}</strong>
        </article>
        <article class="notification-stat-card">
            <span class="notification-stat-label">غير مقروءة</span>
            <strong class="notification-stat-value">{{ number_format((int) $unreadValue) }}</strong>
        </article>
        <article class="notification-stat-card">
            <span class="notification-stat-label">مقروءة</span>
            <strong class="notification-stat-value">{{ number_format((int) $readValue) }}</strong>
        </article>
        <article class="notification-stat-card">
            <span class="notification-stat-label">مخفية</span>
            <strong class="notification-stat-value">{{ number_format((int) $hiddenValue) }}</strong>
        </article>
    </section>

    <section class="notification-list-panel">
        <header class="notification-list-header">
            <h2 class="notification-list-title">قائمة الإشعارات</h2>
            <span class="notification-list-meta">{{ number_format($notificationItems->count()) }} عنصر ظاهر في هذه الصفحة</span>
        </header>

        @if($notificationItems->isEmpty())
            <div class="notification-empty-state">
                لا توجد إشعارات حالياً.<br>
                عندما تحدث عمليات مهمة داخل النظام ستظهر هنا تلقائياً.
            </div>
        @else
            <div class="notification-items">
                @foreach($notificationItems as $notification)
                    @php
                        $notificationId = data_get($notification, 'id');
                        $type = data_get($notification, 'type') ?? data_get($notification, 'category') ?? 'info';
                        $title = data_get($notification, 'title')
                            ?: data_get($notification, 'subject')
                            ?: data_get($notification, 'name')
                            ?: 'إشعار النظام';
                        $message = data_get($notification, 'message')
                            ?: data_get($notification, 'body')
                            ?: data_get($notification, 'description')
                            ?: '';
                        $link = data_get($notification, 'link') ?: data_get($notification, 'url');
                        $readAt = data_get($notification, 'read_at');
                        $createdAt = data_get($notification, 'created_at');
                        $badgeClass = $safeBadgeClass($type);
                        $isUnread = empty($readAt);

                        $markOneReadUrl = $notificationId
                            ? $urlForFirstAvailableRoute([
                                'notifications.read',
                                'notifications.mark-read',
                                'notifications.mark_read',
                                'notifications.markOneRead',
                            ], '/notifications/' . $notificationId . '/read', ['notification' => $notificationId, 'id' => $notificationId])
                            : null;

                        $hideOneUrl = $notificationId
                            ? $urlForFirstAvailableRoute([
                                'notifications.hide',
                                'notifications.hide-one',
                                'notifications.hide_one',
                            ], '/notifications/' . $notificationId . '/hide', ['notification' => $notificationId, 'id' => $notificationId])
                            : null;

                        $deleteOneUrl = $notificationId
                            ? $urlForFirstAvailableRoute([
                                'notifications.destroy',
                                'notifications.delete',
                                'notifications.delete-one',
                                'notifications.delete_one',
                            ], '/notifications/' . $notificationId, ['notification' => $notificationId, 'id' => $notificationId])
                            : null;
                    @endphp

                    <article class="notification-item-card {{ $isUnread ? 'is-unread' : 'is-read' }}">
                        <div class="notification-icon" aria-hidden="true">
                            @if($badgeClass === 'danger') ❌
                            @elseif($badgeClass === 'warning') ⚠️
                            @elseif($badgeClass === 'success') ✅
                            @else 🔔
                            @endif
                        </div>

                        <div class="notification-content">
                            <div class="notification-title-row">
                                <h3 class="notification-title">{{ $title }}</h3>
                                <span class="notification-type-badge {{ $badgeClass }}">{{ $type }}</span>
                                @if($isUnread)
                                    <span class="notification-type-badge info">جديد</span>
                                @endif
                            </div>

                            @if($message)
                                <p class="notification-message">{{ $message }}</p>
                            @endif

                            @if($createdAt)
                                <div class="notification-date">{{ $formatDate($createdAt) }}</div>
                            @endif
                        </div>

                        <div class="notification-item-actions">
                            @if($link)
                                <a class="notification-btn notification-btn-muted" href="{{ $link }}">فتح</a>
                            @endif

                            @if($isUnread && $markOneReadUrl)
                                <form method="POST" action="{{ $markOneReadUrl }}">
                                    @csrf
                                    <button type="submit" class="notification-btn notification-btn-primary">قراءة</button>
                                </form>
                            @endif

                            @if($hideOneUrl)
                                <form method="POST" action="{{ $hideOneUrl }}">
                                    @csrf
                                    <button type="submit" class="notification-btn notification-btn-warning">إخفاء</button>
                                </form>
                            @endif

                            @if($deleteOneUrl)
                                <form method="POST" action="{{ $deleteOneUrl }}" onsubmit="return confirm('هل تريد حذف هذا الإشعار؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="notification-btn notification-btn-danger">حذف</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        @if($isPaginator)
            <div class="notification-pagination">
                {{ $notifications->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
BLADE;

if (file_put_contents($viewPath, $view) === false) {
    fwrite(STDERR, "ERROR: تعذر تحديث ملف عرض الإشعارات.\n");
    exit(1);
}

// Remove backup directories accidentally left under Laravel autoload paths.
$rootsToClean = [
    $root . DIRECTORY_SEPARATOR . 'app',
    $root . DIRECTORY_SEPARATOR . 'resources',
    $root . DIRECTORY_SEPARATOR . 'routes',
];

$removeDir = function (string $dir) use (&$removeDir): void {
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $removeDir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
};

foreach ($rootsToClean as $cleanRoot) {
    if (!is_dir($cleanRoot)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cleanRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
            $removeDir($item->getPathname());
        }
    }
}

echo "DONE: تم ترتيب واجهة مركز الإشعارات V6 وإزالة مجلدات backup القديمة.\n";
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

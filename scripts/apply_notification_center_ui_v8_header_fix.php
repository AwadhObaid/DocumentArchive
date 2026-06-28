<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false || ! file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: يجب تشغيل السكربت من جذر مشروع Laravel.\n");
    exit(1);
}

$viewPath = $root . DIRECTORY_SEPARATOR . 'resources/views/notifications/index.blade.php';
if (! file_exists(dirname($viewPath))) {
    mkdir(dirname($viewPath), 0777, true);
}

if (file_exists($viewPath)) {
    $backupDir = $root . DIRECTORY_SEPARATOR . 'storage/app/patch-backups/notifications-ui-v8-' . date('Ymd-His');
    if (! is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }
    copy($viewPath, $backupDir . DIRECTORY_SEPARATOR . 'index.blade.php');
}

$blade = <<<'BLADE'
@extends('layouts.app')

@section('title', 'مركز الإشعارات')

@section('content')
@php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Carbon;

    $getValue = function ($item, array $keys, $default = null) {
        foreach ($keys as $key) {
            if (is_array($item) && array_key_exists($key, $item)) {
                return $item[$key];
            }
            if (is_object($item) && isset($item->{$key})) {
                return $item->{$key};
            }
        }
        return $default;
    };

    $safeText = function ($value, $default = '') {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if ($value instanceof \Stringable) {
            return (string) $value;
        }
        return $default;
    };

    $actionUrl = function (array $names, string $fallback) {
        foreach ($names as $name) {
            if (Route::has($name)) {
                return route($name);
            }
        }
        return url($fallback);
    };

    $itemUrl = function (array $names, string $fallback, $id) {
        $id = is_scalar($id) ? (string) $id : '';
        foreach ($names as $name) {
            if (Route::has($name)) {
                return route($name, $id);
            }
        }
        return url(str_replace('{id}', $id, $fallback));
    };

    $formatDate = function ($value) {
        if (empty($value)) {
            return '';
        }
        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (Throwable $e) {
            return (string) $value;
        }
    };

    $collectionForCount = collect($notifications instanceof \Illuminate\Contracts\Pagination\Paginator ? $notifications->items() : ($notifications ?? []));

    $totalCount = $totalCount ?? $total ?? ($notifications instanceof \Illuminate\Contracts\Pagination\Paginator ? $notifications->total() : $collectionForCount->count());
    $unreadCount = $unreadCount ?? $unread ?? $collectionForCount->filter(fn ($n) => empty($getValue($n, ['read_at', 'readAt'])))->count();
    $readCount = $readCount ?? $read ?? max(0, (int) $totalCount - (int) $unreadCount);
    $hiddenCount = $hiddenCount ?? $hidden ?? 0;

    $readAllUrl = $actionUrl(['notifications.read_all', 'notifications.read-all', 'notifications.mark-all-read', 'notifications.markAllRead'], '/notifications/read-all');
    $hideReadUrl = $actionUrl(['notifications.hide-read', 'notifications.hide_read', 'notifications.hideRead'], '/notifications/hide-read');
    $deleteHiddenUrl = $actionUrl(['notifications.delete-hidden', 'notifications.delete_hidden', 'notifications.purge-hidden', 'notifications.clear-hidden'], '/notifications/delete-hidden');
    $settingsUrl = Route::has('notification-settings.index') ? route('notification-settings.index') : url('/notification-settings');
@endphp

<style>
    .notifications-center-v8 {
        --nc-bg: #0f172a;
        --nc-panel: #111c2f;
        --nc-panel-2: #162238;
        --nc-border: rgba(148, 163, 184, .22);
        --nc-text: #f8fafc;
        --nc-muted: #a9b6ca;
        --nc-blue: #3b82f6;
        --nc-green: #22c55e;
        --nc-yellow: #f59e0b;
        --nc-red: #ef4444;
        direction: rtl;
        color: var(--nc-text);
        max-width: 1180px;
        margin: 0 auto;
        padding: 18px 14px 42px;
    }

    .notifications-center-v8 * { box-sizing: border-box; }

    .nc-hero {
        background: linear-gradient(135deg, rgba(17, 28, 47, .98), rgba(15, 23, 42, .98));
        border: 1px solid var(--nc-border);
        border-radius: 22px;
        padding: 26px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 20px 22px;
        align-items: start;
        box-shadow: 0 18px 50px rgba(0, 0, 0, .18);
    }

    .nc-hero-title-wrap {
        display: flex;
        align-items: flex-start;
        gap: 16px;
        min-width: 0;
    }

    .nc-hero-icon {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(59, 130, 246, .16);
        border: 1px solid rgba(96, 165, 250, .35);
        font-size: 30px;
        flex: 0 0 auto;
    }

    .nc-title-block {
        min-width: 0;
        width: 100%;
    }

    .nc-title-block h1 {
        margin: 0 0 10px;
        font-size: clamp(28px, 3.2vw, 46px);
        line-height: 1.18;
        color: #fff;
        font-weight: 900;
        letter-spacing: -.02em;
        white-space: normal;
        word-break: normal;
        overflow-wrap: anywhere;
    }

    .nc-title-block p {
        margin: 0;
        color: var(--nc-muted);
        line-height: 1.9;
        font-size: 15px;
        max-width: 760px;
        white-space: normal;
        word-break: normal;
    }

    .nc-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-start;
        gap: 10px;
        min-width: 360px;
        max-width: 520px;
    }

    .nc-btn, .nc-inline-form button, .nc-link-btn {
        border: 0;
        outline: 0;
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 40px;
        white-space: nowrap;
        transition: transform .15s ease, opacity .15s ease;
    }

    .nc-btn:hover, .nc-inline-form button:hover, .nc-link-btn:hover { transform: translateY(-1px); opacity: .94; }
    .nc-btn-blue { background: var(--nc-blue); color: #fff; }
    .nc-btn-yellow { background: var(--nc-yellow); color: #111827; }
    .nc-btn-red { background: var(--nc-red); color: #fff; }
    .nc-btn-gray { background: #334155; color: #fff; border: 1px solid rgba(148, 163, 184, .24); }
    .nc-btn-soft { background: rgba(59, 130, 246, .12); color: #bfdbfe; border: 1px solid rgba(96, 165, 250, .35); }

    .nc-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin: 18px 0;
    }

    .nc-stat {
        background: var(--nc-panel);
        border: 1px solid var(--nc-border);
        border-radius: 18px;
        padding: 18px 20px;
        min-height: 96px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 8px;
    }

    .nc-stat span { color: var(--nc-muted); font-size: 13px; font-weight: 800; }
    .nc-stat strong { color: #fff; font-size: 28px; line-height: 1; }

    .nc-list-panel {
        background: var(--nc-panel);
        border: 1px solid var(--nc-border);
        border-radius: 20px;
        padding: 18px;
    }

    .nc-list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--nc-border);
        margin-bottom: 14px;
    }

    .nc-list-header h2 { margin: 0; color: #fff; font-size: 20px; }
    .nc-list-header span { color: var(--nc-muted); font-size: 13px; }

    .nc-items {
        display: grid;
        gap: 12px;
    }

    .nc-item {
        background: var(--nc-panel-2);
        border: 1px solid var(--nc-border);
        border-radius: 16px;
        padding: 16px;
        display: grid;
        grid-template-columns: 52px minmax(0, 1fr) auto;
        gap: 14px;
        align-items: start;
    }

    .nc-item-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(59, 130, 246, .14);
        border: 1px solid rgba(96, 165, 250, .28);
        font-size: 24px;
    }

    .nc-item-content { min-width: 0; }
    .nc-item-title-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 8px; }
    .nc-item-title { margin: 0; color: #fff; font-size: 16px; font-weight: 900; }
    .nc-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 9px; font-size: 11px; font-weight: 800; background: rgba(59, 130, 246, .16); color: #bfdbfe; border: 1px solid rgba(96, 165, 250, .30); }
    .nc-pill-unread { background: rgba(34, 197, 94, .15); color: #bbf7d0; border-color: rgba(34, 197, 94, .35); }
    .nc-pill-read { background: rgba(148, 163, 184, .12); color: #cbd5e1; border-color: rgba(148, 163, 184, .25); }
    .nc-message { color: #dbeafe; margin: 0 0 8px; line-height: 1.75; overflow-wrap: anywhere; }
    .nc-meta { color: var(--nc-muted); font-size: 12px; display: flex; flex-wrap: wrap; gap: 10px; }

    .nc-item-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-start;
        gap: 8px;
        min-width: 240px;
    }

    .nc-item-actions .nc-btn, .nc-item-actions button, .nc-item-actions a {
        min-height: 34px;
        padding: 8px 11px;
        border-radius: 10px;
        font-size: 12px;
    }

    .nc-empty {
        border: 1px dashed rgba(148, 163, 184, .35);
        border-radius: 18px;
        padding: 34px;
        text-align: center;
        color: var(--nc-muted);
    }

    .nc-pagination { margin-top: 18px; }

    @media (max-width: 920px) {
        .nc-hero { grid-template-columns: 1fr; }
        .nc-actions { min-width: 0; max-width: none; }
        .nc-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .nc-item { grid-template-columns: 44px minmax(0, 1fr); }
        .nc-item-actions { grid-column: 1 / -1; min-width: 0; justify-content: flex-start; }
    }

    @media (max-width: 560px) {
        .notifications-center-v8 { padding: 10px 8px 28px; }
        .nc-hero { padding: 18px; border-radius: 18px; }
        .nc-hero-title-wrap { flex-direction: column; }
        .nc-stats { grid-template-columns: 1fr; }
        .nc-actions, .nc-item-actions { flex-direction: column; align-items: stretch; }
        .nc-btn, .nc-inline-form button, .nc-link-btn { width: 100%; }
        .nc-list-header { flex-direction: column; align-items: flex-start; }
    }

    @media print {
        .nc-actions, .nc-item-actions, .nc-pagination { display: none !important; }
        .notifications-center-v8 { color: #111827; background: #fff; padding: 0; }
        .nc-hero, .nc-stat, .nc-list-panel, .nc-item { background: #fff !important; color: #111827 !important; border-color: #d1d5db !important; box-shadow: none !important; }
        .nc-title-block h1, .nc-list-header h2, .nc-item-title, .nc-stat strong { color: #111827 !important; }
        .nc-message, .nc-title-block p, .nc-meta, .nc-stat span { color: #374151 !important; }
    }
</style>

<div class="notifications-center-v8">
    <section class="nc-hero">
        <div class="nc-hero-title-wrap">
            <div class="nc-hero-icon">🔔</div>
            <div class="nc-title-block">
                <h1>مركز الإشعارات</h1>
                <p>متابعة تنبيهات النظام والعمليات المهمة بشكل واضح ومنظم، مع إمكانية القراءة والإخفاء والتنظيف.</p>
            </div>
        </div>

        <div class="nc-actions" aria-label="إجراءات مركز الإشعارات">
            <a class="nc-link-btn nc-btn-gray" href="{{ $settingsUrl }}">⚙️ إعدادات الإشعارات</a>

            <form class="nc-inline-form" method="POST" action="{{ $readAllUrl }}">
                @csrf
                <button class="nc-btn-blue" type="submit">✅ تعليم الكل كمقروءة</button>
            </form>

            <form class="nc-inline-form" method="POST" action="{{ $hideReadUrl }}">
                @csrf
                <button class="nc-btn-yellow" type="submit">🙈 إخفاء المقروء</button>
            </form>

            <form class="nc-inline-form" method="POST" action="{{ $deleteHiddenUrl }}" onsubmit="return confirm('هل تريد حذف الإشعارات المخفية نهائياً؟');">
                @csrf
                <button class="nc-btn-red" type="submit">🗑️ حذف المخفية</button>
            </form>
        </div>
    </section>

    <section class="nc-stats" aria-label="إحصائيات الإشعارات">
        <div class="nc-stat"><span>الإجمالي</span><strong>{{ $totalCount }}</strong></div>
        <div class="nc-stat"><span>غير مقروءة</span><strong>{{ $unreadCount }}</strong></div>
        <div class="nc-stat"><span>مقروءة</span><strong>{{ $readCount }}</strong></div>
        <div class="nc-stat"><span>مخفية</span><strong>{{ $hiddenCount }}</strong></div>
    </section>

    <section class="nc-list-panel">
        <div class="nc-list-header">
            <h2>قائمة الإشعارات</h2>
            <span>{{ $collectionForCount->count() }} عنصر ظاهر في هذه الصفحة</span>
        </div>

        @if($collectionForCount->isEmpty())
            <div class="nc-empty">لا توجد إشعارات حالياً.</div>
        @else
            <div class="nc-items">
                @foreach($notifications as $notification)
                    @php
                        $id = $getValue($notification, ['id'], null);
                        $title = $safeText($getValue($notification, ['title', 'subject', 'heading']), 'إشعار النظام');
                        $message = $safeText($getValue($notification, ['body', 'message', 'description', 'content']), 'لا توجد تفاصيل إضافية.');
                        $type = $safeText($getValue($notification, ['type', 'level', 'category']), 'info');
                        $readAt = $getValue($notification, ['read_at', 'readAt'], null);
                        $createdAt = $getValue($notification, ['created_at', 'createdAt'], null);
                        $isRead = ! empty($readAt);
                        $rawLink = $getValue($notification, ['url', 'link', 'action_url', 'actionUrl'], null);
                        $openLink = is_scalar($rawLink) && trim((string) $rawLink) !== '' ? (string) $rawLink : null;
                        $readUrl = $id ? $itemUrl(['notifications.read', 'notifications.mark-read', 'notifications.mark_read'], '/notifications/{id}/read', $id) : null;
                        $hideUrl = $id ? $itemUrl(['notifications.hide'], '/notifications/{id}/hide', $id) : null;
                        $deleteUrl = $id ? $itemUrl(['notifications.destroy', 'notifications.delete'], '/notifications/{id}/delete', $id) : null;
                    @endphp

                    <article class="nc-item">
                        <div class="nc-item-icon">🔔</div>
                        <div class="nc-item-content">
                            <div class="nc-item-title-row">
                                <h3 class="nc-item-title">{{ $title }}</h3>
                                <span class="nc-pill">{{ $type }}</span>
                                <span class="nc-pill {{ $isRead ? 'nc-pill-read' : 'nc-pill-unread' }}">{{ $isRead ? 'مقروء' : 'جديد' }}</span>
                            </div>
                            <p class="nc-message">{{ $message }}</p>
                            <div class="nc-meta">
                                @if($createdAt)<span>🕒 {{ $formatDate($createdAt) }}</span>@endif
                                @if($readAt)<span>✅ قرئ في {{ $formatDate($readAt) }}</span>@endif
                            </div>
                        </div>
                        <div class="nc-item-actions">
                            @if($openLink)
                                <a class="nc-link-btn nc-btn-soft" href="{{ $openLink }}">فتح</a>
                            @endif
                            @if($readUrl && ! $isRead)
                                <form method="POST" action="{{ $readUrl }}">@csrf<button class="nc-btn nc-btn-blue" type="submit">قراءة</button></form>
                            @endif
                            @if($hideUrl)
                                <form method="POST" action="{{ $hideUrl }}">@csrf<button class="nc-btn nc-btn-yellow" type="submit">إخفاء</button></form>
                            @endif
                            @if($deleteUrl)
                                <form method="POST" action="{{ $deleteUrl }}" onsubmit="return confirm('هل تريد حذف هذا الإشعار؟');">@csrf<button class="nc-btn nc-btn-red" type="submit">حذف</button></form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if(is_object($notifications) && method_exists($notifications, 'links'))
                <div class="nc-pagination">{{ $notifications->links() }}</div>
            @endif
        @endif
    </section>
</div>
@endsection
BLADE;

file_put_contents($viewPath, $blade);

echo "DONE: تم إصلاح ترتيب بطاقة مركز الإشعارات ومنع الكتابة العمودية V8.\n";
echo "NEXT: php artisan view:clear && php artisan optimize:clear\n";

<?php

declare(strict_types=1);

$root = getcwd();

function fail(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}" . PHP_EOL);
    exit(1);
}

function removeDirectory(string $dir): void
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

function cleanBackupFolders(string $root): void
{
    foreach (['app', 'resources', 'routes'] as $relative) {
        $base = $root . DIRECTORY_SEPARATOR . $relative;
        if (!is_dir($base)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && str_starts_with($item->getFilename(), '_backup')) {
                removeDirectory($item->getPathname());
            }
        }
    }
}

if (!file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fail('يجب تشغيل السكربت من جذر مشروع Laravel حيث يوجد ملف artisan.');
}

$viewDir = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'notifications';
$viewPath = $viewDir . DIRECTORY_SEPARATOR . 'index.blade.php';

if (!is_dir($viewDir)) {
    mkdir($viewDir, 0775, true);
}

$backupDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'notifications-ui-v7-' . date('Ymd_His');
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

if (file_exists($viewPath)) {
    copy($viewPath, $backupDir . DIRECTORY_SEPARATOR . 'index.blade.php');
}

cleanBackupFolders($root);

$blade = <<<'BLADE'
@extends('layouts.app')

@section('content')
@php
    $routeUrl = function (array $names, array|string|int|null $params = null, ?string $fallback = null): string {
        foreach ($names as $name) {
            if (\Illuminate\Support\Facades\Route::has($name)) {
                return $params === null ? route($name) : route($name, $params);
            }
        }

        return $fallback ?: '#';
    };

    $safeText = function ($value, string $fallback = ''): string {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $fallback;
    };

    $safeUrl = function ($value): ?string {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
            return $value;
        }

        return url($value);
    };

    $formatDate = function ($value): string {
        if (!$value) {
            return '';
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return \Carbon\Carbon::instance($value)->format('Y-m-d H:i');
            }

            if (is_scalar($value)) {
                return \Carbon\Carbon::parse((string) $value)->format('Y-m-d H:i');
            }
        } catch (\Throwable $e) {
            return is_scalar($value) ? (string) $value : '';
        }

        return '';
    };

    $items = $notifications ?? collect();
    $pageItemsCount = method_exists($items, 'count') ? $items->count() : (is_countable($items) ? count($items) : 0);

    $totalValue = $totalCount
        ?? $totalNotifications
        ?? (method_exists($items, 'total') ? $items->total() : $pageItemsCount);

    $unreadValue = $unreadCount ?? $unreadNotificationsCount ?? 0;
    $readValue = $readCount ?? $readNotificationsCount ?? max(0, (int) $totalValue - (int) $unreadValue);
    $hiddenValue = $hiddenCount ?? $hiddenNotificationsCount ?? 0;

    $readAllUrl = $routeUrl(
        ['notifications.read_all', 'notifications.read-all', 'notifications.mark-all-read', 'notifications.markAllRead'],
        null,
        url('/notifications/read-all')
    );

    $hideReadUrl = $routeUrl(
        ['notifications.hide-read', 'notifications.hide_read'],
        null,
        url('/notifications/hide-read')
    );

    $deleteHiddenUrl = $routeUrl(
        ['notifications.delete-hidden', 'notifications.delete_hidden', 'notifications.purge-hidden', 'notifications.clear-hidden'],
        null,
        url('/notifications/delete-hidden')
    );

    $settingsUrl = $routeUrl(
        ['notification-settings.index', 'notifications.settings', 'notification.settings'],
        null,
        url('/notification-settings')
    );

    $typeLabels = [
        'success' => 'نجاح',
        'info' => 'معلومة',
        'warning' => 'تنبيه',
        'error' => 'خطأ',
        'danger' => 'خطأ',
        'document_created' => 'إضافة كتاب',
        'document_updated' => 'تعديل كتاب',
        'document_deleted' => 'حذف كتاب',
        'document_restored' => 'استعادة كتاب',
        'attachment_uploaded' => 'رفع مرفق',
        'attachment_deleted' => 'حذف مرفق',
    ];
@endphp

<style>
    .notification-center-page {
        direction: rtl;
        --nc-bg: #0f172a;
        --nc-panel: #111827;
        --nc-panel-2: #172033;
        --nc-border: rgba(148, 163, 184, .22);
        --nc-text: #f8fafc;
        --nc-muted: #b6c2d6;
        --nc-soft: rgba(59, 130, 246, .12);
        --nc-blue: #3b82f6;
        --nc-green: #22c55e;
        --nc-yellow: #f59e0b;
        --nc-red: #ef4444;
        --nc-shadow: 0 18px 45px rgba(0, 0, 0, .22);
        color: var(--nc-text);
    }

    body.light .notification-center-page,
    body.light-mode .notification-center-page,
    html.light .notification-center-page,
    [data-theme="light"] .notification-center-page {
        --nc-bg: #f8fafc;
        --nc-panel: #ffffff;
        --nc-panel-2: #f1f5f9;
        --nc-border: #dbe3ef;
        --nc-text: #0f172a;
        --nc-muted: #64748b;
        --nc-soft: #eff6ff;
        --nc-shadow: 0 16px 40px rgba(15, 23, 42, .08);
    }

    .notification-center-page * {
        box-sizing: border-box;
    }

    .nc-hero,
    .nc-stat-card,
    .nc-list-card,
    .nc-item {
        background: var(--nc-panel);
        border: 1px solid var(--nc-border);
        color: var(--nc-text);
        box-shadow: var(--nc-shadow);
    }

    .nc-hero {
        border-radius: 24px;
        padding: 28px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 22px;
        align-items: center;
        margin-bottom: 18px;
    }

    .nc-hero-title {
        margin: 0;
        font-size: clamp(28px, 4vw, 44px);
        line-height: 1.25;
        font-weight: 900;
        letter-spacing: -.02em;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .nc-hero-title .bell {
        width: 54px;
        height: 54px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--nc-soft);
        border: 1px solid rgba(59, 130, 246, .28);
        flex: 0 0 auto;
    }

    .nc-hero-subtitle {
        margin: 12px 0 0;
        color: var(--nc-muted);
        font-size: 15px;
        line-height: 1.9;
        max-width: 620px;
    }

    .nc-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-start;
        align-items: center;
    }

    .nc-actions form,
    .nc-item-actions form {
        margin: 0;
        display: inline-flex;
    }

    .nc-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 42px;
        border: 0;
        border-radius: 12px;
        padding: 9px 14px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: transform .16s ease, opacity .16s ease, box-shadow .16s ease;
        color: #fff;
        font-family: inherit;
        font-size: 13px;
    }

    .nc-btn:hover {
        transform: translateY(-1px);
        opacity: .94;
    }

    .nc-btn-blue { background: var(--nc-blue); }
    .nc-btn-green { background: var(--nc-green); }
    .nc-btn-yellow { background: var(--nc-yellow); color: #111827; }
    .nc-btn-red { background: var(--nc-red); }
    .nc-btn-muted {
        background: var(--nc-panel-2);
        color: var(--nc-text);
        border: 1px solid var(--nc-border);
    }

    .nc-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin: 18px 0;
    }

    .nc-stat-card {
        border-radius: 18px;
        padding: 18px;
        min-height: 112px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 10px;
    }

    .nc-stat-label {
        color: var(--nc-muted);
        font-weight: 800;
        font-size: 13px;
    }

    .nc-stat-number {
        font-size: 32px;
        font-weight: 900;
        color: var(--nc-text);
        line-height: 1;
    }

    .nc-list-card {
        border-radius: 22px;
        padding: 18px;
        margin-top: 14px;
    }

    .nc-list-header {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--nc-border);
        margin-bottom: 14px;
    }

    .nc-list-title {
        margin: 0;
        font-size: 22px;
        font-weight: 900;
    }

    .nc-list-meta {
        color: var(--nc-muted);
        font-weight: 800;
        font-size: 13px;
    }

    .nc-items {
        display: grid;
        gap: 12px;
    }

    .nc-item {
        border-radius: 18px;
        padding: 16px;
        display: grid;
        grid-template-columns: 52px minmax(0, 1fr) auto;
        gap: 14px;
        align-items: start;
        box-shadow: none;
        background: var(--nc-panel-2);
    }

    .nc-item-icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(59, 130, 246, .18);
        border: 1px solid rgba(59, 130, 246, .28);
        font-size: 22px;
    }

    .nc-item-main {
        min-width: 0;
    }

    .nc-item-topline {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 7px;
    }

    .nc-title {
        margin: 0;
        font-size: 16px;
        font-weight: 900;
        color: var(--nc-text);
    }

    .nc-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 24px;
        padding: 3px 9px;
        border-radius: 999px;
        background: rgba(59, 130, 246, .16);
        color: #93c5fd;
        border: 1px solid rgba(147, 197, 253, .28);
        font-size: 11px;
        font-weight: 900;
    }

    .nc-badge-new {
        background: rgba(34, 197, 94, .15);
        color: #86efac;
        border-color: rgba(134, 239, 172, .25);
    }

    body.light .nc-badge,
    body.light-mode .nc-badge,
    html.light .nc-badge,
    [data-theme="light"] .nc-badge {
        color: #2563eb;
    }

    body.light .nc-badge-new,
    body.light-mode .nc-badge-new,
    html.light .nc-badge-new,
    [data-theme="light"] .nc-badge-new {
        color: #15803d;
    }

    .nc-message {
        margin: 0;
        color: var(--nc-muted);
        line-height: 1.9;
        font-size: 14px;
        overflow-wrap: anywhere;
    }

    .nc-date {
        margin-top: 10px;
        color: var(--nc-muted);
        font-size: 12px;
        font-weight: 800;
    }

    .nc-item-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-start;
        min-width: 220px;
    }

    .nc-item-actions .nc-btn {
        min-height: 34px;
        padding: 7px 10px;
        border-radius: 10px;
        font-size: 12px;
    }

    .nc-empty {
        text-align: center;
        padding: 44px 20px;
        color: var(--nc-muted);
        border: 1px dashed var(--nc-border);
        border-radius: 18px;
        background: var(--nc-panel-2);
    }

    .nc-pagination {
        margin-top: 18px;
        color: var(--nc-text);
    }

    @media (max-width: 900px) {
        .nc-hero {
            grid-template-columns: 1fr;
        }

        .nc-stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .nc-item {
            grid-template-columns: 44px minmax(0, 1fr);
        }

        .nc-item-actions {
            grid-column: 1 / -1;
            min-width: 0;
        }
    }

    @media (max-width: 560px) {
        .nc-hero {
            padding: 20px;
            border-radius: 18px;
        }

        .nc-stats-grid {
            grid-template-columns: 1fr;
        }

        .nc-actions,
        .nc-item-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .nc-btn {
            width: 100%;
        }
    }

    @media print {
        .nc-actions,
        .nc-item-actions,
        .nc-pagination {
            display: none !important;
        }

        .notification-center-page {
            color: #111827 !important;
        }

        .nc-hero,
        .nc-stat-card,
        .nc-list-card,
        .nc-item {
            background: #fff !important;
            color: #111827 !important;
            box-shadow: none !important;
            border-color: #d1d5db !important;
        }
    }
</style>

<div class="notification-center-page">
    <section class="nc-hero">
        <div>
            <h1 class="nc-hero-title"><span class="bell">🔔</span> مركز الإشعارات</h1>
            <p class="nc-hero-subtitle">
                متابعة تنبيهات النظام والعمليات المهمة بشكل واضح ومنظم، مع إمكانية القراءة والإخفاء والتنظيف.
            </p>
        </div>

        <div class="nc-actions" aria-label="إجراءات مركز الإشعارات">
            <a class="nc-btn nc-btn-muted" href="{{ $settingsUrl }}">⚙️ إعدادات الإشعارات</a>

            <form method="POST" action="{{ $readAllUrl }}">
                @csrf
                <button type="submit" class="nc-btn nc-btn-blue">✅ تعليم الكل كمقروءة</button>
            </form>

            <form method="POST" action="{{ $hideReadUrl }}">
                @csrf
                <button type="submit" class="nc-btn nc-btn-yellow">🙈 إخفاء المقروء</button>
            </form>

            <form method="POST" action="{{ $deleteHiddenUrl }}" onsubmit="return confirm('هل تريد حذف الإشعارات المخفية نهائياً؟');">
                @csrf
                @method('DELETE')
                <button type="submit" class="nc-btn nc-btn-red">🗑 حذف المخفية</button>
            </form>
        </div>
    </section>

    <section class="nc-stats-grid" aria-label="ملخص الإشعارات">
        <div class="nc-stat-card">
            <div class="nc-stat-label">الإجمالي</div>
            <div class="nc-stat-number">{{ number_format((int) $totalValue) }}</div>
        </div>
        <div class="nc-stat-card">
            <div class="nc-stat-label">غير مقروءة</div>
            <div class="nc-stat-number">{{ number_format((int) $unreadValue) }}</div>
        </div>
        <div class="nc-stat-card">
            <div class="nc-stat-label">مقروءة</div>
            <div class="nc-stat-number">{{ number_format((int) $readValue) }}</div>
        </div>
        <div class="nc-stat-card">
            <div class="nc-stat-label">مخفية</div>
            <div class="nc-stat-number">{{ number_format((int) $hiddenValue) }}</div>
        </div>
    </section>

    <section class="nc-list-card">
        <div class="nc-list-header">
            <h2 class="nc-list-title">قائمة الإشعارات</h2>
            <div class="nc-list-meta">{{ number_format((int) $pageItemsCount) }} عنصر ظاهر في هذه الصفحة</div>
        </div>

        <div class="nc-items">
            @forelse($items as $notification)
                @php
                    $id = data_get($notification, 'id');
                    $title = $safeText(data_get($notification, 'title'), 'إشعار النظام');
                    $message = $safeText(
                        data_get($notification, 'message')
                            ?? data_get($notification, 'body')
                            ?? data_get($notification, 'content')
                            ?? data_get($notification, 'description'),
                        ''
                    );
                    $type = $safeText(data_get($notification, 'type'), 'info');
                    $typeLabel = $typeLabels[$type] ?? $type;
                    $isUnread = empty(data_get($notification, 'read_at'));
                    $createdAt = $formatDate(data_get($notification, 'created_at'));
                    $targetUrl = $safeUrl(data_get($notification, 'url') ?? data_get($notification, 'link'));

                    $readUrl = $id ? $routeUrl(
                        ['notifications.read', 'notifications.mark-read', 'notifications.mark_read'],
                        $id,
                        url('/notifications/' . $id . '/read')
                    ) : '#';

                    $hideUrl = $id ? $routeUrl(
                        ['notifications.hide', 'notifications.hide-one', 'notifications.hide_one'],
                        $id,
                        url('/notifications/' . $id . '/hide')
                    ) : '#';

                    $deleteUrl = $id ? $routeUrl(
                        ['notifications.destroy', 'notifications.delete', 'notifications.delete-one'],
                        $id,
                        url('/notifications/' . $id)
                    ) : '#';
                @endphp

                <article class="nc-item">
                    <div class="nc-item-icon">🔔</div>

                    <div class="nc-item-main">
                        <div class="nc-item-topline">
                            <h3 class="nc-title">{{ $title }}</h3>
                            <span class="nc-badge">{{ $typeLabel }}</span>
                            @if($isUnread)
                                <span class="nc-badge nc-badge-new">جديد</span>
                            @endif
                        </div>

                        @if($message !== '')
                            <p class="nc-message">{{ $message }}</p>
                        @else
                            <p class="nc-message">لا توجد تفاصيل إضافية لهذا الإشعار.</p>
                        @endif

                        @if($createdAt !== '')
                            <div class="nc-date">{{ $createdAt }}</div>
                        @endif
                    </div>

                    <div class="nc-item-actions">
                        @if($targetUrl)
                            <a class="nc-btn nc-btn-blue" href="{{ $targetUrl }}">فتح</a>
                        @endif

                        @if($id && $isUnread)
                            <form method="POST" action="{{ $readUrl }}">
                                @csrf
                                <button type="submit" class="nc-btn nc-btn-green">قراءة</button>
                            </form>
                        @endif

                        @if($id)
                            <form method="POST" action="{{ $hideUrl }}">
                                @csrf
                                <button type="submit" class="nc-btn nc-btn-yellow">إخفاء</button>
                            </form>

                            <form method="POST" action="{{ $deleteUrl }}" onsubmit="return confirm('هل تريد حذف هذا الإشعار؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="nc-btn nc-btn-red">حذف</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="nc-empty">لا توجد إشعارات حالياً.</div>
            @endforelse
        </div>

        @if(method_exists($items, 'links'))
            <div class="nc-pagination">
                {{ $items->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
BLADE;

file_put_contents($viewPath, $blade);

// Remove compiled views to force Laravel to read the new Blade file.
$compiledViews = glob($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . '*.php') ?: [];
foreach ($compiledViews as $compiledView) {
    @unlink($compiledView);
}

fwrite(STDOUT, "DONE: تم ترتيب مركز الإشعارات V7 وإصلاح الوضع الليلي والبطاقات.\n");
fwrite(STDOUT, "NEXT: php artisan view:clear && php artisan optimize:clear && php scripts/check_notification_center_ui_v7_dark_fix.php\n");

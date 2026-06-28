@extends('layouts.app')

@section('title', 'م
ركز الإشعارات')

@section('content')
@php
    use Illuminate\Support\Facades\Route;

    $notificationCollection = $notifications ?? collect();
    $isPaginator = $notificationCollection instanceof \Illuminate\Contracts\Pagination\Paginator;
    $isLengthPaginator = $notificationCollection instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    $itemsCount = $isLengthPaginator
        ? $notificationCollection->count()
        : (is_countable($notificationCollection) ? count($notificationCollection) : 0);

    $total = $totalNotifications
        ?? $totalCount
        ?? ($isLengthPaginator ? $notificationCollection->total() : $itemsCount);

    $unread = $unreadCount ?? $unreadNotifications ?? 0;
    $read = $readCount ?? max(((int) $total - (int) $unread), 0);
    $hidden = $hiddenCount ?? $hiddenNotifications ?? 0;

    $routeOrUrl = function (array $names, string $fallback = '#', mixed $parameter = null): string {
        foreach ($names as $name) {
            if (Route::has($name)) {
                return $parameter === null ? route($name) : route($name, $parameter);
            }
        }
        return url($fallback);
    };

    $bulkReadUrl = $routeOrUrl(['notifications.read_all', 'notifications.read-all', 'notifications.mark-all-read'], '/notifications/read-all');
    $hideReadUrl = $routeOrUrl(['notifications.hide-read', 'notifications.hide_read'], '/notifications/hide-read');
    $deleteHiddenUrl = $routeOrUrl(['notifications.delete-hidden', 'notifications.purge-hidden', 'notifications.clear-hidden'], '/notifications/delete-hidden');
@endphp

<style>
    .notifications-page-v9 {
        direction: rtl;
        color: #e5eefc;
    }

    .notifications-hero-v9 {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 24px;
        border: 1px solid rgba(148, 163, 184, .22);
        border-radius: 22px;
        background: linear-gradient(135deg, rgba(15, 23, 42, .98), rgba(17, 24, 39, .92));
        box-shadow: 0 18px 50px rgba(0,0,0,.18);
        margin-bottom: 18px;
        overflow: hidden;
    }

    .notifications-hero-main-v9 {
        display: flex;
        align-items: center;
        gap: 16px;
        min-width: 280px;
        flex: 1 1 420px;
    }

    .notifications-hero-icon-v9 {
        width: 58px;
        height: 58px;
        min-width: 58px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 18px;
        background: rgba(37, 99, 235, .18);
        border: 1px solid rgba(96, 165, 250, .35);
        font-size: 28px;
    }

    .notifications-title-v9 {
        margin: 0;
        font-size: clamp(28px, 4vw, 44px);
        line-height: 1.15;
        font-weight: 900;
        color: #ffffff;
        letter-spacing: -.5px;
        white-space: normal;
        word-break: normal;
    }

    .notifications-subtitle-v9 {
        margin: 8px 0 0;
        color: #a9b7cf;
        font-size: 15px;
        line-height: 1.9;
        max-width: 620px;
    }

    .notifications-actions-v9 {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        flex-wrap: wrap;
        gap: 10px;
        flex: 0 1 460px;
    }

    .notifications-actions-v9 form {
        display: inline-flex;
        margin: 0;
    }

    .notif-btn-v9 {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 38px;
        padding: 9px 14px;
        border: 0;
        border-radius: 11px;
        font-weight: 800;
        font-size: 13px;
        text-decoration: none;
        color: #fff !important;
        cursor: pointer;
        white-space: nowrap;
    }

    .notif-btn-blue-v9 { background: #2563eb; }
    .notif-btn-gray-v9 { background: #334155; }
    .notif-btn-orange-v9 { background: #f59e0b; color: #111827 !important; }
    .notif-btn-red-v9 { background: #ef4444; }
    .notif-btn-green-v9 { background: #059669; }
    .notif-btn-soft-v9 { background: rgba(59, 130, 246, .14); color: #bfdbfe !important; border: 1px solid rgba(96, 165, 250, .24); }

    .notifications-stats-v9 {
        display: grid;
        grid-template-columns: repeat(4, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .notifications-stat-v9 {
        padding: 18px;
        border-radius: 17px;
        border: 1px solid rgba(148, 163, 184, .22);
        background: rgba(15, 23, 42, .88);
    }

    .notifications-stat-v9 span {
        display: block;
        color: #9fb0c9;
        font-size: 13px;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .notifications-stat-v9 strong {
        display: block;
        color: #fff;
        font-size: 27px;
        font-weight: 900;
        line-height: 1;
    }

    .notifications-list-card-v9 {
        border-radius: 20px;
        border: 1px solid rgba(148, 163, 184, .22);
        background: rgba(15, 23, 42, .88);
        padding: 18px;
    }

    .notifications-list-head-v9 {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding-bottom: 14px;
        margin-bottom: 14px;
        border-bottom: 1px solid rgba(148, 163, 184, .18);
    }

    .notifications-list-head-v9 h2 {
        margin: 0;
        color: #fff;
        font-size: 20px;
        font-weight: 900;
    }

    .notifications-list-head-v9 small {
        color: #93a4bd;
        font-weight: 700;
    }

    .notification-item-v9 {
        display: grid;
        grid-template-columns: 54px minmax(0, 1fr) auto;
        gap: 14px;
        align-items: start;
        padding: 16px;
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, .18);
        background: rgba(30, 41, 59, .62);
        margin-bottom: 12px;
    }

    .notification-item-icon-v9 {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(37, 99, 235, .18);
        border: 1px solid rgba(96, 165, 250, .28);
        font-size: 23px;
    }

    .notification-item-title-row-v9 {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 7px;
    }

    .notification-item-title-v9 {
        margin: 0;
        color: #fff;
        font-size: 16px;
        font-weight: 900;
        line-height: 1.6;
    }

    .notification-badge-v9 {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 4px 8px;
        font-size: 11px;
        font-weight: 900;
        background: rgba(59, 130, 246, .15);
        color: #bfdbfe;
        border: 1px solid rgba(96, 165, 250, .25);
    }

    .notification-message-v9 {
        color: #cbd5e1;
        font-size: 14px;
        line-height: 1.8;
        margin: 0 0 8px;
        white-space: normal;
        word-break: break-word;
    }

    .notification-meta-v9 {
        color: #8ea0ba;
        font-size: 12px;
        font-weight: 700;
    }

    .notification-item-actions-v9 {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        flex-wrap: wrap;
        gap: 8px;
        min-width: 220px;
    }

    .notification-item-actions-v9 form {
        margin: 0;
        display: inline-flex;
    }

    .notifications-empty-v9 {
        padding: 34px;
        text-align: center;
        color: #a9b7cf;
        border: 1px dashed rgba(148, 163, 184, .28);
        border-radius: 16px;
    }

    .notifications-pagination-v9 {
        margin-top: 18px;
    }

    @media (max-width: 900px) {
        .notifications-hero-v9 { align-items: stretch; }
        .notifications-actions-v9 { flex-basis: 100%; }
        .notifications-stats-v9 { grid-template-columns: repeat(2, minmax(130px, 1fr)); }
        .notification-item-v9 { grid-template-columns: 46px minmax(0, 1fr); }
        .notification-item-actions-v9 { grid-column: 1 / -1; min-width: 0; }
    }

    @media (max-width: 520px) {
        .notifications-hero-v9 { padding: 18px; }
        .notifications-hero-main-v9 { align-items: flex-start; }
        .notifications-stats-v9 { grid-template-columns: 1fr; }
        .notif-btn-v9 { width: 100%; }
        .notifications-actions-v9 form,
        .notifications-actions-v9 a { width: 100%; }
        .notification-item-actions-v9 .notif-btn-v9 { width: auto; }
    }

    @media print {
        .notifications-page-v9 .notifications-actions-v9,
        .notification-item-actions-v9,
        .notifications-pagination-v9 { display: none !important; }
    }
</style>

<div class="notifications-page-v9">
    <section class="notifications-hero-v9">
        <div class="notifications-hero-main-v9">
            <div class="notifications-hero-icon-v9">🔔</div>
            <div>
                <h1 class="notifications-title-v9">م
ركز الإشعارات</h1>
                <p class="notifications-subtitle-v9">
                    م
تابعة تنبيهات النظام
 والعم
ليات الم
هم
ة بشكل واضح وم
نظم
 م
ع إم
كانية القراءة والإخفاء والتنظيف.
                </p>
            </div>
        </div>

        <div class="notifications-actions-v9">
            <a class="notif-btn-v9 notif-btn-gray-v9" href="{{ url('/notification-settings') }}">⚙️ إعدادات الإشعارات</a>

            <form method="POST" action="{{ $bulkReadUrl }}">
                @csrf
                <button class="notif-btn-v9 notif-btn-blue-v9" type="submit">م
 تعليم
 الكل كم
قروءة</button>
            </form>

            <form method="POST" action="{{ $hideReadUrl }}">
                @csrf
                <button class="notif-btn-v9 notif-btn-orange-v9" type="submit">🙈 إخفاء الم
قروء</button>
            </form>

            <form method="POST" action="{{ $deleteHiddenUrl }}" onsubmit="return confirm('هل تريد حذف الإشعارات الم
خفية نهائياً؟');">
                @csrf
                <button class="notif-btn-v9 notif-btn-red-v9" type="submit">🗑 حذف الم
خفية</button>
            </form>
        </div>
    </section>

    <section class="notifications-stats-v9">
        <div class="notifications-stat-v9"><span>الإجم
الي</span><strong>{{ number_format((int) $total) }}</strong></div>
        <div class="notifications-stat-v9"><span>غير م
قروءة</span><strong>{{ number_format((int) $unread) }}</strong></div>
        <div class="notifications-stat-v9"><span>م
قروءة</span><strong>{{ number_format((int) $read) }}</strong></div>
        <div class="notifications-stat-v9"><span>م
خفية</span><strong>{{ number_format((int) $hidden) }}</strong></div>
    </section>

    <section class="notifications-list-card-v9">
        <div class="notifications-list-head-v9">
            <h2>قائم
ة الإشعارات</h2>
            <small>{{ number_format($itemsCount) }} عنصر ظاهر في هذه الصفحة</small>
        </div>

        @forelse($notificationCollection as $notification)
            @php
                $id = data_get($notification, 'id');
                $title = data_get($notification, 'title')
                    ?: data_get($notification, 'subject')
                    ?: data_get($notification, 'heading')
                    ?: 'إشعار النظام
';

                $message = data_get($notification, 'message')
                    ?: data_get($notification, 'body')
                    ?: data_get($notification, 'description')
                    ?: data_get($notification, 'data.message')
                    ?: 'لا توجد تفاصيل إضافية لهذا الإشعار.';

                $type = data_get($notification, 'type') ?: data_get($notification, 'level') ?: 'info';
                $createdAt = data_get($notification, 'created_at');
                $readAt = data_get($notification, 'read_at');
                $url = data_get($notification, 'url') ?: data_get($notification, 'link') ?: data_get($notification, 'action_url');

                $markReadUrl = $id ? $routeOrUrl(['notifications.read', 'notifications.mark-read', 'notifications.mark_read'], '/notifications/' . $id . '/read', $id) : '#';
                $hideUrl = $id ? $routeOrUrl(['notifications.hide'], '/notifications/' . $id . '/hide', $id) : '#';
                $deleteUrl = $id ? $routeOrUrl(['notifications.destroy', 'notifications.delete'], '/notifications/' . $id, $id) : '#';
            @endphp

            <article class="notification-item-v9">
                <div class="notification-item-icon-v9">🔔</div>

                <div>
                    <div class="notification-item-title-row-v9">
                        <h3 class="notification-item-title-v9">{{ $title }}</h3>
                        <span class="notification-badge-v9">{{ $type }}</span>
                        <span class="notification-badge-v9">{{ $readAt ? 'م
قروء' : 'جديد' }}</span>
                    </div>

                    <p class="notification-message-v9">{{ $message }}</p>
                    <div class="notification-meta-v9">
                        {{ $createdAt ? \Illuminate\Support\Carbon::parse($createdAt)->format('Y-m-d H:i') : 'بدون تاريخ' }}
                    </div>
                </div>

                <div class="notification-item-actions-v9">
                    @if($url)
                        <a class="notif-btn-v9 notif-btn-soft-v9" href="{{ $url }}">فتح</a>
                    @endif

                    @if(!$readAt && $id)
                        <form method="POST" action="{{ $markReadUrl }}">
                            @csrf
                            <button class="notif-btn-v9 notif-btn-blue-v9" type="submit">قراءة</button>
                        </form>
                    @endif

                    @if($id)
                        <form method="POST" action="{{ $hideUrl }}">
                            @csrf
                            <button class="notif-btn-v9 notif-btn-orange-v9" type="submit">إخفاء</button>
                        </form>

                        <form method="POST" action="{{ $deleteUrl }}" onsubmit="return confirm('هل تريد حذف هذا الإشعار؟');">
                            @csrf
                            @method('DELETE')
                            <button class="notif-btn-v9 notif-btn-red-v9" type="submit">حذف</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="notifications-empty-v9">لا توجد إشعارات حالياً.</div>
        @endforelse

        @if($notificationCollection instanceof \Illuminate\Contracts\Pagination\Paginator)
            <div class="notifications-pagination-v9">
                {{ $notificationCollection->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
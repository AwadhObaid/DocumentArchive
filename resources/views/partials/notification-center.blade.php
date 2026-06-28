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
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    @php
        $liteSystemName = \App\Models\Setting::getValue('system_name', 'الأرشيف الإلكتروني');
        $liteDepartment = \App\Models\Setting::getValue('system_department_name', 'الشحن والتأمين');
        $liteIcon = \App\Models\Setting::getValue('system_brand_icon', '🗂️');
        $liteAutoLogoutEnabled = (string) \App\Models\Setting::getValue('auto_logout_enabled', '0') === '1';
        $liteAutoLogoutMinutes = max(1, min(1440, (int) \App\Models\Setting::getValue('auto_logout_minutes', 30)));
        $liteAutoLogoutWarningSeconds = max(10, min(600, (int) \App\Models\Setting::getValue('auto_logout_warning_seconds', 60)));
        $liteAutoLogoutTimeoutSeconds = $liteAutoLogoutMinutes * 60;
        $liteUnread = \App\Models\SystemNotification::query()
            ->where(function ($q) {
                $q->whereNull('user_id');
                if (auth()->id()) {
                    $q->orWhere('user_id', auth()->id());
                }
            })
            ->whereNull('read_at')
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('system_notifications', 'is_hidden'), fn ($q) => $q->where(function ($x) { $x->whereNull('is_hidden')->orWhere('is_hidden', false); }))
            ->count();
    @endphp
    <title>@yield('title', 'نسخة الهاتف') - {{ $liteSystemName }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#07111f">
    <meta name="description" content="{{ $liteSystemName }} - نسخة الهاتف لايت">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $liteSystemName }} لايت">
    <link rel="manifest" href="{{ asset('manifest-lite.json') }}?v=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=20260705">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=20260705">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=20260705">
    <link rel="stylesheet" href="{{ asset('css/lite.css') }}?v={{ filemtime(public_path('css/lite.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/auto-logout.css') }}?v={{ filemtime(public_path('css/auto-logout.css')) }}">
</head>
<body
    data-lite-notifications
    data-poll-url="{{ route('lite.notifications.poll') }}"
    data-poll-seconds="{{ $pollSeconds ?? 30 }}"
    data-initial-unread="{{ $liteUnread }}"
    data-auto-logout-enabled="{{ $liteAutoLogoutEnabled ? '1' : '0' }}"
    data-auto-logout-timeout="{{ $liteAutoLogoutTimeoutSeconds }}"
    data-auto-logout-warning="{{ min($liteAutoLogoutWarningSeconds, max(10, $liteAutoLogoutTimeoutSeconds - 5)) }}"
    data-auto-logout-ping-url="{{ route('session.activity') }}"
    data-auto-logout-login-url="{{ route('login') }}"
    data-auto-logout-logout-url="{{ route('logout') }}"
>
    <div class="lite-shell">
        <header class="lite-topbar">
            <a href="{{ route('lite.index') }}" class="lite-brand">
                <span class="lite-brand-icon">{{ $liteIcon }}</span>
                <span>
                    <h1>{{ $liteSystemName }} لايت</h1>
                    <p>{{ $liteDepartment }} · معاينة فقط</p>
                </span>
            </a>
            <div class="lite-user-actions">
                <button class="lite-icon-btn lite-install-btn" type="button" data-lite-install aria-label="تثبيت التطبيق" hidden>⬇️</button>
                <a class="lite-icon-btn" href="{{ route('lite.notifications.index') }}" aria-label="الإشعارات">
                    🔔 <span class="lite-badge" data-lite-unread-count style="{{ $liteUnread < 1 ? 'display:none' : '' }}">{{ $liteUnread }}</span>
                </a>
                <a class="lite-icon-btn" href="{{ route('dashboard') }}" aria-label="النظام الكامل">🖥️</a>
            </div>
        </header>

        @yield('content')
    </div>

    <nav class="lite-bottom-nav" aria-label="تنقل نسخة الهاتف">
        <a href="{{ route('lite.index') }}" class="{{ request()->routeIs('lite.index') ? 'active' : '' }}"><b>🏠</b><span>الرئيسية</span></a>
        <a href="{{ route('lite.documents.index') }}" class="{{ request()->routeIs('lite.documents.*') ? 'active' : '' }}"><b>📄</b><span>الكتب</span></a>
        <a href="{{ route('lite.memos.index') }}" class="{{ request()->routeIs('lite.memos.*') ? 'active' : '' }}"><b>📒</b><span>المذكرات</span></a>
        <a href="{{ route('lite.notifications.index') }}" class="{{ request()->routeIs('lite.notifications.*') ? 'active' : '' }}"><b>🔔</b><span>إشعارات</span></a>
        <a href="{{ route('dashboard') }}"><b>🖥️</b><span>كامل</span></a>
    </nav>

    <div class="lite-toast" id="liteNotificationToast">
        <strong data-lite-toast-title>إشعار جديد</strong>
        <p data-lite-toast-body>وصل إشعار جديد إلى النظام.</p>
    </div>

    <div class="auto-logout-modal" id="autoLogoutModal" aria-hidden="true">
        <div class="auto-logout-card" role="dialog" aria-modal="true" aria-labelledby="autoLogoutTitle">
            <div class="auto-logout-icon">🔒</div>
            <div>
                <h3 id="autoLogoutTitle">تنبيه انتهاء الجلسة</h3>
                <p>لم يتم رصد نشاط في النظام. سيتم تسجيل الخروج تلقائياً خلال <strong data-auto-logout-countdown>60</strong> ثانية.</p>
                <div class="auto-logout-actions">
                    <button type="button" class="lite-btn" data-auto-logout-stay>متابعة العمل</button>
                    <button type="button" class="lite-btn secondary" data-auto-logout-now>تسجيل الخروج الآن</button>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/lite-notifications.js') }}?v={{ filemtime(public_path('js/lite-notifications.js')) }}" defer></script>
    <script src="{{ asset('js/auto-logout.js') }}?v={{ filemtime(public_path('js/auto-logout.js')) }}" defer></script>
    <script src="{{ asset('js/lite-pwa.js') }}?v=1" defer></script>
</body>
</html>

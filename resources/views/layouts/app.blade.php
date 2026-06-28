<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'نظام
 الأرشيف الإلكتروني')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cairo-global.css') }}">
    <link rel="stylesheet" href="{{ asset('css/arabic-file-input.css') }}">
    {{-- Arabic browser/form validation messages --}}
    <link rel="stylesheet" href="{{ asset('css/arabic-validation.css') }}">
<!-- reports-dark-mode-fix:start -->
<link rel="stylesheet" href="{{ asset('css/reports-dark-mode-fix.css') }}">
<script defer src="{{ asset('js/reports-dark-mode-fix.js') }}"></script>
<!-- reports-dark-mode-fix:end -->
    {{-- DocumentArchive popup notifications --}}
    <link rel="stylesheet" href="{{ asset('css/app-notifications.css') }}">
<!-- Documents grid actions inline fix:start -->
<link rel="stylesheet" href="{{ asset('css/documents-grid-actions-fix.css') }}">
<!-- Documents grid actions inline fix:end -->
    <link rel="stylesheet" href="{{ asset('css/qr-total-isolation-v6.css') }}?v={{ filemtime(public_path('css/qr-total-isolation-v6.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/qr-global-isolation-v9.css') }}">
    <link rel="stylesheet" href="{{ asset('css/arabic-ellipsis-display-fix.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/arabic-ui-final-fix.css') }}?v=2026062802">
    {{-- Arabic UI V4 final guard --}}
    <link rel="stylesheet" href="{{ asset('css/arabic-no-truncate-v4.css') }}?v={{ filemtime(public_path('css/arabic-no-truncate-v4.css')) }}">
</head>
<body>

<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-icon">📁</div>
            <div>
                <div class="brand-title">الأرشيف الإلكتروني</div>
                <div class="brand-subtitle">الشحن والتأم
ين</div>
            </div>
        </div>

        <nav class="side-nav">
            <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'active' : '' }}">👤 الم
لف الشخصي</a>

            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">🏠 لوحة التحكم
</a>
            @if(auth()->user()?->hasPermission('documents.view'))
            <a href="{{ route('documents.index') }}" class="{{ request()->routeIs('documents.index') ? 'active' : '' }}">📄 الكتب</a>
            @endif
            @if(auth()->user()?->hasPermission('documents.create'))
            <a href="{{ route('documents.create') }}" class="{{ request()->routeIs('documents.create') ? 'active' : '' }}">➕ إضافة كتاب</a>
            @endif
            @if(auth()->user()?->hasPermission('documents.restore'))
            <a href="{{ route('documents.trash') }}" class="{{ request()->routeIs('documents.trash') ? 'active' : '' }}">🗑️ سلة الم
حذوفات</a>
            @endif
            @if(auth()->user()?->hasPermission('activity_logs.view'))
            <a href="{{ route('activity-logs.index') }}" class="{{ request()->routeIs('activity-logs.*') || request()->routeIs('documents.activity') ? 'active' : '' }}">🧾 سجل النشاط</a>
            @endif
            @if(auth()->user()?->hasPermission('departments.manage'))
            <a href="{{ route('departments.index') }}" class="{{ request()->routeIs('departments.*') ? 'active' : '' }}">🏢 الإدارات</a>
            @endif
            @if(auth()->user()?->hasPermission('document_types.manage'))
            <a href="{{ route('document-types.index') }}" class="{{ request()->routeIs('document-types.*') ? 'active' : '' }}">📑 أنواع الكتب</a>
            @endif
@if(auth()->user()?->hasPermission('settings.manage'))
<a href="{{ route('settings.edit') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">⚙️ الإعدادات</a>
                        @if(auth()->user()?->hasPermission('settings.manage'))
                            <a href="{{ route('system-health.index') }}">🩺 فحص النظام
</a>
                        @if(auth()->user()?->hasPermission('reports.view'))
                            <a href="{{ route('data-quality.index') }}">🧭 جودة البيانات</a>
                        @endif
                        @endif
@endif
                {{-- REPORTS-SIDEBAR-LINK --}}
                @if(auth()->user()?->hasPermission('reports.view'))
                <a href="{{ route('reports.index') }}" class="sidebar-link nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <span class="nav-icon">📊</span>
                    <span>التقارير</span>
                </a>
                @endif

            
            {{-- BACKUP-SIDEBAR-ADMIN-ONLY --}}
            @if(auth()->user()?->hasPermission('users.manage'))
                <a href="{{ url('/backups') }}" class="{{ request()->is('backups*') ? 'active' : '' }}">💾 النسخ الاحتياطي</a>
            @endif
@if(auth()->user()?->hasPermission('users.manage'))
                <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">👥 الم
ستخدم
ون</a>
            @endif
        </nav>

        <div class="sidebar-footer">
            <div class="user-mini">
                <div class="avatar">{{ mb_substr(auth()->user()?->name ?? 'م
', 0, 1) }}</div>
                <div>
                    <strong>{{ auth()->user()?->name }}</strong>
                    <span>{{ auth()->user()?->role_name }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-btn">تسجيل الخروج</button>
            </form>
        </div>
    </aside>

    <main class="main-area">
        <header class="topbar">
            <button class="menu-toggle" type="button" data-toggle-sidebar>☰</button>

            <div>
                <h1>@yield('page_title', 'نظام
 الأرشيف الإلكتروني الخاص بقسم
 الشحن والتأم
ين')</h1>
                <p>@yield('page_subtitle', 'إدارة الكتب، الم
رفقات، البوالص، والطباعة الرسم
ية')</p>
            </div>

            <button class="theme-toggle" type="button" data-toggle-theme>🌙</button>
        </header>

        <section class="content-area">
            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert-error">
                    <strong>يرجى تصحيح الأخطاء التالية:</strong>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </section>
    </main>
</div>

<script src="{{ asset('js/app.js') }}"></script>
    <script src="{{ asset('js/arabic-file-input.js') }}" defer></script>

    {{-- Arabic browser/form validation messages --}}
    <script src="{{ asset('js/arabic-form-validation.js') }}" defer></script>
    {{-- DocumentArchive popup notifications --}}
    @include('partials.flash-notifications')
    <script src="{{ asset('js/app-notifications.js') }}"></script>
<!-- Documents grid actions inline fix script:start -->
<script src="{{ asset('js/documents-grid-actions-fix.js') }}" defer></script>
<!-- Documents grid actions inline fix script:end -->
    {{-- notification-center-include --}}
    @include('partials.notification-center')
    <script src="{{ asset('js/qr-total-isolation-v6.js') }}?v={{ filemtime(public_path('js/qr-total-isolation-v6.js')) }}" defer></script>
    <script src="{{ asset('js/qr-global-isolation-v9.js') }}" defer></script>
    <script src="{{ asset('js/arabic-ellipsis-display-fix.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/arabic-ui-final-fix.js') }}?v=2026062802"></script>
    {{-- Arabic UI V4 final guard --}}
    <script src="{{ asset('js/arabic-text-mojibake-v4.js') }}?v={{ filemtime(public_path('js/arabic-text-mojibake-v4.js')) }}" defer></script>
</body>
</html>



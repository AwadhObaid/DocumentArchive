<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    {{-- document-archive-system-settings:start --}}
    @php
        $daSystemName = \App\Models\Setting::getValue('system_name', 'الأرشيف الإلكتروني');
        $daSystemDepartmentName = \App\Models\Setting::getValue('system_department_name', 'الشحن والتأمين');
        $daSystemFullTitle = \App\Models\Setting::getValue('system_full_title', 'نظام الأرشيف الإلكتروني الخاص بقسم الشحن والتأمين');
        $daSystemTagline = \App\Models\Setting::getValue('system_tagline', 'إدارة الكتب، المرفقات، البوالص، والطباعة الرسمية');
        $daSystemBrandIcon = \App\Models\Setting::getValue('system_brand_icon', '📁');
    @endphp
    {{-- document-archive-system-settings:end --}}
    <title>@yield('title', $daSystemName)</title>
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
    <link rel="stylesheet" href="{{ asset('css/arabic-ellipsis-display-fix.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/arabic-ui-final-fix.css') }}?v=2026062802">
    {{-- Arabic UI V4 final guard --}}
    <link rel="stylesheet" href="{{ asset('css/arabic-no-truncate-v4.css') }}?v={{ filemtime(public_path('css/arabic-no-truncate-v4.css')) }}">
    {{-- DocumentArchive compact font scale fix --}}
    <link rel="stylesheet" href="{{ asset('css/global-font-scale-fix.css') }}?v={{ filemtime(public_path('css/global-font-scale-fix.css')) }}">
    {{-- DocumentArchive contacts and message templates module --}}
    <link rel="stylesheet" href="{{ asset('css/contacts-templates.css') }}?v={{ filemtime(public_path('css/contacts-templates.css')) }}">
    {{-- DocumentArchive email module --}}
    <link rel="stylesheet" href="{{ asset('css/email-module.css') }}?v={{ filemtime(public_path('css/email-module.css')) }}">
    {{-- DocumentArchive whatsapp module --}}
    <link rel="stylesheet" href="{{ asset('css/whatsapp-module.css') }}?v={{ filemtime(public_path('css/whatsapp-module.css')) }}">
    {{-- DocumentArchive secure attachment share links --}}
    <link rel="stylesheet" href="{{ asset('css/shared-attachments.css') }}?v={{ filemtime(public_path('css/shared-attachments.css')) }}">
</head>
<body>

<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-icon">{{ $daSystemBrandIcon }}</div>
            <div>
                <div class="brand-title">{{ $daSystemName }}</div>
                <div class="brand-subtitle">{{ $daSystemDepartmentName }}</div>
            </div>
        </div>

                <nav class="side-nav">
            <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'active' : '' }}">👤 الملف الشخصي</a>

            @if(auth()->user()?->hasPermission('dashboard.view'))
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">🏠 لوحة التحكم</a>
            @endif

            @if(auth()->user()?->hasPermission('documents.view'))
                <a href="{{ route('documents.index') }}" class="{{ request()->routeIs('documents.index') ? 'active' : '' }}">📄 الكتب</a>
            @endif

            @if(auth()->user()?->hasPermission('documents.create'))
                <a href="{{ route('documents.create') }}" class="{{ request()->routeIs('documents.create') ? 'active' : '' }}">➕ إضافة كتاب</a>
            @endif

            @if(auth()->user()?->hasPermission('documents.restore'))
                <a href="{{ route('documents.trash') }}" class="{{ request()->routeIs('documents.trash') ? 'active' : '' }}">🗑️ سلة المحذوفات</a>
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

            @if(auth()->user()?->hasPermission('form_links.view'))
                <a href="{{ route('form-links.index') }}" class="{{ request()->routeIs('form-links.*') ? 'active' : '' }}">📝 إدارة النماذج</a>
            @endif

            @if(auth()->user()?->hasPermission('contacts.view'))
                <a href="{{ route('contacts.index') }}" class="{{ request()->routeIs('contacts.*') ? 'active' : '' }}">📇 جهات الاتصال</a>
            @endif

            @if(auth()->user()?->hasPermission('message_templates.view'))
                <a href="{{ route('message-templates.index') }}" class="{{ request()->routeIs('message-templates.*') ? 'active' : '' }}">💬 قوالب الرسائل</a>
            @endif

            @if(auth()->user()?->hasPermission('emails.view'))
                <a href="{{ route('emails.index') }}" class="{{ request()->routeIs('emails.*') || request()->routeIs('documents.email.compose') ? 'active' : '' }}">📧 البريد الإلكتروني</a>
            @endif

            @if(auth()->user()?->hasPermission('whatsapp.view'))
                <a href="{{ route('whatsapp.index') }}" class="{{ request()->routeIs('whatsapp.*') || request()->routeIs('documents.whatsapp.compose') ? 'active' : '' }}">🟢 واتساب</a>
            @endif

            @if(auth()->user()?->hasPermission('attachment_shares.view'))
                <a href="{{ route('shared-attachment-links.index') }}" class="{{ request()->routeIs('shared-attachment-links.*') || request()->routeIs('documents.shared-attachments.create') ? 'active' : '' }}">🔗 مشاركة المرفقات</a>
            @endif

            @if(auth()->user()?->hasPermission('settings.manage'))
                <a href="{{ route('settings.edit') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">⚙️ الإعدادات</a>
            @endif

            @if(auth()->user()?->hasPermission('system_health.view'))
                <a href="{{ route('system-health.index') }}" class="{{ request()->routeIs('system-health.*') ? 'active' : '' }}">🩺 فحص النظام</a>
            @endif

            @if(auth()->user()?->hasPermission('data_quality.view'))
                <a href="{{ route('data-quality.index') }}" class="{{ request()->routeIs('data-quality.*') ? 'active' : '' }}">🧭 جودة البيانات</a>
            @endif

            @if(auth()->user()?->hasPermission('reports.view'))
                <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">📊 التقارير</a>
            @endif

            @if(auth()->user()?->hasPermission('backups.view'))
                <a href="{{ route('backups.index') }}" class="{{ request()->is('backups*') ? 'active' : '' }}">💾 النسخ الاحتياطي</a>
            @endif

            @if(auth()->user()?->hasPermission('users.manage'))
                <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">👥 المستخدمون</a>
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
                <h1>@yield('page_title', $daSystemFullTitle)</h1>
                <p>@yield('page_subtitle', $daSystemTagline)</p>
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
    <script src="{{ asset('js/arabic-ellipsis-display-fix.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/arabic-ui-final-fix.js') }}?v=2026062802"></script>
    {{-- DocumentArchive secure attachment share links --}}
    <script src="{{ asset('js/shared-attachments.js') }}?v={{ filemtime(public_path('js/shared-attachments.js')) }}" defer></script>
    {{-- Arabic UI V4 final guard --}}
    <script src="{{ asset('js/arabic-text-mojibake-v4.js') }}?v={{ filemtime(public_path('js/arabic-text-mojibake-v4.js')) }}" defer></script>
</body>
</html>

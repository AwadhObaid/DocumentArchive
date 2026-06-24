<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'نظام الأرشيف الإلكتروني الخاص بقسم الشحن والتأمين')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-icon">أ</div>
            <div>
                <div class="brand-title">الأرشيف الإلكتروني</div>
                <div class="brand-subtitle">الشحن والتأمين</div>
            </div>
        </div>

        <nav class="nav-menu">
            <a href="{{ route('dashboard') }}" class="nav-link @if(request()->routeIs('dashboard')) active @endif">
                <span class="nav-icon">🏠</span>
                <span>لوحة التحكم</span>
            </a>

            <a href="{{ route('documents.index') }}" class="nav-link @if(request()->routeIs('documents.index') || request()->routeIs('documents.show') || request()->routeIs('documents.edit')) active @endif">
                <span class="nav-icon">📁</span>
                <span>الكتب والمستندات</span>
            </a>

            <a href="{{ route('documents.create') }}" class="nav-link @if(request()->routeIs('documents.create')) active @endif">
                <span class="nav-icon">➕</span>
                <span>إضافة كتاب</span>
            </a>

            <a href="{{ route('documents.trash') }}" class="nav-link @if(request()->routeIs('documents.trash')) active @endif">
                <span class="nav-icon">🗑️</span>
                <span>سلة المحذوفات</span>
            </a>

            <div class="nav-divider"></div>

            <a href="{{ route('departments.index') }}" class="nav-link @if(request()->routeIs('departments.*')) active @endif">
                <span class="nav-icon">🏢</span>
                <span>الإدارات</span>
            </a>

            <a href="{{ route('document-types.index') }}" class="nav-link @if(request()->routeIs('document-types.*')) active @endif">
                <span class="nav-icon">📑</span>
                <span>أنواع الكتب</span>
            </a>

            <a href="{{ route('settings.edit') }}" class="nav-link @if(request()->routeIs('settings.*')) active @endif">
                <span class="nav-icon">⚙️</span>
                <span>الإعدادات</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="system-version">الإصدار التجريبي 1.0</div>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="فتح القائمة">☰</button>

            <div class="topbar-title">
                <div class="page-kicker">نظام الأرشيف الإلكتروني الخاص بقسم الشحن والتأمين</div>
                <h1>@yield('page_title', 'لوحة التحكم')</h1>
            </div>

            <div class="topbar-actions">
                <button type="button" class="theme-toggle" id="themeToggle">🌙</button>
                <div class="user-chip">
                    <span class="user-avatar">م</span>
                    <span>مستخدم النظام</span>
                </div>
            </div>
        </header>

        <section class="content-area">
            @if(session('success'))
                <div class="alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert-error">{{ session('error') }}</div>
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
</body>
</html>

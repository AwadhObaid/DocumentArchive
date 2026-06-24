<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'نظام الأرشيف الإلكتروني')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">أ</div>
            <div>
                <strong>الأرشيف الإلكتروني</strong>
                <span>الشحن والتأمين</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('dashboard') }}">🏠 لوحة التحكم</a>
            <a href="{{ route('documents.index') }}">📁 الكتب</a>
            <a href="{{ route('documents.create') }}">➕ إضافة كتاب</a>
            <a href="{{ route('documents.trash') }}">🗑️ سلة المحذوفات</a>
            <a href="{{ route('departments.index') }}">🏢 الإدارات</a>
            <a href="{{ route('document-types.index') }}">📑 أنواع الكتب</a>
            <a href="{{ route('settings.edit') }}">⚙️ الإعدادات</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button class="sidebar-toggle" type="button" data-sidebar-toggle>☰</button>

            <div class="topbar-title">
                نظام الأرشيف الإلكتروني الخاص بقسم الشحن والتأمين
            </div>

            @auth
                <div class="user-menu">
                    <span>{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="logout-btn">تسجيل خروج</button>
                    </form>
                </div>
            @endauth
        </header>

        <section class="content-container">
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
</body>
</html>

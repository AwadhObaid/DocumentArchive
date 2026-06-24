<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'نظام الأرشيف الإلكتروني الخاص بقسم الشحن والتأمين')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

<div class="topbar">
    <span>نظام الأرشيف الإلكتروني - الشحن والتأمين</span>

    <div class="topbar-links">
        <a href="{{ route('documents.index') }}">الكتب</a>
        <a href="{{ route('documents.create') }}">إضافة كتاب</a>
        <a href="{{ route('documents.trash') }}">سلة المحذوفات</a>
        <a href="{{ route('departments.index') }}">الإدارات</a>
        <a href="{{ route('document-types.index') }}">أنواع الكتب</a>
        <a href="{{ route('settings.edit') }}">الإعدادات</a>
    </div>
</div>

<div class="container">
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
</div>

<script src="{{ asset('js/app.js') }}"></script>
</body>
</html>

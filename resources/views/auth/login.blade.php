<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول - نظام الأرشيف الإلكتروني</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="brand-mark">أ</div>

        <h1>نظام الأرشيف الإلكتروني</h1>
        <p class="subtitle">الخاص بقسم الشحن والتأمين</p>

        @if($errors->any())
            <div class="login-error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf

            <div class="form-group">
                <label>اسم المستخدم</label>
                <input type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label>كلمة المرور</label>
                <input type="password" name="password" required autocomplete="current-password">
            </div>

            <label class="remember-row">
                <input type="checkbox" name="remember" value="1">
                <span>تذكرني</span>
            </label>

            <button type="submit" class="login-btn">دخول</button>
        </form>

        <div class="login-footer">
            <span>Document Archive System</span>
        </div>
    </div>
</div>
</body>
</html>

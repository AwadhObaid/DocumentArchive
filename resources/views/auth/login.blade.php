<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول - نظام
 الأرشيف الإلكتروني</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-body">

<div class="login-card">
    <div class="login-logo">📁</div>
    <h1>نظام
 الأرشيف الإلكتروني</h1>
    <p>الخاص بقسم
 الشحن والتأم
ين</p>

    @if($errors->any())
        <div class="alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login.post') }}">
        @csrf

        <div class="form-group">
            <label>اسم
 الم
ستخدم
</label>
            <input type="text" name="username" value="{{ old('username') }}" autofocus required>
        </div>

        <div class="form-group">
            <label>كلم
ة الم
رور</label>
            <input type="password" name="password" required>
        </div>

        <label class="checkbox-line">
            <input type="checkbox" name="remember" value="1">
            تذكرني
        </label>

        <button type="submit" class="btn btn-primary login-btn">دخول</button>
    </form>
</div>

</body>
</html>

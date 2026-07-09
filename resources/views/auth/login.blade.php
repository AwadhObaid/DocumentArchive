<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول - نظام الأرشيف الإلكتروني</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cairo-global.css') }}">
    <style>
        * { box-sizing: border-box; }
        body.login-body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: Cairo, Tahoma, Arial, sans-serif;
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, .24), transparent 32%),
                radial-gradient(circle at bottom left, rgba(20, 184, 166, .18), transparent 28%),
                #0f172a;
            color: #e5e7eb;
        }
        .login-shell { width: min(100%, 460px); }
        .login-card {
            width: 100%;
            background: rgba(15, 23, 42, .92);
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 26px;
            padding: 30px;
            box-shadow: 0 26px 80px rgba(0, 0, 0, .38);
            backdrop-filter: blur(16px);
        }
        .login-logo {
            width: 74px;
            height: 74px;
            margin: 0 auto 18px;
            display: grid;
            place-items: center;
            border-radius: 22px;
            background: linear-gradient(145deg, #1d4ed8, #38bdf8);
            box-shadow: 0 16px 36px rgba(37, 99, 235, .32);
            font-size: 34px;
        }
        .login-card h1 { margin: 0; text-align: center; font-size: 25px; color: #fff; }
        .login-card .subtitle { margin: 8px 0 24px; text-align: center; color: #b6c3d7; line-height: 1.8; }
        .login-alert {
            border-radius: 16px;
            border: 1px solid rgba(248, 113, 113, .35);
            background: rgba(127, 29, 29, .32);
            color: #fecaca;
            padding: 12px 14px;
            margin-bottom: 18px;
            line-height: 1.8;
        }
        .login-success {
            border-radius: 16px;
            border: 1px solid rgba(74, 222, 128, .32);
            background: rgba(22, 101, 52, .28);
            color: #bbf7d0;
            padding: 12px 14px;
            margin-bottom: 18px;
            line-height: 1.8;
        }
        .login-field { margin-bottom: 16px; }
        .login-field label { display: block; margin-bottom: 8px; color: #dbeafe; font-weight: 800; }
        .login-field input {
            width: 100%;
            border: 1px solid rgba(148, 163, 184, .25);
            background: rgba(2, 6, 23, .55);
            color: #fff;
            border-radius: 16px;
            padding: 13px 14px;
            outline: none;
            font-size: 15px;
        }
        .login-field input:focus { border-color: #60a5fa; box-shadow: 0 0 0 4px rgba(96, 165, 250, .16); }
        .login-options { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 6px 0 20px; color: #cbd5e1; }
        .login-options label { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
        .login-btn {
            width: 100%;
            border: 0;
            border-radius: 16px;
            padding: 13px 18px;
            color: #fff;
            background: linear-gradient(135deg, #2563eb, #0ea5e9);
            font-weight: 900;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 15px 30px rgba(14, 165, 233, .22);
        }
        .login-note { margin: 18px 0 0; text-align: center; color: #94a3b8; font-size: 13px; line-height: 1.8; }
        @media (max-width: 520px) {
            body.login-body { padding: 14px; }
            .login-card { padding: 22px; border-radius: 22px; }
            .login-card h1 { font-size: 21px; }
        }
    </style>

{{-- auth-no-cache-v44:meta --}}
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
</head>
<body class="login-body">
<div class="login-shell">
    <div class="login-card">
        <div class="login-logo">📁</div>
        <h1>نظام الأرشيف الإلكتروني</h1>
        <p class="subtitle">قسم الشحن والتأمين</p>

        @if(session('success'))
            <div class="login-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="login-alert">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" autocomplete="on" data-da-login-form="1" data-lpignore="true" data-1p-ignore="true">
            @csrf
{{-- auth-no-autofill-v44:applied --}}
<div class="auth-autofill-decoys" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;opacity:0;">
    <input type="text" name="da_decoy_username_v44" tabindex="-1" autocomplete="username">
    <input type="password" name="da_decoy_password_v44" tabindex="-1" autocomplete="current-password">
</div>

            <div class="login-field">
                <label for="username">اسم المستخدم</label>
                <input id="username" type="text" name="username" value="{{ old('username') }}" autofocus required autocomplete="username" placeholder="أدخل اسم المستخدم" autocapitalize="none" spellcheck="false" data-da-secure-login-input="username" data-lpignore="true" data-1p-ignore="true">
            </div>

            <div class="login-field">
                <label for="password">كلمة المرور</label>
                <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="أدخل كلمة المرور" data-da-secure-login-input="password" data-lpignore="true" data-1p-ignore="true">
            </div>

            <div class="login-options">
                <label>
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) disabled data-da-remember-disabled-v44="1">
                    <span>تذكرني</span>
                </label>
            </div>

            <button type="submit" class="login-btn">تسجيل الدخول</button>
        </form>

        <p class="login-note">حسابك لا يعمل إذا تم تعطيله من مدير النظام.</p>
    </div>
</div>

{{-- auth-no-autofill-v44:script --}}
<script src="{{ asset('js/auth-no-autofill-v44.js') }}?v={{ filemtime(public_path('js/auth-no-autofill-v44.js')) }}" defer></script>
</body>
</html>

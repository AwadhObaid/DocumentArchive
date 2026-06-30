@extends('layouts.app')

@section('title', 'تغيير كلمة المرور')
@section('page_title', 'تغيير كلمة المرور')
@section('page_subtitle', 'تغيير كلمة مرور المستخدم المحدد من مدير النظام')

@section('content')
    <div class="page-title">
        <h2>تغيير كلمة المرور</h2>
        <a href="{{ route('users.index') }}" class="btn btn-secondary">رجوع</a>
    </div>

    <div class="card user-password-card">
        <div class="password-user-summary">
            <div class="password-avatar">{{ mb_substr((string)($user->name ?: $user->username ?: 'م'), 0, 1) }}</div>
            <div>
                <h3>{{ $user->name }}</h3>
                <p>اسم المستخدم: <strong>{{ $user->username }}</strong></p>
                <p>الدور: <strong>{{ $user->role_name ?? $user->role }}</strong></p>
            </div>
        </div>

        <div class="security-note">
            سيتم تغيير كلمة مرور هذا المستخدم مباشرة. أبلغه بكلمة المرور الجديدة بطريقة آمنة، ويفضل أن يغيرها من صفحة الملف الشخصي بعد أول دخول.
        </div>

        <form method="POST" action="{{ route('users.password.update', $user) }}" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="form-group">
                    <label for="password">كلمة المرور الجديدة</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password">
                    <small>الحد الأدنى 8 أحرف.</small>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">تأكيد كلمة المرور الجديدة</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-success">حفظ كلمة المرور الجديدة</button>
            </div>
        </form>
    </div>

    <style>
        .user-password-card { max-width: 860px; }
        .password-user-summary { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
        .password-avatar {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #2563eb, #0ea5e9);
            color: #fff;
            font-size: 28px;
            font-weight: 900;
        }
        .password-user-summary h3 { margin: 0 0 6px; }
        .password-user-summary p { margin: 4px 0; color: #cbd5e1; }
        .security-note {
            margin: 0 0 18px;
            padding: 12px 14px;
            border-radius: 16px;
            color: #fde68a;
            background: rgba(120, 53, 15, .22);
            border: 1px solid rgba(251, 191, 36, .26);
            line-height: 1.9;
        }
    </style>
@endsection

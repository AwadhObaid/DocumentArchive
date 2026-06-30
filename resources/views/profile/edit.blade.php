@extends('layouts.app')

@section('title', 'الملف الشخصي')
@section('page_title', 'الملف الشخصي')
@section('page_subtitle', 'تعديل بيانات حسابك وتغيير كلمة المرور الخاصة بك')

@section('content')
    <div class="page-title">
        <h2>الملف الشخصي</h2>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">رجوع</a>
    </div>

    <div class="profile-security-page">
        <div class="profile-hero card">
            <div class="profile-avatar-lg">{{ mb_substr((string)($user->name ?: $user->username ?: 'م'), 0, 1) }}</div>
            <div class="profile-hero-text">
                <h3>{{ $user->name }}</h3>
                <p>اسم المستخدم: <strong>{{ $user->username }}</strong></p>
                <p>الدور: <strong>{{ $user->role_name ?? $user->role }}</strong></p>
                <p>الحالة: <strong>{{ $user->is_active ? 'نشط' : 'معطل' }}</strong></p>
            </div>
            <div class="profile-login-info">
                <span>آخر دخول</span>
                <strong>{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'لا يوجد تسجيل سابق' }}</strong>
            </div>
        </div>

        <div class="card profile-section-card">
            <div class="section-heading-row">
                <div>
                    <h3 class="section-title">بيانات الحساب</h3>
                    <p class="muted-note">يمكنك تعديل الاسم والبريد الإلكتروني وبيانات التواصل الخاصة بحسابك.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">الاسم</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
                    </div>

                    <div class="form-group">
                        <label for="profile_username">اسم المستخدم</label>
                        <input id="profile_username" type="text" value="{{ $user->username }}" readonly class="readonly-input">
                        <small>اسم المستخدم لا يتغير من هذه الصفحة حفاظاً على سجلات النظام.</small>
                    </div>

                    <div class="form-group">
                        <label for="email">البريد الإلكتروني</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email">
                    </div>

                    @if($hasPhoneColumn ?? false)
                        <div class="form-group">
                            <label for="phone">الهاتف</label>
                            <input id="phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel">
                        </div>
                    @endif
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">حفظ البيانات</button>
                </div>
            </form>
        </div>

        <div class="card profile-section-card">
            <div class="section-heading-row">
                <div>
                    <h3 class="section-title">تغيير كلمة المرور</h3>
                    <p class="muted-note">اختر كلمة مرور قوية لا تقل عن 8 أحرف، ولا تشاركها مع أي مستخدم آخر.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.password.update') }}" autocomplete="off">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-group">
                        <label for="current_password">كلمة المرور الحالية</label>
                        <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label for="new_password">كلمة المرور الجديدة</label>
                        <input id="new_password" type="password" name="password" required autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">تأكيد كلمة المرور الجديدة</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>

                <div class="password-rules">
                    <span>🔐 استخدم كلمة مرور مختلفة عن الحالية.</span>
                    <span>✅ الحد الأدنى 8 أحرف.</span>
                    <span>🛡️ بعد التغيير سيتم تحديث الجلسة الحالية.</span>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">تغيير كلمة المرور</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .profile-security-page { display: grid; gap: 18px; }
        .profile-hero { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
        .profile-avatar-lg {
            width: 72px;
            height: 72px;
            border-radius: 22px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #2563eb, #0ea5e9);
            color: #fff;
            font-size: 30px;
            font-weight: 900;
            box-shadow: 0 14px 26px rgba(37, 99, 235, .24);
        }
        .profile-hero-text { flex: 1; min-width: 220px; }
        .profile-hero-text h3 { margin: 0 0 8px; color: #e5e7eb; }
        .profile-hero-text p { margin: 4px 0; color: #cbd5e1; }
        .profile-login-info {
            min-width: 190px;
            border-radius: 18px;
            padding: 13px 15px;
            background: rgba(30, 41, 59, .55);
            border: 1px solid rgba(148, 163, 184, .18);
        }
        .profile-login-info span { display: block; color: #94a3b8; font-size: 13px; margin-bottom: 4px; }
        .profile-login-info strong { color: #e5e7eb; }
        .section-heading-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .profile-section-card .section-title { margin: 0 0 6px; }
        .muted-note { color: #94a3b8; margin: 0; line-height: 1.8; }
        .readonly-input { opacity: .84; cursor: not-allowed; }
        .password-rules {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            color: #cbd5e1;
            font-size: 13px;
        }
        .password-rules span {
            border: 1px solid rgba(148, 163, 184, .18);
            background: rgba(15, 23, 42, .42);
            padding: 8px 10px;
            border-radius: 999px;
        }
        @media (max-width: 720px) {
            .profile-hero { align-items: flex-start; }
            .profile-login-info { width: 100%; }
            .password-rules span { width: 100%; border-radius: 14px; }
        }
    </style>
@endsection

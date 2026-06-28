@extends('layouts.app')

@section('title', 'الملف الشخصي')
@section('page_title', 'الملف الشخصي')
@section('page_subtitle', 'تعديل بيانات حسابك وتغيير كلمة المرور الخاصة بك')

@section('content')
    <div class="page-title">
        <h2>الملف الشخصي</h2>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">رجوع</a>
    </div>

    <div class="profile-page">
        <div class="card profile-card">
            <div class="profile-card-header">
                <div class="profile-avatar-lg">{{ mb_substr($user->name ?? $user->username ?? 'م
', 0, 1) }}</div>
                <div>
                    <h3>{{ $user->name }}</h3>
                    <p>اسمالمستخدم
: <strong>{{ $user->username }}</strong></p>
                    <p>الدور: <strong>{{ $user->role_name ?? $user->role }}</strong></p>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 class="section-title">بيانات الحساب</h3>

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-group">
                        <label>الاسم
</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                    </div>

                    <div class="form-group">
                        <label>اسمالمستخدم
</label>
                        <input type="text" value="{{ $user->username }}" readonly class="readonly-input">
                        <small>اسمالمستخدملا يتغير من هذه الصفحة حفاظاً على سجلات النظام
.</small>
                    </div>

                    <div class="form-group">
                        <label>البريد الإلكتروني</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}">
                    </div>

                    @if($hasPhoneColumn ?? false)
                        <div class="form-group">
                            <label>الهاتف</label>
                            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}">
                        </div>
                    @endif
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">حفظ البيانات</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h3 class="section-title">تغيير كلمة المرور</h3>
            <p class="muted-note">اختر كلمة مرور قوية، ولا تشاركها مع أي مستخدمآخر.</p>

            <form method="POST" action="{{ route('profile.password.update') }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-group">
                        <label>كلمة المرور الحالية</label>
                        <input type="password" name="current_password" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label>كلمة المرور الجديدة</label>
                        <input type="password" name="password" required autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label>تأكيد كلمة المرور الجديدة</label>
                        <input type="password" name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">تغيير كلمة المرور</button>
                </div>
            </form>
        </div>
    </div>
@endsection

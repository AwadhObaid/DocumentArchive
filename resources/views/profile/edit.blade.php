@extends('layouts.app')

@section('title', 'الم
لف الشخصي')
@section('page_title', 'الم
لف الشخصي')
@section('page_subtitle', 'تعديل بيانات حسابك وتغيير كلم
ة الم
رور الخاصة بك')

@section('content')
    <div class="page-title">
        <h2>الم
لف الشخصي</h2>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">رجوع</a>
    </div>

    <div class="profile-page">
        <div class="card profile-card">
            <div class="profile-card-header">
                <div class="profile-avatar-lg">{{ mb_substr($user->name ?? $user->username ?? 'م
', 0, 1) }}</div>
                <div>
                    <h3>{{ $user->name }}</h3>
                    <p>اسم
 الم
ستخدم
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
                        <label>اسم
 الم
ستخدم
</label>
                        <input type="text" value="{{ $user->username }}" readonly class="readonly-input">
                        <small>اسم
 الم
ستخدم
 لا يتغير م
ن هذه الصفحة حفاظاً على سجلات النظام
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
            <h3 class="section-title">تغيير كلم
ة الم
رور</h3>
            <p class="muted-note">اختر كلم
ة م
رور قوية، ولا تشاركها م
ع أي م
ستخدم
 آخر.</p>

            <form method="POST" action="{{ route('profile.password.update') }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-group">
                        <label>كلم
ة الم
رور الحالية</label>
                        <input type="password" name="current_password" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label>كلم
ة الم
رور الجديدة</label>
                        <input type="password" name="password" required autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label>تأكيد كلم
ة الم
رور الجديدة</label>
                        <input type="password" name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">تغيير كلم
ة الم
رور</button>
                </div>
            </form>
        </div>
    </div>
@endsection

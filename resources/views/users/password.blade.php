@extends('layouts.app')

@section('title', 'تغيير كلمة المرور')
@section('page_title', 'تغيير كلمة المرور')
@section('page_subtitle', 'تغيير كلمة مرور المستخدم المحدد')

@section('content')
    <div class="page-title">
        <h2>تغيير كلمة المرور</h2>
        <a href="{{ route('users.index') }}" class="btn btn-secondary">رجوع</a>
    </div>

    <div class="card">
        <p style="margin-top:0;color:#94a3b8;">المستخدم: <strong>{{ $user->name }}</strong></p>

        <form method="POST" action="{{ route('users.password.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="form-group">
                    <label>كلمة المرور الجديدة</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label>تأكيد كلمة المرور</label>
                    <input type="password" name="password_confirmation" required>
                </div>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-success">حفظ كلمة المرور</button>
            </div>
        </form>
    </div>
@endsection
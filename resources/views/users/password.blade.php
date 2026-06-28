@extends('layouts.app')

@section('title', 'تغيير كلم
ة الم
رور')
@section('page_title', 'تغيير كلم
ة الم
رور')
@section('page_subtitle', 'تحديث كلم
ة م
رور الم
ستخدم
 الم
حدد')

@section('content')
    <div class="page-title">
        <h2>تغيير كلم
ة م
رور: {{ $user->name }}</h2>
        @if(auth()->user()?->hasPermission('users.manage'))
        <a href="{{ route('users.index') }}" class="btn btn-secondary">رجوع</a>
        @endif
    </div>

    <div class="card">
        <form method="POST" action="{{ route('users.password.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="form-group">
                    <label>كلم
ة الم
رور الجديدة</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label>تأكيد كلم
ة الم
رور</label>
                    <input type="password" name="password_confirmation" required>
                </div>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-success">حفظ كلم
ة الم
رور</button>
            </div>
        </form>
    </div>
@endsection

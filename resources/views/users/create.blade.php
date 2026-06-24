@extends('layouts.app')

@section('title', 'إضافة مستخدم')
@section('page_title', 'إضافة مستخدم')
@section('page_subtitle', 'إنشاء حساب جديد داخل نظام الأرشيف الإلكتروني')

@section('content')
    <div class="page-title">
        <h2>إضافة مستخدم</h2>
        <a href="{{ route('users.index') }}" class="btn btn-secondary">رجوع</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label>الاسم</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>

                <div class="form-group">
                    <label>اسم المستخدم</label>
                    <input type="text" name="username" value="{{ old('username') }}" required>
                </div>

                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <input type="email" name="email" value="{{ old('email') }}">
                </div>

                <div class="form-group">
                    <label>الهاتف</label>
                    <input type="text" name="phone" value="{{ old('phone') }}">
                </div>

                <div class="form-group">
                    <label>الدور</label>
                    <select name="role" required>
                        <option value="user" @selected(old('role') === 'user')>مستخدم</option>
                        <option value="viewer" @selected(old('role') === 'viewer')>مشاهد فقط</option>
                        <option value="admin" @selected(old('role') === 'admin')>مدير النظام</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>الحالة</label>
                    <label class="checkbox-line">
                        <input type="checkbox" name="is_active" value="1" checked>
                        مستخدم نشط
                    </label>
                </div>

                <div class="form-group">
                    <label>كلمة المرور</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label>تأكيد كلمة المرور</label>
                    <input type="password" name="password_confirmation" required>
                </div>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-success">حفظ المستخدم</button>
            </div>
        </form>
    </div>
@endsection

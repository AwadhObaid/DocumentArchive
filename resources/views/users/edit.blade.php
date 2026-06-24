@extends('layouts.app')

@section('title', 'تعديل مستخدم')
@section('page_title', 'تعديل مستخدم')
@section('page_subtitle', 'تعديل بيانات الحساب والدور والحالة')

@section('content')
    <div class="page-title">
        <h2>تعديل مستخدم</h2>
        <a href="{{ route('users.index') }}" class="btn btn-secondary">رجوع</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="form-group">
                    <label>الاسم</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                </div>

                <div class="form-group">
                    <label>اسم المستخدم</label>
                    <input type="text" name="username" value="{{ old('username', $user->username) }}" required>
                </div>

                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}">
                </div>

                <div class="form-group">
                    <label>الهاتف</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}">
                </div>

                <div class="form-group">
                    <label>الدور</label>
                    <select name="role" required>
                        <option value="user" @selected(old('role', $user->role) === 'user')>مستخدم</option>
                        <option value="viewer" @selected(old('role', $user->role) === 'viewer')>مشاهد فقط</option>
                        <option value="admin" @selected(old('role', $user->role) === 'admin')>مدير النظام</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>الحالة</label>
                    <label class="checkbox-line">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                        مستخدم نشط
                    </label>
                </div>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-success">حفظ التعديلات</button>
            </div>
        </form>
    </div>
@endsection

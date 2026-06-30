@extends('layouts.app')

@section('title', 'تعديل مستخدم')
@section('page_title', 'تعديل مستخدم')
@section('page_subtitle', 'تحديث بيانات المستخدم وصلاحياته')

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
                    <select name="role" id="roleSelect" required>
                        <option value="user" @selected(old('role', $user->role) === 'user')>مستخدم</option>
                        <option value="viewer" @selected(old('role', $user->role) === 'viewer')>مشاهد فقط</option>
                        <option value="admin" @selected(old('role', $user->role) === 'admin')>مدير النظام</option>
                    </select>
                    <small class="hint">مدير النظام يملك كل الصلاحيات تلقائياً.</small>
                </div>

                <div class="form-group">
                    <label>الحالة</label>
                    <label class="checkbox-line">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                        مستخدم نشط
                    </label>
                </div>
            </div>

            @php
                $selectedPermissions = old('permissions', $defaultPermissions ?? []);
                if (!is_array($selectedPermissions)) {
                    $selectedPermissions = [];
                }
            @endphp

            <div class="permissions-panel" id="permissionsPanel">
                <div class="permissions-header">
                    <div>
                        <h3>الصلاحيات التفصيلية</h3>
                        <p>تحديد الصلاحيات الخاصة بهذا المستخدم. عند اختيار مدير النظام يتم تجاهل هذه القائمة ويُمنح كل الصلاحيات.</p>
                    </div>
                    <div class="actions">
                        <button type="button" class="btn btn-secondary" onclick="toggleAllPermissions(true)">تحديد الكل</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleAllPermissions(false)">إلغاء الكل</button>
                    </div>
                </div>

                <div class="permissions-grid">
                    @foreach ($permissionGroups as $group)
                        <div class="permission-group">
                            <strong>{{ $group['label'] }}</strong>
                            @foreach ($group['permissions'] as $permissionKey => $permissionLabel)
                                <label class="permission-item">
                                    <input type="checkbox" name="permissions[]" value="{{ $permissionKey }}" @checked(in_array('*', $selectedPermissions, true) || in_array($permissionKey, $selectedPermissions, true))>
                                    <span>{{ $permissionLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-success">حفظ التعديلات</button>
            </div>
        </form>
    </div>

    @include('users.partials.permissions-style')
@endsection
@extends('layouts.app')

@section('title', 'إضافة مستخدم
')
@section('page_title', 'إضافة مستخدم
')
@section('page_subtitle', 'إنشاء حساب جديد وتحديد صلاحياته داخل نظام الأرشيف الإلكتروني')

@section('content')
    <div class="page-title">
        <h2>إضافة مستخدم
</h2>
        @if(auth()->user()?->hasPermission('users.manage'))
        <a href="{{ route('users.index') }}" class="btn btn-secondary">رجوع</a>
        @endif
    </div>

    <div class="card">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label>الاسم
</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>

                <div class="form-group">
                    <label>اسمالمستخدم
</label>
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
                    <select name="role" id="roleSelect" required>
                        <option value="user" @selected(old('role', 'user') === 'user')>مستخدم
</option>
                        <option value="viewer" @selected(old('role') === 'viewer')>مشاهد فقط</option>
                        <option value="admin" @selected(old('role') === 'admin')>مدير النظام
</option>
                    </select>
                    <small style="display:block;margin-top:6px;color:#64748b;">مدير النظاميملك كل الصلاحيات تلقائياً.</small>
                </div>

                <div class="form-group">
                    <label>الحالة</label>
                    <label class="checkbox-line">
                        <input type="checkbox" name="is_active" value="1" checked>
                        مستخدمنشط
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

            @php
                $selectedPermissions = old('permissions', $defaultPermissions ?? []);
                if (!is_array($selectedPermissions)) {
                    $selectedPermissions = [];
                }
            @endphp

            <div class="permissions-panel">
                <div class="permissions-header">
                    <div>
                        <h3>الصلاحيات التفصيلية</h3>
                        <p>اختر ما يمكن لهذا المستخدمالوصول إليه داخل النظام
.</p>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="toggleAllPermissions(true)">تحديد الكل</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleAllPermissions(false)">إلغاء الكل</button>
                </div>

                <div class="permissions-grid">
                    @foreach ($permissionGroups as $group)
                        <div class="permission-group">
                            <strong>{{ $group['label'] }}</strong>
                            @foreach ($group['permissions'] as $permissionKey => $permissionLabel)
                                <label class="permission-item">
                                    <input type="checkbox" name="permissions[]" value="{{ $permissionKey }}" @checked(in_array($permissionKey, $selectedPermissions, true))>
                                    <span>{{ $permissionLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-success">حفظ المستخدم
</button>
            </div>
        </form>
    </div>

    <style>
        .permissions-panel { margin-top: 24px; border: 1px solid #e5e7eb; border-radius: 16px; padding: 18px; background: #f8fafc; }
        .permissions-header { display: flex; align-items: center; gap: 10px; justify-content: space-between; flex-wrap: wrap; margin-bottom: 16px; }
        .permissions-header h3 { margin: 0 0 4px; font-size: 18px; }
        .permissions-header p { margin: 0; color: #64748b; }
        .permissions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px; }
        .permission-group { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px; }
        .permission-group strong { display: block; margin-bottom: 10px; color: #0f172a; }
        .permission-item { display: flex; gap: 8px; align-items: center; margin: 8px 0; color: #334155; }
        .permission-item input { width: auto; }
    </style>

    <script>
        function toggleAllPermissions(checked) {
            document.querySelectorAll('input[name="permissions[]"]').forEach((checkbox) => checkbox.checked = checked);
        }
    </script>
@endsection

@extends('layouts.app')

@section('title', 'الم
ستخدم
ون')
@section('page_title', 'إدارة الم
ستخدم
ين')
@section('page_subtitle', 'إضافة وتعديل وتفعيل وتعطيل م
ستخدم
ي النظام
')

@section('content')
    <div class="page-title">
        <h2>الم
ستخدم
ون</h2>

        @if(auth()->user()?->hasPermission('users.manage'))
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            + إضافة م
ستخدم

        </a>
        @endif
    </div>

    <div class="card">
        <form method="GET" action="{{ route('users.index') }}">
            <div class="form-grid">
                <div class="form-group">
                    <label>بحث</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="الاسم
 / اسم
 الم
ستخدم
 / البريد / الهاتف">
                </div>

                <div class="form-group">
                    <label>الدور</label>
                    <select name="role">
                        <option value="">كل الأدوار</option>
                        <option value="admin" @selected(request('role') === 'admin')>م
دير النظام
</option>
                        <option value="user" @selected(request('role') === 'user')>م
ستخدم
</option>
                        <option value="viewer" @selected(request('role') === 'viewer')>م
شاهد فقط</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>الحالة</label>
                    <select name="is_active">
                        <option value="">الكل</option>
                        <option value="1" @selected(request('is_active') === '1')>نشط</option>
                        <option value="0" @selected(request('is_active') === '0')>م
عطل</option>
                    </select>
                </div>

                <div class="form-group" style="justify-content:flex-end;">
                    <label>&nbsp;</label>
                    <div class="actions">
                        <button class="btn btn-primary">بحث</button>
                        @if(auth()->user()?->hasPermission('users.manage'))
                        <a href="{{ route('users.index') }}" class="btn btn-secondary">إلغاء</a>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>الاسم
</th>
                <th>اسم
 الم
ستخدم
</th>
                <th>الدور</th>
                <th>الحالة</th>
                <th>آخر دخول</th>
                <th>إجراءات</th>
            </tr>
            </thead>

            <tbody>
            @forelse($users as $user)
                <tr>
                    <td><strong>{{ $user->name }}</strong></td>
                    <td>{{ $user->username }}</td>
                    <td><span class="badge">{{ $user->role_name }}</span></td>
                    <td>
                        @if($user->is_active)
                            <span class="badge badge-success">نشط</span>
                        @else
                            <span class="badge badge-danger">م
عطل</span>
                        @endif
                    </td>
                    <td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>
                        <div class="actions">
                            @if(auth()->user()?->hasPermission('users.manage'))
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-primary">تعديل</a>
                            @endif
                            <a href="{{ route('users.password.edit', $user) }}" class="btn btn-warning">كلم
ة الم
رور</a>

                            @if($user->is_active)
                                <form method="POST" action="{{ route('users.deactivate', $user) }}" data-confirm="هل تريد تعطيل هذا الم
ستخدم
؟">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-danger" type="submit">تعطيل</button>
                                </form>
                            @else
                                @if(auth()->user()?->hasPermission('users.manage'))
                                <form method="POST" action="{{ route('users.activate', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-success" type="submit">تفعيل</button>
                                </form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">لا يوجد م
ستخدم
ون.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="pagination">
            {{ $users->links() }}
        </div>
    </div>
@endsection

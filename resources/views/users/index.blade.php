@extends('layouts.app')

@section('title', 'المستخدمون')
@section('page_title', 'إدارة المستخدمين')
@section('page_subtitle', 'إضافة وتعديل وتفعيل وتعطيل مستخدمي النظام')

@section('content')
    <div class="page-title">
        <h2>المستخدمون</h2>
        <a href="{{ route('users.create') }}" class="btn btn-primary">+ إضافة مستخدم</a>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('users.index') }}">
            <div class="form-grid">
                <div class="form-group">
                    <label>بحث</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="الاسم / اسم المستخدم / البريد / الهاتف">
                </div>

                <div class="form-group">
                    <label>الدور</label>
                    <select name="role">
                        <option value="">كل الأدوار</option>
                        <option value="admin" @selected(request('role') === 'admin')>مدير النظام</option>
                        <option value="user" @selected(request('role') === 'user')>مستخدم</option>
                        <option value="viewer" @selected(request('role') === 'viewer')>مشاهد فقط</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>الحالة</label>
                    <select name="is_active">
                        <option value="">الكل</option>
                        <option value="1" @selected(request('is_active') === '1')>نشط</option>
                        <option value="0" @selected(request('is_active') === '0')>معطل</option>
                    </select>
                </div>

                <div class="form-group" style="justify-content:flex-end;">
                    <label>&nbsp;</label>
                    <div class="actions">
                        <button class="btn btn-primary">بحث</button>
                        <a href="{{ route('users.index') }}" class="btn btn-secondary">إلغاء</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card users-table-card">
        <div class="table-scroll users-table-scroll">
            <table class="users-table">
                <thead>
                <tr>
                    <th>الاسم</th>
                    <th>اسم المستخدم</th>
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
                                <span class="badge badge-danger">معطل</span>
                            @endif
                        </td>
                        <td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-primary">تعديل</a>
                                <a href="{{ route('users.password.edit', $user) }}" class="btn btn-warning">كلمة المرور</a>

                                @if($user->is_active)
                                    <form method="POST" action="{{ route('users.deactivate', $user) }}" data-confirm="هل تريد تعطيل هذا المستخدم؟">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-danger" type="submit">تعطيل</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('users.activate', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-success" type="submit">تفعيل</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">لا يوجد مستخدمون.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $users->links() }}
        </div>
    </div>

    <style>
        .users-table-scroll { width: 100%; overflow-x: auto; overflow-y: hidden; padding-bottom: 8px; }
        .users-table { width: 100%; min-width: 760px; }
        .users-table th, .users-table td { white-space: nowrap; }
    </style>
@endsection
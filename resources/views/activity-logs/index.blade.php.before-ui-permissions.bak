@extends('layouts.app')

@section('title', 'سجل النشاط')
@section('page_title', 'سجل النشاط')
@section('page_subtitle', 'متابعة العمليات التي تمت على الكتب، المرفقات، والطباعة')

@section('content')
    <div class="page-title">
        <h1>سجل النشاط</h1>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('activity-logs.index') }}">
            <div class="form-grid">
                <div class="form-group">
                    <label>بحث</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="العملية / الوصف / رقم السجل">
                </div>

                <div class="form-group">
                    <label>المستخدم</label>
                    <select name="user_id">
                        <option value="">كل المستخدمين</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>
                                {{ $user->name }} - {{ $user->username }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>نوع العملية</label>
                    <select name="action">
                        <option value="">كل العمليات</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>
                                {{ \App\Models\ActivityLog::actionLabel($action) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>من تاريخ</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}">
                </div>

                <div class="form-group">
                    <label>إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}">
                </div>

                <div class="form-group" style="justify-content:flex-end;">
                    <label>&nbsp;</label>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">بحث</button>
                        <a href="{{ route('activity-logs.index') }}" class="btn btn-secondary">إلغاء الفلترة</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>التاريخ والوقت</th>
                <th>المستخدم</th>
                <th>العملية</th>
                <th>الوصف</th>
                <th>العنصر</th>
            </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? 'النظام' }}</td>
                    <td><span class="badge">{{ $log->action_label }}</span></td>
                    <td>{{ $log->description ?? '-' }}</td>
                    <td>{{ $log->model_label }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">لا توجد عمليات مسجلة حتى الآن.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="pagination">
            {{ $logs->links() }}
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'سجل حركة الكتاب')
@section('page_title', 'سجل حركة الكتاب')
@section('page_subtitle', 'كل العم
ليات الخاصة بالكتاب وم
رفقاته')

@section('content')
    <div class="page-title">
        <h1>سجل حركة الكتاب رقم
 {{ $document->reference_number }}</h1>

        <div class="actions">
            @if(auth()->user()?->hasPermission('documents.view'))
            <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary">رجوع للكتاب</a>
            @endif
            @if(auth()->user()?->hasPermission('activity_logs.view'))
            <a href="{{ route('activity-logs.index') }}" class="btn btn-primary">سجل النشاط العام
</a>
            @endif
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>التاريخ والوقت</th>
                <th>الم
ستخدم
</th>
                <th>العم
لية</th>
                <th>الوصف</th>
                <th>العنصر</th>
            </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? 'النظام
' }}</td>
                    <td><span class="badge">{{ $log->action_label }}</span></td>
                    <td>{{ $log->description ?? '-' }}</td>
                    <td>{{ $log->model_label }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">لا توجد عم
ليات م
سجلة على هذا الكتاب حتى الآن.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="pagination">
            {{ $logs->links() }}
        </div>
    </div>
@endsection

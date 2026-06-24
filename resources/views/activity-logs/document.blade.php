@extends('layouts.app')

@section('title', 'سجل حركة الكتاب')
@section('page_title', 'سجل حركة الكتاب')
@section('page_subtitle', 'كل العمليات الخاصة بالكتاب ومرفقاته')

@section('content')
    <div class="page-title">
        <h1>سجل حركة الكتاب رقم {{ $document->reference_number }}</h1>

        <div class="actions">
            <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary">رجوع للكتاب</a>
            <a href="{{ route('activity-logs.index') }}" class="btn btn-primary">سجل النشاط العام</a>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>التاريخ والوقت</th>
                <th>المستخدم</th>
                <th>العملية</th>
                <th>الوصف</th>
            </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? 'النظام' }}</td>
                    <td><span class="badge">{{ $log->action }}</span></td>
                    <td>{{ $log->description ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">لا توجد عمليات مسجلة على هذا الكتاب حتى الآن.</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="pagination">
            {{ $logs->links() }}
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'فحص النظام
')

@section('content')
    <div class="page-title">
        <div>
            <h1>فحص النظام
</h1>
            <p class="muted">فحص سريع لحالة قاعدة البيانات، التخزين، النسخ الاحتياطي، والجلسات.</p>
        </div>

        <div class="actions">
            <a href="{{ route('system-health.index') }}" class="btn btn-primary">تحديث الفحص</a>
            <a href="{{ route('dashboard') }}" class="btn btn-secondary">رجوع</a>
        </div>
    </div>

    <div class="stats-grid" style="margin-bottom: 18px;">
        <div class="stat-card">
            <div class="stat-label">سليم
</div>
            <div class="stat-value">{{ $summary['ok'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">تنبيهات</div>
            <div class="stat-value">{{ $summary['warning'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">أخطاء</div>
            <div class="stat-value">{{ $summary['error'] }}</div>
        </div>
    </div>

    @if($summary['error'] > 0)
        <div class="alert-error" style="margin-bottom: 18px;">
            توجد أخطاء تحتاج م
عالجة قبل الاعتم
اد على النظام
 أو تنفيذ استعادة جديدة.
        </div>
    @elseif($summary['warning'] > 0)
        <div class="alert-warning" style="margin-bottom: 18px;">
            النظام
 يعم
ل، لكن توجد تنبيهات يفضل م
راجعتها.
        </div>
    @else
        <div class="alert-success" style="margin-bottom: 18px;">
            كل الفحوصات الأساسية سليم
ة.
        </div>
    @endif

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th style="width: 160px;">الحالة</th>
                    <th style="width: 230px;">الفحص</th>
                    <th>التفاصيل</th>
                </tr>
            </thead>
            <tbody>
                @foreach($checks as $check)
                    <tr>
                        <td>
                            @if($check['status'] === 'ok')
                                <span class="badge" style="background: rgba(34,197,94,.14); color: #22c55e; border: 1px solid rgba(34,197,94,.35);">سليم
</span>
                            @elseif($check['status'] === 'warning')
                                <span class="badge" style="background: rgba(245,158,11,.14); color: #f59e0b; border: 1px solid rgba(245,158,11,.35);">تنبيه</span>
                            @else
                                <span class="badge" style="background: rgba(239,68,68,.14); color: #ef4444; border: 1px solid rgba(239,68,68,.35);">خطأ</span>
                            @endif
                        </td>
                        <td><strong>{{ $check['title'] }}</strong></td>
                        <td>{{ $check['message'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

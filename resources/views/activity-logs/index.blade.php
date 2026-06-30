@extends('layouts.app')

@section('title', 'سجل النشاط')
@section('page_title', 'سجل النشاط')
@section('page_subtitle', 'متابعة العمليات التي تمت على الكتب، المرفقات، المستخدمين، النسخ الاحتياطي والطباعة')

@section('content')
{{-- DA_ACTIVITY_PAGINATION_FIX_START --}}
@once
<style>
    /* إصلاح محدود لأسهم وروابط الترقيم داخل صفحات سجل النشاط فقط */
    nav[role="navigation"] {
        direction: rtl !important;
        font-size: 13px !important;
        line-height: 1.45 !important;
        color: #cbd5e1 !important;
        margin-top: 14px !important;
    }

    nav[role="navigation"] svg {
        width: 16px !important;
        height: 16px !important;
        min-width: 16px !important;
        min-height: 16px !important;
        max-width: 16px !important;
        max-height: 16px !important;
        display: inline-block !important;
        vertical-align: middle !important;
        overflow: hidden !important;
    }

    nav[role="navigation"] a,
    nav[role="navigation"] span {
        font-size: 13px !important;
        line-height: 1.45 !important;
        align-items: center !important;
        justify-content: center !important;
    }

    nav[role="navigation"] p {
        color: #94a3b8 !important;
        font-size: 13px !important;
        margin: 8px 0 !important;
    }

    nav[role="navigation"] .hidden,
    nav[role="navigation"] .sm\:flex-1,
    nav[role="navigation"] .sm\:items-center,
    nav[role="navigation"] .sm\:justify-between {
        font-size: 13px !important;
    }
</style>
@endonce
{{-- DA_ACTIVITY_PAGINATION_FIX_END --}}

<div class="activity-log-page">
    <style>
        .activity-log-page {
            --audit-card: rgba(15, 23, 42, 0.78);
            --audit-card-border: rgba(148, 163, 184, 0.24);
            --audit-muted: #9ca3af;
            --audit-text: #e5e7eb;
        }

        html[data-theme="light"] .activity-log-page {
            --audit-card: #ffffff;
            --audit-card-border: #dbe3ef;
            --audit-muted: #64748b;
            --audit-text: #0f172a;
        }

        .activity-log-page .audit-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .activity-log-page .audit-stat {
            border: 1px solid var(--audit-card-border);
            border-radius: 16px;
            padding: 14px 16px;
            background: var(--audit-card);
        }

        .activity-log-page .audit-stat-label {
            color: var(--audit-muted);
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .activity-log-page .audit-stat-value {
            color: var(--audit-text);
            font-size: 24px;
            font-weight: 900;
            line-height: 1;
        }

        .activity-log-page .audit-table-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            direction: rtl;
            border-radius: 14px;
            border: 1px solid var(--audit-card-border);
        }

        .activity-log-page table.audit-table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
            margin: 0;
        }

        .activity-log-page .audit-table th,
        .activity-log-page .audit-table td {
            vertical-align: top;
            white-space: normal !important;
            overflow: visible !important;
            line-height: 1.75;
            font-size: 13px;
        }

        .activity-log-page .audit-time {
            direction: ltr;
            unicode-bidi: plaintext;
            font-weight: 800;
            white-space: nowrap !important;
        }

        .activity-log-page .audit-muted {
            color: var(--audit-muted);
            font-size: 12px;
        }

        .activity-log-page .audit-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 900;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .activity-log-page .audit-badge-neutral {
            background: rgba(100, 116, 139, 0.18);
            color: #cbd5e1;
            border-color: rgba(148, 163, 184, 0.24);
        }

        html[data-theme="light"] .activity-log-page .audit-badge-neutral {
            color: #334155;
        }

        .activity-log-page .audit-badge-success {
            background: rgba(34, 197, 94, 0.14);
            color: #4ade80;
            border-color: rgba(34, 197, 94, 0.30);
        }

        .activity-log-page .audit-badge-info {
            background: rgba(59, 130, 246, 0.15);
            color: #93c5fd;
            border-color: rgba(59, 130, 246, 0.30);
        }

        .activity-log-page .audit-badge-warning {
            background: rgba(245, 158, 11, 0.16);
            color: #fbbf24;
            border-color: rgba(245, 158, 11, 0.34);
        }

        .activity-log-page .audit-badge-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.32);
        }

        .activity-log-page .audit-description {
            max-width: 420px;
        }

        .activity-log-page details.audit-details {
            margin-top: 7px;
        }

        .activity-log-page details.audit-details summary {
            cursor: pointer;
            color: #93c5fd;
            font-weight: 800;
            font-size: 12px;
        }

        .activity-log-page .audit-properties {
            margin-top: 8px;
            display: grid;
            gap: 4px;
            font-size: 12px;
        }

        .activity-log-page .audit-property-row {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 8px;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.20);
            padding-bottom: 4px;
        }

        .activity-log-page .audit-property-key {
            color: var(--audit-muted);
            font-weight: 900;
        }

        .activity-log-page .audit-filter-actions {
            display: flex;
            gap: 8px;
            align-items: end;
            flex-wrap: wrap;
        }

        @media (max-width: 900px) {
            .activity-log-page .audit-summary-grid {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }
        }

        @media (max-width: 560px) {
            .activity-log-page .audit-summary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            .activity-log-page .card:first-of-type,
            .activity-log-page .pagination {
                display: none !important;
            }

            .activity-log-page .audit-table-wrap {
                overflow: visible !important;
                border: 0;
            }

            .activity-log-page table.audit-table {
                min-width: 0 !important;
                font-size: 10px;
            }
        }
    </style>

    <div class="page-title">
        <h1>سجل النشاط</h1>
        <p>مراجعة تفصيلية لكل العمليات المهمة داخل النظام.</p>
    </div>

    <div class="audit-summary-grid">
        <div class="audit-stat">
            <div class="audit-stat-label">نتائج الفلتر الحالية</div>
            <div class="audit-stat-value">{{ number_format($summary['total_filtered'] ?? $logs->total()) }}</div>
        </div>
        <div class="audit-stat">
            <div class="audit-stat-label">عمليات اليوم</div>
            <div class="audit-stat-value">{{ number_format($summary['today_total'] ?? 0) }}</div>
        </div>
        <div class="audit-stat">
            <div class="audit-stat-label">مستخدمون لديهم نشاط</div>
            <div class="audit-stat-value">{{ number_format($summary['users_total'] ?? 0) }}</div>
        </div>
        <div class="audit-stat">
            <div class="audit-stat-label">عمليات حساسة</div>
            <div class="audit-stat-value">{{ number_format($summary['critical_total'] ?? 0) }}</div>
        </div>
    </div>

    <div class="card">
        @if(auth()->user()?->hasPermission('activity_logs.view'))
        <form method="GET" action="{{ route('activity-logs.index') }}">
            <div class="form-grid">
                <div class="form-group">
                    <label>بحث</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="الوصف / العملية / رقم السجل / IP">
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
                    <label>نوع العنصر</label>
                    <select name="model_type">
                        <option value="">كل العناصر</option>
                        @foreach($modelTypes as $modelType)
                            <option value="{{ $modelType }}" @selected(request('model_type') === $modelType)>
                                {{ \App\Models\ActivityLog::modelLabel($modelType) }}
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

                <div class="form-group">
                    <label>&nbsp;</label>
                    <div class="audit-filter-actions">
                        <button type="submit" class="btn btn-primary">بحث</button>
                        <a href="{{ route('activity-logs.index') }}" class="btn btn-secondary">إلغاء الفلترة</a>
                    </div>
                </div>
            </div>
        </form>
        @endif
    </div>

    <div class="card">
        <div class="audit-table-wrap">
            <table class="audit-table">
                <thead>
                <tr>
                    <th>التاريخ والوقت</th>
                    <th>المستخدم</th>
                    <th>العملية</th>
                    <th>الوصف والتفاصيل</th>
                    <th>العنصر</th>
                    <th>IP</th>
                </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    @php($propertyRows = $log->propertiesRows())
                    <tr>
                        <td>
                            <div class="audit-time">{{ $log->created_at?->format('Y-m-d H:i:s') }}</div>
                            <div class="audit-muted">{{ $log->created_at?->diffForHumans() }}</div>
                        </td>
                        <td>
                            <strong>{{ $log->actor_label }}</strong>
                            @if($log->user?->username)
                                <div class="audit-muted">{{ $log->user->username }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="audit-badge audit-badge-{{ $log->action_tone }}">{{ $log->action_label }}</span>
                            <div class="audit-muted">{{ $log->action_group }}</div>
                        </td>
                        <td class="audit-description">
                            {{ $log->description ?? '-' }}

                            @if(!empty($propertyRows))
                                <details class="audit-details">
                                    <summary>عرض التفاصيل</summary>
                                    <div class="audit-properties">
                                        @foreach($propertyRows as $row)
                                            <div class="audit-property-row">
                                                <span class="audit-property-key">{{ $row['key'] }}</span>
                                                <span>{{ $row['value'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </td>
                        <td>
                            {{ $log->model_label }}
                        </td>
                        <td>
                            <div class="audit-time">{{ $log->ip_label }}</div>
                            <details class="audit-details">
                                <summary>الجهاز</summary>
                                <div class="audit-muted">{{ $log->shortUserAgent() }}</div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">لا توجد عمليات مسجلة حتى الآن.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection

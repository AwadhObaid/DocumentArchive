@extends('layouts.app')

@section('title', 'المذكرات')
@section('page_title', 'المذكرات')
@section('page_subtitle', 'أرشفة المذكرات الواردة بترقيم مستقل يبدأ من 2600000')

@section('content')
<style>
    /* MEMOS_TABLE_SCROLL_FIX_V5_START */
    html,
    body {
        overflow-x: hidden !important;
    }

    .memos-page,
    .memos-page *,
    .memos-page *::before,
    .memos-page *::after {
        box-sizing: border-box !important;
    }

    .memos-page {
        display: grid;
        gap: 18px;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: hidden !important;
    }

    .memos-page > *,
    .memos-page .card,
    .memos-header,
    .memos-stats,
    .memos-filter-grid {
        min-width: 0 !important;
        max-width: 100% !important;
    }

    .memos-page > .card {
        overflow: hidden !important;
    }
    /* MEMOS_TABLE_SCROLL_FIX_V5_END */

    .memos-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .memos-header h1 { margin:0; }
    .memos-header p { margin:6px 0 0; color:var(--muted, #94a3b8); }
    .memos-stats { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; }
    .memo-stat { padding:16px; border:1px solid rgba(148,163,184,.25); border-radius:16px; background:rgba(255,255,255,.72); }
    html[data-theme="dark"] .memo-stat { background:rgba(15,23,42,.55); }
    .memo-stat span { display:block; color:var(--muted, #94a3b8); font-size:13px; margin-bottom:8px; }
    .memo-stat strong { font-size:24px; }
    .memos-filter-grid { display:grid; grid-template-columns:2fr repeat(4, minmax(145px, 1fr)) auto; gap:10px; align-items:end; }
    .memos-filter-grid .form-group { margin:0; }
    .memos-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .memos-header-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }

    /* MEMOS_TABLE_INNER_SCROLL_V5_START */
    #memosTableScroll {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: scroll !important;
        overflow-y: hidden !important;
        direction: ltr !important;
        box-sizing: border-box !important;
        border-radius: 14px !important;
        padding: 0 0 14px 0 !important;
        margin: 0 !important;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-inline: contain;
        scrollbar-width: auto;
        scrollbar-color: rgba(59,130,246,.85) rgba(148,163,184,.18);
    }

    #memosTableScroll::-webkit-scrollbar { height: 12px; }
    #memosTableScroll::-webkit-scrollbar-track { background: rgba(148,163,184,.18); border-radius: 999px; }
    #memosTableScroll::-webkit-scrollbar-thumb { background: rgba(59,130,246,.85); border-radius: 999px; }

    #memosTableScroll .memos-table {
        display: table !important;
        direction: rtl !important;
        width: max-content !important;
        min-width: 1520px !important;
        max-width: none !important;
        table-layout: auto !important;
        border-collapse: collapse !important;
        margin: 0 !important;
    }

    #memosTableScroll .memos-table th,
    #memosTableScroll .memos-table td {
        overflow: hidden !important;
        text-overflow: clip !important;
        white-space: nowrap !important;
        vertical-align: middle !important;
        box-sizing: border-box !important;
    }

    #memosTableScroll .memos-table th:nth-child(1),
    #memosTableScroll .memos-table td:nth-child(1) { min-width: 135px !important; }
    #memosTableScroll .memos-table th:nth-child(2),
    #memosTableScroll .memos-table td:nth-child(2) { min-width: 115px !important; }
    #memosTableScroll .memos-table th:nth-child(3),
    #memosTableScroll .memos-table td:nth-child(3) { min-width: 290px !important; max-width: 380px !important; white-space: normal !important; overflow: visible !important; line-height: 1.65 !important; }
    #memosTableScroll .memos-table th:nth-child(4),
    #memosTableScroll .memos-table td:nth-child(4) { min-width: 150px !important; }
    #memosTableScroll .memos-table th:nth-child(5),
    #memosTableScroll .memos-table td:nth-child(5) { min-width: 170px !important; }
    #memosTableScroll .memos-table th:nth-child(6),
    #memosTableScroll .memos-table td:nth-child(6) { min-width: 105px !important; text-align:center !important; }
    #memosTableScroll .memos-table th:nth-child(7),
    #memosTableScroll .memos-table td:nth-child(7) { min-width: 115px !important; }
    #memosTableScroll .memos-table th:nth-child(8),
    #memosTableScroll .memos-table td:nth-child(8) { min-width: 760px !important; }

    #memosTableScroll .memos-actions {
        display: inline-flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-start !important;
        flex-wrap: nowrap !important;
        gap: 8px !important;
        width: max-content !important;
        max-width: none !important;
        white-space: nowrap !important;
    }

    #memosTableScroll .memos-actions form,
    #memosTableScroll .memos-actions .btn,
    #memosTableScroll .memos-actions button,
    #memosTableScroll .memos-actions a {
        flex: 0 0 auto !important;
        white-space: nowrap !important;
        margin: 0 !important;
    }
    /* MEMOS_TABLE_INNER_SCROLL_V5_END */

    .memo-number { direction:ltr; unicode-bidi:embed; font-weight:950; font-size:17px; }
    .memo-status { display:inline-flex; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:850; }
    .memo-status.active { background:rgba(34,197,94,.13); color:#16a34a; }
    .memo-status.archived { background:rgba(59,130,246,.13); color:#3b82f6; }
    .memo-status.cancelled { background:rgba(239,68,68,.13); color:#ef4444; }
    /* MEMOS_ACTION_BUTTON_COLORS_V8_START */
    #memosTableScroll .memo-action-btn {
        min-width: 86px !important;
        min-height: 36px !important;
        padding: 8px 12px !important;
        border-radius: 11px !important;
        border: 1px solid transparent !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        font-size: 13px !important;
        font-weight: 900 !important;
        line-height: 1.2 !important;
        text-decoration: none !important;
        box-shadow: 0 8px 18px rgba(15, 23, 42, .12) !important;
        transition: transform .15s ease, filter .15s ease, box-shadow .15s ease, border-color .15s ease !important;
    }

    #memosTableScroll .memo-action-btn:hover,
    #memosTableScroll .memo-action-btn:focus {
        transform: translateY(-1px) !important;
        filter: brightness(1.05) !important;
        box-shadow: 0 12px 22px rgba(15, 23, 42, .18) !important;
    }

    #memosTableScroll .btn-memo-view {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0) !important;
        color: #0f172a !important;
        border-color: rgba(148, 163, 184, .65) !important;
    }

    #memosTableScroll .btn-memo-email {
        background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
        color: #ffffff !important;
        border-color: rgba(96, 165, 250, .55) !important;
    }

    #memosTableScroll .btn-memo-whatsapp {
        background: linear-gradient(135deg, #16a34a, #15803d) !important;
        color: #ffffff !important;
        border-color: rgba(74, 222, 128, .50) !important;
    }

    #memosTableScroll .btn-memo-share {
        background: linear-gradient(135deg, #f59e0b, #d97706) !important;
        color: #111827 !important;
        border-color: rgba(251, 191, 36, .60) !important;
    }

    #memosTableScroll .btn-memo-edit {
        background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
        color: #ffffff !important;
        border-color: rgba(147, 197, 253, .55) !important;
    }

    #memosTableScroll .btn-memo-delete {
        background: linear-gradient(135deg, #ef4444, #dc2626) !important;
        color: #ffffff !important;
        border-color: rgba(252, 165, 165, .55) !important;
    }

    html[data-theme="dark"] #memosTableScroll .btn-memo-view,
    body.dark #memosTableScroll .btn-memo-view {
        background: linear-gradient(135deg, #f8fafc, #cbd5e1) !important;
        color: #0f172a !important;
        border-color: rgba(226, 232, 240, .70) !important;
    }

    html[data-theme="dark"] #memosTableScroll .btn-memo-share,
    body.dark #memosTableScroll .btn-memo-share {
        color: #111827 !important;
    }
    /* MEMOS_ACTION_BUTTON_COLORS_V8_END */
    @media (max-width: 1100px) { .memos-filter-grid { grid-template-columns:1fr 1fr; } }
    @media (max-width: 760px) { .memos-stats { grid-template-columns:repeat(2, minmax(0, 1fr)); } .memos-filter-grid { grid-template-columns:1fr; } #memosTableScroll .memos-table { min-width: 1450px !important; } }
    @media (max-width: 560px) { .memos-stats { grid-template-columns:1fr; } }
</style>

<div class="memos-page">
    <div class="memos-header">
        <div>
            <h1>المذكرات</h1>
            <p>وحدة مستقلة لحفظ وأرشفة المذكرات الواردة برقم مرجع خاص.</p>
        </div>
        <div class="memos-header-actions">
            @if(auth()->user()?->hasPermission('legacy_import.manage') || auth()->user()?->hasPermission('settings.manage'))
                <a href="{{ route('memo-legacy-import.index') }}" class="btn btn-secondary">
                    استيراد المذكرات القديمة
                </a>
            @endif

            @if(auth()->user()?->hasPermission('memos.create'))
                <a href="{{ route('memos.create') }}" class="btn btn-primary">+ إضافة مذكرة</a>
            @endif
        </div>
    </div>

    <div class="memos-stats">
        <div class="memo-stat"><span>إجمالي المذكرات</span><strong>{{ number_format($stats['total'] ?? 0) }}</strong></div>
        <div class="memo-stat"><span>النشطة</span><strong>{{ number_format($stats['active'] ?? 0) }}</strong></div>
        <div class="memo-stat"><span>المؤرشفة</span><strong>{{ number_format($stats['archived'] ?? 0) }}</strong></div>
        <div class="memo-stat"><span>بها مرفقات</span><strong>{{ number_format($stats['with_attachments'] ?? 0) }}</strong></div>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('memos.index') }}" class="memos-filter-grid">
            <div class="form-group">
                <label>بحث</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="رقم المذكرة، الموضوع، الوارد من، أو الإدارة">
            </div>
            <div class="form-group">
                <label>الإدارة</label>
                <select name="department_id">
                    <option value="">كل الإدارات</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((string)($filters['department_id'] ?? '') === (string)$department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>الحالة</label>
                <select name="status">
                    <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>كل الحالات</option>
                    <option value="active" @selected(($filters['status'] ?? 'all') === 'active')>نشطة</option>
                    <option value="archived" @selected(($filters['status'] ?? 'all') === 'archived')>مؤرشفة</option>
                    <option value="cancelled" @selected(($filters['status'] ?? 'all') === 'cancelled')>ملغاة</option>
                </select>
            </div>
            <div class="form-group">
                <label>من تاريخ</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="form-group">
                <label>إلى تاريخ</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="memos-actions">
                <button type="submit" class="btn btn-primary">بحث</button>
                <a href="{{ route('memos.index') }}" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="memos-table-scroll" id="memosTableScroll">
            <table class="memos-table">
                <thead>
                    <tr>
                        <th>رقم المذكرة</th>
                        <th>التاريخ</th>
                        <th>الموضوع</th>
                        <th>الإدارة</th>
                        <th>الواردة من</th>
                        <th>المرفقات</th>
                        <th>الفهرسة</th>
                        <th>الحالة</th>
                        <th>الاعتماد</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($memos as $memo)
                        <tr>
                            <td><span class="memo-number">{{ $memo->memo_number }}</span></td>
                            <td>{{ $memo->formatted_date }}</td>
                            <td><strong>{{ \Illuminate\Support\Str::limit($memo->subject, 90) }}</strong></td>
                            <td>{{ $memo->department?->name ?: '-' }}</td>
                            <td>{{ $memo->sender ?: '-' }}</td>
                            <td>{{ number_format($memo->attachments_count ?? 0) }}</td>
                            <td>@include('partials.attachment-index-status', ['record' => $memo, 'sourceType' => 'memo'])</td>
                            <td><span class="memo-status {{ $memo->status }}">{{ $memo->status_name }}</span></td>
                            <td><span class="workflow-status-badge workflow-status-{{ $memo->workflow_status ?: 'draft' }}">{{ $memo->workflow_status_name ?? \App\Models\WorkflowAction::statusName($memo->workflow_status ?? 'draft') }}</span></td>
                            <td>
                                <div class="memos-actions">
                                    <a href="{{ route('memos.show', $memo) }}" class="btn memo-action-btn btn-memo-view">عرض</a>
                                    @if(auth()->user()?->hasPermission('emails.send'))
                                        <a href="{{ route('memos.email.compose', $memo) }}" class="btn memo-action-btn btn-memo-email">إرسال البريد</a>
                                    @endif
                                    @if(auth()->user()?->hasPermission('whatsapp.send'))
                                        <a href="{{ route('memos.whatsapp.compose', $memo) }}" class="btn memo-action-btn btn-memo-whatsapp">إرسال واتساب</a>
                                    @endif
                                    @if(auth()->user()?->hasPermission('internal_messages.send'))
                                        <a href="{{ route('memos.internal-message.create', $memo) }}" class="btn memo-action-btn btn-memo-email">إرسال داخلي</a>
                                    @endif
                                    @if(auth()->user()?->hasPermission('attachment_shares.create') && ($memo->attachments_count ?? 0) > 0)
                                        <a href="{{ route('memos.shared-attachments.create', $memo) }}" class="btn memo-action-btn btn-memo-share">رابط المرفقات</a>
                                    @endif
                                    @if(auth()->user()?->hasPermission('memos.edit') && (! method_exists($memo, 'canBeModifiedBy') || $memo->canBeModifiedBy(auth()->user())))
                                        <a href="{{ route('memos.edit', $memo) }}" class="btn memo-action-btn btn-memo-edit">تعديل</a>
                                    @endif
                                    @if(auth()->user()?->hasPermission('memos.delete') && (! method_exists($memo, 'canBeModifiedBy') || $memo->canBeModifiedBy(auth()->user())))
                                        <form method="POST" action="{{ route('memos.destroy', $memo) }}" data-confirm="هل تريد حذف هذه المذكرة؟">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn memo-action-btn btn-memo-delete">حذف</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10">لا توجد مذكرات مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $memos->links() }}</div>
    </div>
</div>

<script>
(function () {
    function alignMemosTableScroll() {
        var wrap = document.getElementById('memosTableScroll');
        if (!wrap) return;
        // الغلاف LTR والجدول RTL؛ لذلك نضع المؤشر في أقصى اليمين لعرض أول أعمدة عربية أولًا.
        wrap.scrollLeft = wrap.scrollWidth;
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', alignMemosTableScroll);
    } else {
        alignMemosTableScroll();
    }
    window.addEventListener('resize', alignMemosTableScroll);
})();
</script>

@endsection

@extends('layouts.app')

@section('title', 'الإدارات')
@section('page_title', 'الإدارات')
@section('page_subtitle', 'إدارة الإدارات المستخدمة في تصنيف الكتب وربطها بالفلاتر والتقارير')

@section('content')
    {{-- DEFINITIONS_POLISH_VIEW_START --}}
    <style>
        .definitions-page {
            display: grid;
            gap: 18px;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow-x: hidden !important;
        }
        .definitions-page .card {
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
        }
        .definitions-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .definitions-header h1 { margin:0; }
        .definitions-header p { margin:6px 0 0; color:var(--muted, #6b7280); }
        .definition-stats { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; }
        .definition-stat { padding:16px; border:1px solid rgba(148,163,184,.25); border-radius:16px; background:rgba(255,255,255,.72); }
        html[data-theme="dark"] .definition-stat { background:rgba(15,23,42,.55); }
        .definition-stat span { display:block; color:var(--muted, #6b7280); font-size:13px; margin-bottom:8px; }
        .definition-stat strong { font-size:24px; }
        .definition-filter-grid { display:grid; grid-template-columns:2fr repeat(3, minmax(150px, 1fr)) auto; gap:10px; align-items:end; }
        .definition-filter-grid .form-group { margin:0; }
        .definition-filter-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }

        /* Departments table scroll fix V11
           The Arabic global fixes use !important on table cells, so this page must
           override them with higher specificity and !important as well. */
        .definitions-table-card {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow: hidden !important;
            box-sizing: border-box;
        }
        .departments-scroll-shell {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            border-radius: 14px;
            padding-bottom: 14px;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-x: contain;
            scrollbar-gutter: stable;
            scrollbar-width: auto;
            scrollbar-color: rgba(59,130,246,.95) rgba(148,163,184,.24);
        }
        .departments-scroll-shell::-webkit-scrollbar { height: 15px; }
        .departments-scroll-shell::-webkit-scrollbar-track {
            background: rgba(148,163,184,.24);
            border-radius: 999px;
        }
        .departments-scroll-shell::-webkit-scrollbar-thumb {
            background: rgba(59,130,246,.95);
            border-radius: 999px;
            border: 3px solid rgba(15,23,42,.30);
        }
        .departments-scroll-shell::-webkit-scrollbar-thumb:hover { background: rgba(37,99,235,1); }
        .departments-scroll-inner {
            width: 1360px !important;
            min-width: 1360px !important;
            max-width: none !important;
        }
        html[dir="rtl"] .departments-scroll-shell table.departments-scroll-table,
        .departments-scroll-shell table.departments-scroll-table {
            width: 1360px !important;
            min-width: 1360px !important;
            max-width: none !important;
            table-layout: fixed !important;
            border-collapse: collapse !important;
            display: table !important;
        }
        html[dir="rtl"] .departments-scroll-shell table.departments-scroll-table th,
        html[dir="rtl"] .departments-scroll-shell table.departments-scroll-table td,
        .departments-scroll-shell table.departments-scroll-table th,
        .departments-scroll-shell table.departments-scroll-table td {
            white-space: nowrap !important;
            overflow-wrap: normal !important;
            word-break: keep-all !important;
            text-overflow: clip !important;
            vertical-align: middle !important;
        }
        html[dir="rtl"] .departments-scroll-shell table.departments-scroll-table td.department-description-cell,
        .departments-scroll-shell table.departments-scroll-table td.department-description-cell {
            white-space: normal !important;
            overflow-wrap: break-word !important;
            word-break: normal !important;
            line-height: 1.65;
        }
        .departments-scroll-table col.col-name { width: 220px; }
        .departments-scroll-table col.col-code { width: 190px; }
        .departments-scroll-table col.col-desc { width: 360px; }
        .departments-scroll-table col.col-status { width: 150px; }
        .departments-scroll-table col.col-active { width: 130px; }
        .departments-scroll-table col.col-all { width: 120px; }
        .departments-scroll-table col.col-actions { width: 290px; }

        .definition-code { direction:ltr; unicode-bidi:embed; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12px; }
        .definition-status { display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .definition-status.active { background:rgba(34,197,94,.12); color:#15803d; }
        .definition-status.inactive { background:rgba(100,116,139,.14); color:#475569; }
        .definition-actions { display:flex; flex-wrap:nowrap; gap:8px; align-items:center; min-width: 260px; }
        .definition-actions form { margin:0; }
        .definition-actions .btn { flex:0 0 auto; }
        .definitions-page .pagination svg { width:18px !important; height:18px !important; max-width:18px !important; max-height:18px !important; }
        .definitions-page .pagination { margin-top:14px; overflow:auto; }
        @media (max-width: 900px) { .definition-stats { grid-template-columns:repeat(2, minmax(0, 1fr)); } .definition-filter-grid { grid-template-columns:1fr; } }
        @media (max-width: 560px) { .definition-stats { grid-template-columns:1fr; } .definitions-header { display:grid; } }


        /* departments-table-scroll-fix-v12:start
           Final guard: keep horizontal scrolling inside the departments table card only.
           Uses a deliberately wider inner canvas plus a JS guard below because several
           global Arabic no-truncate rules override table sizing on this project. */
        body {
            overflow-x: hidden !important;
        }
        .definitions-page,
        .definitions-table-card {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            overflow-x: hidden !important;
        }
        .departments-scroll-shell[data-departments-scroll-v12] {
            display: block !important;
            position: relative !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            overflow-x: scroll !important;
            overflow-y: hidden !important;
            padding-bottom: 16px !important;
            margin-bottom: 4px !important;
            border-radius: 14px !important;
            direction: rtl !important;
            -webkit-overflow-scrolling: touch !important;
            overscroll-behavior-x: contain !important;
            scrollbar-gutter: stable both-edges !important;
            scrollbar-width: auto !important;
            scrollbar-color: rgba(59,130,246,.95) rgba(148,163,184,.24) !important;
        }
        .departments-scroll-shell[data-departments-scroll-v12]::-webkit-scrollbar {
            height: 16px !important;
            display: block !important;
        }
        .departments-scroll-shell[data-departments-scroll-v12]::-webkit-scrollbar-track {
            background: rgba(148,163,184,.24) !important;
            border-radius: 999px !important;
        }
        .departments-scroll-shell[data-departments-scroll-v12]::-webkit-scrollbar-thumb {
            background: rgba(59,130,246,.95) !important;
            border-radius: 999px !important;
            border: 3px solid rgba(15,23,42,.30) !important;
        }
        .departments-scroll-wide-v12 {
            display: block !important;
            width: 1480px !important;
            min-width: 1480px !important;
            max-width: none !important;
        }
        .departments-scroll-shell[data-departments-scroll-v12] table.departments-scroll-table {
            display: table !important;
            width: 1480px !important;
            min-width: 1480px !important;
            max-width: none !important;
            table-layout: fixed !important;
            border-collapse: collapse !important;
        }
        .departments-scroll-shell[data-departments-scroll-v12] table.departments-scroll-table th,
        .departments-scroll-shell[data-departments-scroll-v12] table.departments-scroll-table td {
            white-space: nowrap !important;
            overflow-wrap: normal !important;
            word-break: keep-all !important;
            text-overflow: clip !important;
            vertical-align: middle !important;
        }
        .departments-scroll-shell[data-departments-scroll-v12] table.departments-scroll-table td.department-description-cell {
            white-space: normal !important;
            overflow-wrap: break-word !important;
            word-break: normal !important;
        }
        .departments-scroll-shell[data-departments-scroll-v12] .definition-actions {
            display: flex !important;
            flex-wrap: nowrap !important;
            gap: 8px !important;
            align-items: center !important;
            min-width: 260px !important;
            white-space: nowrap !important;
        }
        /* departments-table-scroll-fix-v12:end */

    </style>

    <div class="definitions-page">
        <div class="definitions-header">
            <div>
                <h1>الإدارات</h1>
                <p>تحكم في الإدارات التي تظهر داخل إضافة وتعديل الكتب.</p>
            </div>

            @if(auth()->user()?->hasPermission('departments.manage'))
                <a href="{{ route('departments.create') }}" class="btn btn-primary">+ إضافة إدارة</a>
            @endif
        </div>

        <div class="definition-stats">
            <div class="definition-stat"><span>إجمالي الإدارات</span><strong>{{ number_format($stats['total'] ?? 0) }}</strong></div>
            <div class="definition-stat"><span>الإدارات النشطة</span><strong>{{ number_format($stats['active'] ?? 0) }}</strong></div>
            <div class="definition-stat"><span>الإدارات المعطلة</span><strong>{{ number_format($stats['inactive'] ?? 0) }}</strong></div>
            <div class="definition-stat"><span>مرتبطة بكتب</span><strong>{{ number_format($stats['linked'] ?? 0) }}</strong></div>
        </div>

        <div class="card">
            <form method="GET" action="{{ route('departments.index') }}" class="definition-filter-grid">
                <div class="form-group">
                    <label>بحث</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="اسم الإدارة، الكود، أو الوصف">
                </div>

                <div class="form-group">
                    <label>الحالة</label>
                    <select name="status">
                        <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>كل الحالات</option>
                        <option value="active" @selected(($filters['status'] ?? 'all') === 'active')>النشطة فقط</option>
                        <option value="inactive" @selected(($filters['status'] ?? 'all') === 'inactive')>المعطلة فقط</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>الارتباط بالكتب</label>
                    <select name="linked">
                        <option value="all" @selected(($filters['linked'] ?? 'all') === 'all')>الكل</option>
                        <option value="linked" @selected(($filters['linked'] ?? 'all') === 'linked')>مرتبطة بكتب</option>
                        <option value="empty" @selected(($filters['linked'] ?? 'all') === 'empty')>غير مرتبطة</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>الترتيب</label>
                    <select name="sort">
                        <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>الأحدث أولاً</option>
                        <option value="oldest" @selected(($filters['sort'] ?? 'latest') === 'oldest')>الأقدم أولاً</option>
                        <option value="name" @selected(($filters['sort'] ?? 'latest') === 'name')>حسب الاسم</option>
                        <option value="documents" @selected(($filters['sort'] ?? 'latest') === 'documents')>الأكثر استخداماً</option>
                    </select>
                </div>

                <div class="definition-filter-actions">
                    <button type="submit" class="btn btn-primary">تطبيق</button>
                    <a href="{{ route('departments.index') }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>

        <div class="card definitions-table-card">
            <div class="departments-scroll-shell" data-departments-scroll-v12 tabindex="0" aria-label="جدول الإدارات قابل للتمرير أفقيًا">
                <div class="departments-scroll-inner departments-scroll-wide-v12">
                <table class="departments-scroll-table">
                    <colgroup>
                        <col class="col-name">
                        <col class="col-code">
                        <col class="col-desc">
                        <col class="col-status">
                        <col class="col-active">
                        <col class="col-all">
                        <col class="col-actions">
                    </colgroup>
                    <thead>
                    <tr>
                        <th>اسم الإدارة</th>
                        <th>الكود</th>
                        <th>الوصف</th>
                        <th>الحالة</th>
                        <th>الكتب النشطة</th>
                        <th>كل الكتب</th>
                        <th>إجراءات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($departments as $department)
                        <tr>
                            <td><strong>{{ $department->name }}</strong></td>
                            <td><span class="definition-code">{{ $department->code ?: '-' }}</span></td>
                            <td class="department-description-cell">{{ $department->description ?: '-' }}</td>
                            <td>
                                @if($department->is_active)
                                    <span class="definition-status active">● نشطة</span>
                                @else
                                    <span class="definition-status inactive">● معطلة</span>
                                @endif
                            </td>
                            <td>{{ number_format($department->documents_count ?? 0) }}</td>
                            <td>{{ number_format($department->all_documents_count ?? $department->documents_count ?? 0) }}</td>
                            <td>
                                <div class="definition-actions">
                                    <a href="{{ route('documents.index', ['department_id' => $department->id]) }}" class="btn btn-secondary">عرض الكتب</a>

                                    @if(auth()->user()?->hasPermission('departments.manage'))
                                        <a href="{{ route('departments.edit', $department) }}" class="btn btn-primary">تعديل</a>
                                        <form method="POST" action="{{ route('departments.destroy', $department) }}" data-confirm="هل أنت متأكد من حذف أو تعطيل هذه الإدارة؟">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">{{ ($department->all_documents_count ?? 0) > 0 ? 'تعطيل' : 'حذف' }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">لا توجد إدارات مطابقة للفلاتر الحالية.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div class="pagination">{{ $departments->links() }}</div>
        </div>
    </div>
    {{-- DEFINITIONS_POLISH_VIEW_END --}}


    {{-- departments-table-scroll-fix-v12:js:start --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var shell = document.querySelector('.departments-scroll-shell[data-departments-scroll-v12]');
            if (!shell) return;

            var wide = shell.querySelector('.departments-scroll-wide-v12');
            var table = shell.querySelector('table.departments-scroll-table');

            function forceDepartmentsTableScroll() {
                var clientWidth = Math.max(shell.clientWidth || 0, 320);
                var targetWidth = Math.max(1480, clientWidth + 520);

                shell.style.setProperty('display', 'block', 'important');
                shell.style.setProperty('width', '100%', 'important');
                shell.style.setProperty('max-width', '100%', 'important');
                shell.style.setProperty('overflow-x', 'scroll', 'important');
                shell.style.setProperty('overflow-y', 'hidden', 'important');
                shell.style.setProperty('padding-bottom', '16px', 'important');

                if (wide) {
                    wide.style.setProperty('display', 'block', 'important');
                    wide.style.setProperty('width', targetWidth + 'px', 'important');
                    wide.style.setProperty('min-width', targetWidth + 'px', 'important');
                    wide.style.setProperty('max-width', 'none', 'important');
                }

                if (table) {
                    table.style.setProperty('display', 'table', 'important');
                    table.style.setProperty('width', targetWidth + 'px', 'important');
                    table.style.setProperty('min-width', targetWidth + 'px', 'important');
                    table.style.setProperty('max-width', 'none', 'important');
                    table.style.setProperty('table-layout', 'fixed', 'important');
                }
            }

            forceDepartmentsTableScroll();
            window.setTimeout(forceDepartmentsTableScroll, 80);
            window.setTimeout(forceDepartmentsTableScroll, 350);
            window.addEventListener('resize', forceDepartmentsTableScroll);
        });
    </script>
    {{-- departments-table-scroll-fix-v12:js:end --}}

@endsection
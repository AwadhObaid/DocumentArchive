@extends('layouts.app')

@section('title', 'الإدارات')
@section('page_title', 'الإدارات')
@section('page_subtitle', 'إدارة الإدارات المستخدمة في تصنيف الكتب وربطها بالفلاتر والتقارير')

@section('content')
    {{-- DEFINITIONS_POLISH_VIEW_START --}}
    <style>
        .definitions-page { display: grid; gap: 18px; }
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
        .definitions-table-scroll { width:100%; overflow:auto; border-radius:14px; }
        .definitions-table-scroll table { min-width:900px; }
        .definition-code { direction:ltr; unicode-bidi:embed; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12px; }
        .definition-status { display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .definition-status.active { background:rgba(34,197,94,.12); color:#15803d; }
        .definition-status.inactive { background:rgba(100,116,139,.14); color:#475569; }
        .definition-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .definition-actions form { margin:0; }
        .definitions-page .pagination svg { width:18px !important; height:18px !important; max-width:18px !important; max-height:18px !important; }
        .definitions-page .pagination { margin-top:14px; overflow:auto; }
        @media (max-width: 900px) { .definition-stats { grid-template-columns:repeat(2, minmax(0, 1fr)); } .definition-filter-grid { grid-template-columns:1fr; } }
        @media (max-width: 560px) { .definition-stats { grid-template-columns:1fr; } .definitions-header { display:grid; } }
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

        <div class="card">
            <div class="definitions-table-scroll">
                <table>
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
                            <td>{{ $department->description ?: '-' }}</td>
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

            <div class="pagination">{{ $departments->links() }}</div>
        </div>
    </div>
    {{-- DEFINITIONS_POLISH_VIEW_END --}}
@endsection
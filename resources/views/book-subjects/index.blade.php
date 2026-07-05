@extends('layouts.app')

@section('title', 'مواضيع الكتب')
@section('page_title', 'مواضيع الكتب')
@section('page_subtitle', 'إدارة قائمة مواضيع الكتب التي تظهر عند إضافة أو تعديل كتاب')

@section('content')
<style>
    .subjects-page { display:grid; gap:18px; }
    .subjects-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .subjects-header h1 { margin:0; }
    .subjects-header p { margin:6px 0 0; color:var(--muted, #94a3b8); }
    .subjects-stats { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; }
    .subjects-stat { padding:16px; border:1px solid rgba(148,163,184,.25); border-radius:16px; background:rgba(255,255,255,.72); }
    html[data-theme="dark"] .subjects-stat { background:rgba(15,23,42,.55); }
    .subjects-stat span { display:block; color:var(--muted, #94a3b8); font-size:13px; margin-bottom:8px; }
    .subjects-stat strong { font-size:24px; }
    .subjects-filter-grid { display:grid; grid-template-columns:2fr repeat(3, minmax(150px, 1fr)) auto; gap:10px; align-items:end; }
    .subjects-filter-grid .form-group { margin:0; }
    .subjects-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
    .subjects-table-scroll { width:100%; overflow:auto; border-radius:14px; }
    .subjects-table-scroll table { min-width:920px; }
    .subject-code { direction:ltr; unicode-bidi:embed; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:12px; }
    .subject-status { display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:800; }
    .subject-status.active { background:rgba(34,197,94,.12); color:#16a34a; }
    .subject-status.inactive { background:rgba(100,116,139,.14); color:#64748b; }
    @media (max-width: 900px) { .subjects-stats { grid-template-columns:repeat(2, minmax(0, 1fr)); } .subjects-filter-grid { grid-template-columns:1fr; } }
    @media (max-width: 560px) { .subjects-stats { grid-template-columns:1fr; } }
</style>

<div class="subjects-page">
    <div class="subjects-header">
        <div>
            <h1>مواضيع الكتب</h1>
            <p>أضف المواضيع المتكررة حتى تختارها مباشرة من صفحة إضافة الكتاب.</p>
        </div>

        @if(auth()->user()?->hasPermission('book_subjects.manage'))
            <a href="{{ route('book-subjects.create') }}" class="btn btn-primary">+ إضافة موضوع</a>
        @endif
    </div>

    <div class="subjects-stats">
        <div class="subjects-stat"><span>إجمالي المواضيع</span><strong>{{ number_format($stats['total'] ?? 0) }}</strong></div>
        <div class="subjects-stat"><span>المواضيع النشطة</span><strong>{{ number_format($stats['active'] ?? 0) }}</strong></div>
        <div class="subjects-stat"><span>المواضيع المعطلة</span><strong>{{ number_format($stats['inactive'] ?? 0) }}</strong></div>
        <div class="subjects-stat"><span>مرتبطة بكتب</span><strong>{{ number_format($stats['linked'] ?? 0) }}</strong></div>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('book-subjects.index') }}" class="subjects-filter-grid">
            <div class="form-group">
                <label>بحث</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="اسم الموضوع، الكود، أو الوصف">
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
                <label>الارتباط</label>
                <select name="linked">
                    <option value="all" @selected(($filters['linked'] ?? 'all') === 'all')>الكل</option>
                    <option value="linked" @selected(($filters['linked'] ?? 'all') === 'linked')>مرتبطة بكتب</option>
                    <option value="empty" @selected(($filters['linked'] ?? 'all') === 'empty')>غير مرتبطة</option>
                </select>
            </div>
            <div class="form-group">
                <label>الترتيب</label>
                <select name="sort">
                    <option value="order" @selected(($filters['sort'] ?? 'order') === 'order')>حسب الترتيب</option>
                    <option value="latest" @selected(($filters['sort'] ?? 'order') === 'latest')>الأحدث</option>
                    <option value="oldest" @selected(($filters['sort'] ?? 'order') === 'oldest')>الأقدم</option>
                    <option value="name" @selected(($filters['sort'] ?? 'order') === 'name')>الاسم</option>
                    <option value="documents" @selected(($filters['sort'] ?? 'order') === 'documents')>الأكثر استخداماً</option>
                </select>
            </div>
            <div class="subjects-actions">
                <button type="submit" class="btn btn-primary">تطبيق</button>
                <a href="{{ route('book-subjects.index') }}" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="subjects-table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>الموضوع</th>
                        <th>الكود</th>
                        <th>الوصف</th>
                        <th>الترتيب</th>
                        <th>الحالة</th>
                        <th>الكتب</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookSubjects as $subject)
                        <tr>
                            <td><strong>{{ $subject->name }}</strong></td>
                            <td><span class="subject-code">{{ $subject->code ?: '-' }}</span></td>
                            <td>{{ $subject->description ?: '-' }}</td>
                            <td>{{ number_format($subject->sort_order ?? 0) }}</td>
                            <td>
                                @if($subject->is_active)
                                    <span class="subject-status active">● نشط</span>
                                @else
                                    <span class="subject-status inactive">● معطل</span>
                                @endif
                            </td>
                            <td>{{ number_format($subject->all_documents_count ?? $subject->documents_count ?? 0) }}</td>
                            <td>
                                <div class="subjects-actions">
                                    <a href="{{ route('documents.index', ['book_subject_id' => $subject->id]) }}" class="btn btn-secondary">عرض الكتب</a>
                                    @if(auth()->user()?->hasPermission('book_subjects.manage'))
                                        <a href="{{ route('book-subjects.edit', $subject) }}" class="btn btn-primary">تعديل</a>
                                        <form method="POST" action="{{ route('book-subjects.destroy', $subject) }}" data-confirm="هل أنت متأكد من حذف أو تعطيل هذا الموضوع؟">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">{{ ($subject->all_documents_count ?? 0) > 0 ? 'تعطيل' : 'حذف' }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">لا توجد مواضيع كتب حالياً.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">{{ $bookSubjects->links() }}</div>
    </div>
</div>
@endsection

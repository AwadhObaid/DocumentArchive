@extends('layouts.app')

@section('title', 'إدارة النماذج')
@section('page_title', 'إدارة النماذج')
@section('page_subtitle', 'ربط نماذج العمل الداخلية والخارجية بالنظام وفتحها بسرعة')

@section('content')
    <style>
        .forms-page { display:grid; gap:18px; }
        .forms-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .forms-header h1 { margin:0; }
        .forms-header p { margin:6px 0 0; color:var(--muted, #6b7280); }
        .forms-stats { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; }
        .forms-stat { padding:16px; border:1px solid rgba(148,163,184,.25); border-radius:16px; background:rgba(255,255,255,.72); }
        html[data-theme="dark"] .forms-stat { background:rgba(15,23,42,.55); }
        .forms-stat span { display:block; color:var(--muted, #6b7280); font-size:13px; margin-bottom:8px; }
        .forms-stat strong { font-size:24px; }
        .forms-filter-grid { display:grid; grid-template-columns:2fr repeat(3, minmax(150px, 1fr)) auto; gap:10px; align-items:end; }
        .forms-filter-grid .form-group { margin:0; }
        .forms-filter-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .forms-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:14px; }
        .form-link-card { display:grid; gap:12px; padding:16px; border:1px solid rgba(148,163,184,.25); border-radius:18px; background:rgba(255,255,255,.72); }
        html[data-theme="dark"] .form-link-card { background:rgba(15,23,42,.55); }
        .form-link-title { display:flex; align-items:center; gap:10px; }
        .form-link-title .icon { width:40px; height:40px; border-radius:14px; display:inline-flex; align-items:center; justify-content:center; background:rgba(59,130,246,.12); font-size:22px; }
        .form-link-title h3 { margin:0; font-size:18px; }
        .form-link-meta { display:flex; gap:8px; flex-wrap:wrap; color:var(--muted, #6b7280); font-size:13px; }
        .form-link-url { direction:ltr; unicode-bidi:embed; overflow-wrap:anywhere; font-size:12px; color:var(--muted, #6b7280); }
        .form-status { display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .form-status.active { background:rgba(34,197,94,.12); color:#15803d; }
        .form-status.inactive { background:rgba(100,116,139,.14); color:#475569; }
        .forms-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .forms-actions form { margin:0; }
        .forms-page .pagination svg { width:18px !important; height:18px !important; max-width:18px !important; max-height:18px !important; }
        .forms-page .pagination { margin-top:14px; overflow:auto; }
        @media (max-width: 1100px) { .forms-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } .forms-filter-grid { grid-template-columns:1fr 1fr; } }
        @media (max-width: 700px) { .forms-stats, .forms-grid, .forms-filter-grid { grid-template-columns:1fr; } .forms-header { display:grid; } }
    </style>

    <div class="forms-page">
        <div class="forms-header">
            <div>
                <h1>إدارة النماذج</h1>
                <p>ضع هنا روابط النماذج المهمة ليتم فتحها مباشرة من داخل النظام.</p>
            </div>

            @if($canManage)
                <a href="{{ route('form-links.create') }}" class="btn btn-primary">+ إضافة نموذج</a>
            @endif
        </div>

        <div class="forms-stats">
            <div class="forms-stat"><span>إجمالي النماذج</span><strong>{{ number_format($stats['total'] ?? 0) }}</strong></div>
            <div class="forms-stat"><span>النماذج النشطة</span><strong>{{ number_format($stats['active'] ?? 0) }}</strong></div>
            <div class="forms-stat"><span>النماذج المعطلة</span><strong>{{ number_format($stats['inactive'] ?? 0) }}</strong></div>
            <div class="forms-stat"><span>التصنيفات</span><strong>{{ number_format($stats['categories'] ?? 0) }}</strong></div>
        </div>

        <div class="card">
            <form method="GET" action="{{ route('form-links.index') }}" class="forms-filter-grid">
                <div class="form-group">
                    <label>بحث</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="اسم النموذج، الرابط، التصنيف، أو الوصف">
                </div>

                @if($canManage)
                    <div class="form-group">
                        <label>الحالة</label>
                        <select name="status">
                            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>كل الحالات</option>
                            <option value="active" @selected(($filters['status'] ?? 'all') === 'active')>النشطة فقط</option>
                            <option value="inactive" @selected(($filters['status'] ?? 'all') === 'inactive')>المعطلة فقط</option>
                        </select>
                    </div>
                @endif

                <div class="form-group">
                    <label>التصنيف</label>
                    <select name="category">
                        <option value="">كل التصنيفات</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>الترتيب</label>
                    <select name="sort">
                        <option value="order" @selected(($filters['sort'] ?? 'order') === 'order')>ترتيب العرض</option>
                        <option value="latest" @selected(($filters['sort'] ?? 'order') === 'latest')>الأحدث أولاً</option>
                        <option value="oldest" @selected(($filters['sort'] ?? 'order') === 'oldest')>الأقدم أولاً</option>
                        <option value="title" @selected(($filters['sort'] ?? 'order') === 'title')>حسب الاسم</option>
                    </select>
                </div>

                <div class="forms-filter-actions">
                    <button type="submit" class="btn btn-primary">تطبيق</button>
                    <a href="{{ route('form-links.index') }}" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>

        <div class="forms-grid">
            @forelse($formLinks as $formLink)
                <div class="form-link-card">
                    <div class="form-link-title">
                        <span class="icon">{{ $formLink->icon ?: '📝' }}</span>
                        <div>
                            <h3>{{ $formLink->title }}</h3>
                            <div class="form-link-meta">
                                <span>{{ $formLink->category ?: 'بدون تصنيف' }}</span>
                                @if($canManage)
                                    @if($formLink->is_active)
                                        <span class="form-status active">● نشط</span>
                                    @else
                                        <span class="form-status inactive">● معطل</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($formLink->description)
                        <p>{{ $formLink->description }}</p>
                    @else
                        <p style="color:var(--muted, #6b7280);">لا يوجد وصف لهذا النموذج.</p>
                    @endif

                    <div class="form-link-url">{{ $formLink->url }}</div>

                    <div class="forms-actions">
                        <a href="{{ $formLink->url }}" class="btn btn-success" @if($formLink->opens_new_tab) target="_blank" rel="noopener noreferrer" @endif>فتح النموذج</a>

                        @if($canManage)
                            <a href="{{ route('form-links.edit', $formLink) }}" class="btn btn-primary">تعديل</a>
                            <form method="POST" action="{{ route('form-links.destroy', $formLink) }}" data-confirm="هل أنت متأكد من حذف هذا النموذج؟">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">حذف</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="card" style="grid-column:1/-1;">
                    لا توجد نماذج مضافة حالياً.
                    @if($canManage)
                        <a href="{{ route('form-links.create') }}">أضف أول نموذج الآن.</a>
                    @endif
                </div>
            @endforelse
        </div>

        <div class="pagination">{{ $formLinks->links() }}</div>
    </div>
@endsection

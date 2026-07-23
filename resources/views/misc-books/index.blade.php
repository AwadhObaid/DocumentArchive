@extends('layouts.app')

@section('title', $isTrash ? 'سلة الكتب المتفرقة' : 'الكتب المتفرقة')
@section('page_title', $isTrash ? 'سلة الكتب المتفرقة' : 'الكتب المتفرقة')
@section('page_subtitle', 'كتب الموظفين والهيئات والمخاطبات العسكرية والمدنية بترقيم مستقل يبدأ من 2620000 لسنة 2026')

@section('content')
<div class="archive-module-page">
    <div class="archive-module-header">
        <div>
            <h1>{{ $isTrash ? 'سلة الكتب المتفرقة' : 'الكتب المتفرقة' }}</h1>
            <p>
                {{ $isTrash
                    ? 'استعادة الكتب المحذوفة أو حذفها نهائيًا.'
                    : 'وحدة موحدة لكتب الموظفين والهيئات والمخاطبات الأخرى.' }}
            </p>
        </div>

        <div class="archive-module-actions">
            @if($isTrash)
                <a class="btn btn-light" href="{{ route('misc-books.index') }}">العودة إلى الكتب المتفرقة</a>
            @else
                @if(auth()->user()?->hasPermission('misc_books.restore'))
                    <a class="btn btn-light" href="{{ route('misc-books.trash') }}">السلة ({{ number_format($stats['trashed'] ?? 0) }})</a>
                @endif
                @if(auth()->user()?->hasPermission('archive_categories.manage'))
                    <a class="btn btn-secondary" href="{{ route('archive-categories.index', ['module' => 'misc_book']) }}">التصنيفات</a>
                @endif
                @if(auth()->user()?->hasPermission('misc_books.create'))
                    <a class="btn btn-primary" href="{{ route('misc-books.create') }}">+ إضافة كتاب متفرق</a>
                @endif
            @endif
        </div>
    </div>

    <div class="archive-module-stats">
        <div class="archive-module-stat"><span>إجمالي الكتب</span><strong>{{ number_format($stats['total'] ?? 0) }}</strong></div>
        <div class="archive-module-stat"><span>النشطة</span><strong>{{ number_format($stats['active'] ?? 0) }}</strong></div>
        <div class="archive-module-stat"><span>المؤرشفة</span><strong>{{ number_format($stats['archived'] ?? 0) }}</strong></div>
        <div class="archive-module-stat"><span>بها مرفقات</span><strong>{{ number_format($stats['with_attachments'] ?? 0) }}</strong></div>
        <div class="archive-module-stat"><span>في السلة</span><strong>{{ number_format($stats['trashed'] ?? 0) }}</strong></div>
    </div>

    <div class="card">
        <form method="GET" action="{{ $isTrash ? route('misc-books.trash') : route('misc-books.index') }}" class="archive-module-filter">
            <div class="form-group">
                <label>بحث</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="الرقم، الموضوع، الموظف، الهيئة أو الجهة">
            </div>
            <div class="form-group">
                <label>التصنيف</label>
                <select name="category_id">
                    <option value="">كل التصنيفات</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string)($filters['category_id'] ?? '') === (string)$category->id)>
                            {{ $category->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>الطبيعة</label>
                <select name="nature">
                    <option value="all">الكل</option>
                    <option value="military" @selected(($filters['nature'] ?? 'all') === 'military')>عسكري</option>
                    <option value="civil" @selected(($filters['nature'] ?? 'all') === 'civil')>مدني</option>
                    <option value="unspecified" @selected(($filters['nature'] ?? 'all') === 'unspecified')>غير محدد</option>
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
            <div class="archive-module-actions">
                <button type="submit" class="btn btn-primary">بحث</button>
                <a class="btn btn-light" href="{{ $isTrash ? route('misc-books.trash') : route('misc-books.index') }}">إلغاء</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="archive-module-table-wrap">
            <table class="archive-module-table">
                <thead>
                    <tr>
                        <th>الرقم الداخلي</th>
                        <th>التاريخ</th>
                        <th>الموضوع</th>
                        <th>التصنيف</th>
                        <th>الاتجاه</th>
                        <th>الطبيعة</th>
                        <th>الطرف المرتبط</th>
                        <th>المرفقات</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($miscBooks as $miscBook)
                        <tr>
                            <td><span class="archive-module-number">{{ $miscBook->misc_number }}</span></td>
                            <td>{{ $miscBook->formatted_date }}</td>
                            <td class="archive-module-subject"><strong>{{ $miscBook->subject }}</strong></td>
                            <td>{{ $miscBook->category?->full_name ?: '-' }}</td>
                            <td>{{ $miscBook->direction_name }}</td>
                            <td>{{ $miscBook->nature_name }}</td>
                            <td>{{ $miscBook->employee_name ?: ($miscBook->authority_name ?: ($miscBook->sender ?: '-')) }}</td>
                            <td>{{ number_format($miscBook->attachments_count ?? 0) }}</td>
                            <td>
                                <div class="archive-module-actions">
                                    @if($isTrash)
                                        @if(auth()->user()?->hasPermission('misc_books.restore'))
                                            <form method="POST" action="{{ route('misc-books.restore', $miscBook) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-success" type="submit">استعادة</button>
                                            </form>
                                        @endif
                                        @if(auth()->user()?->hasPermission('misc_books.force_delete'))
                                            <form method="POST" action="{{ route('misc-books.force-delete', $miscBook) }}" data-confirm="الحذف النهائي غير قابل للاستعادة. هل أنت متأكد؟">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger" type="submit">حذف نهائي</button>
                                            </form>
                                        @endif
                                    @else
                                        <a class="btn btn-sm btn-light" href="{{ route('misc-books.show', $miscBook) }}">عرض</a>
                                        @if(auth()->user()?->hasPermission('misc_books.edit') && $miscBook->canBeModifiedBy(auth()->user()))
                                            <a class="btn btn-sm btn-primary" href="{{ route('misc-books.edit', $miscBook) }}">تعديل</a>
                                        @endif
                                        @if(auth()->user()?->hasPermission('misc_books.delete') && $miscBook->canBeModifiedBy(auth()->user()))
                                            <form method="POST" action="{{ route('misc-books.destroy', $miscBook) }}" data-confirm="هل تريد نقل الكتاب إلى السلة؟">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9">{{ $isTrash ? 'السلة فارغة.' : 'لا توجد كتب متفرقة مطابقة.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $miscBooks->links() }}</div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'تصنيفات التعاميم والكتب المتفرقة')
@section('page_title', 'تصنيفات التعاميم والكتب المتفرقة')
@section('page_subtitle', 'إدارة التصنيفات الرئيسية والفرعية دون تعديل بنية قاعدة البيانات')

@section('content')
<div class="archive-module-page">
    <div class="archive-module-header">
        <div>
            <h1>إدارة التصنيفات</h1>
            <p>يمكن تعطيل التصنيف المستخدم بدلًا من حذفه.</p>
        </div>
        <div class="archive-module-actions">
            <a class="btn btn-light" href="{{ route('circulars.index') }}">التعاميم</a>
            <a class="btn btn-light" href="{{ route('misc-books.index') }}">الكتب المتفرقة</a>
        </div>
    </div>

    <div class="card">
        <h2>إضافة تصنيف</h2>
        <form method="POST" action="{{ route('archive-categories.store') }}" class="archive-module-form-grid">
            @csrf
            <div class="form-group">
                <label>الوحدة</label>
                <select name="module" id="archiveCategoryModule" required>
                    <option value="circular">التعاميم</option>
                    <option value="misc_book">الكتب المتفرقة</option>
                </select>
            </div>
            <div class="form-group">
                <label>التصنيف الأب</label>
                <select name="parent_id">
                    <option value="">-- تصنيف رئيسي --</option>
                    @foreach($miscCategories->whereNull('parent_id') as $category)
                        <option value="{{ $category->id }}">{{ $category->name }} — كتب متفرقة</option>
                    @endforeach
                </select>
                <small>يستخدم غالبًا للتصنيفات الفرعية في الكتب المتفرقة.</small>
            </div>
            <div class="form-group">
                <label>اسم التصنيف</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>الكود الإنجليزي</label>
                <input type="text" name="code" required pattern="[a-z0-9-]+" placeholder="example-code">
            </div>
            <div class="form-group">
                <label>ترتيب العرض</label>
                <input type="number" name="sort_order" min="0" value="0">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" checked>
                    نشط
                </label>
            </div>
            <div class="form-group full">
                <label>وصف</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            <div class="full">
                <button class="btn btn-primary" type="submit">إضافة التصنيف</button>
            </div>
        </form>
    </div>

    <div class="archive-category-tree">
        <div class="card">
            <h2>تصنيفات التعاميم</h2>
            @forelse($circularCategories as $category)
                <div class="archive-category-row">
                    <div>
                        <strong>{{ $category->name }}</strong>
                        <small style="display:block;">{{ $category->code }}</small>
                    </div>
                    <div>{{ number_format($category->circulars_count ?? 0) }} تعميم</div>
                    <div>{{ $category->is_active ? 'نشط' : 'معطل' }}</div>
                    <div class="archive-module-actions">
                        <form method="POST" action="{{ route('archive-categories.toggle', $category) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-sm btn-light" type="submit">{{ $category->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                        </form>
                        <form method="POST" action="{{ route('archive-categories.destroy', $category) }}" data-confirm="هل تريد حذف التصنيف؟">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="empty-state">لا توجد تصنيفات.</p>
            @endforelse
        </div>

        <div class="card">
            <h2>تصنيفات الكتب المتفرقة</h2>
            @forelse($miscCategories as $category)
                <div class="archive-category-row {{ $category->parent_id ? 'archive-category-child' : '' }}">
                    <div>
                        <strong>{{ $category->full_name }}</strong>
                        <small style="display:block;">{{ $category->code }}</small>
                    </div>
                    <div>{{ number_format($category->misc_books_count ?? 0) }} كتاب</div>
                    <div>{{ $category->is_active ? 'نشط' : 'معطل' }}</div>
                    <div class="archive-module-actions">
                        <form method="POST" action="{{ route('archive-categories.toggle', $category) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-sm btn-light" type="submit">{{ $category->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                        </form>
                        <form method="POST" action="{{ route('archive-categories.destroy', $category) }}" data-confirm="هل تريد حذف التصنيف؟">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="empty-state">لا توجد تصنيفات.</p>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="archive-module-form-note">
            تعديل اسم التصنيف وكوده سيتم إضافته في مرحلة تحسين إدارة التصنيفات.
            المرحلة الحالية تدعم الإضافة والتفعيل والتعطيل والحذف الآمن.
        </div>
    </div>
</div>
@endsection

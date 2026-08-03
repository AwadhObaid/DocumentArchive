@extends('layouts.app')

@section('title', $isTrash ? 'سلة التعاميم' : 'التعاميم')
@section('page_title', $isTrash ? 'سلة التعاميم' : 'التعاميم')
@section('page_subtitle', 'إدارة التعاميم بترقيم مستقل يبدأ من 2610000 لسنة 2026')

@section('content')
<div class="archive-module-page">
    <div class="archive-module-header">
        <div>
            <h1>{{ $isTrash ? 'سلة التعاميم' : 'التعاميم' }}</h1>
            <p>
                {{ $isTrash
                    ? 'استعادة التعاميم المحذوفة أو حذفها نهائيًا.'
                    : 'تسجيل التعاميم والجهة المصدرة وفترة السريان والمرفقات.' }}
            </p>
        </div>

        <div class="archive-module-actions">
            @if($isTrash)
                <a class="btn btn-light" href="{{ route('circulars.index') }}">العودة إلى التعاميم</a>
            @else
                @if(auth()->user()?->hasPermission('circulars.restore'))
                    <a class="btn btn-light" href="{{ route('circulars.trash') }}">سلة التعاميم ({{ number_format($stats['trashed'] ?? 0) }})</a>
                @endif
                @if(auth()->user()?->hasPermission('archive_categories.manage'))
                    <a class="btn btn-secondary" href="{{ route('archive-categories.index', ['module' => 'circular']) }}">التصنيفات</a>
                @endif
                @if(auth()->user()?->hasPermission('circulars.create'))
                    <a class="btn btn-primary" href="{{ route('circulars.create') }}">+ إضافة تعميم</a>
                @endif
            @endif
        </div>
    </div>

    <div class="archive-module-stats">
        <div class="archive-module-stat"><span>إجمالي التعاميم</span><strong>{{ number_format($stats['total'] ?? 0) }}</strong></div>
        <div class="archive-module-stat"><span>السارية</span><strong>{{ number_format($stats['active'] ?? 0) }}</strong></div>
        <div class="archive-module-stat"><span>المؤرشفة</span><strong>{{ number_format($stats['archived'] ?? 0) }}</strong></div>
        <div class="archive-module-stat"><span>بها مرفقات</span><strong>{{ number_format($stats['with_attachments'] ?? 0) }}</strong></div>
        <div class="archive-module-stat"><span>في السلة</span><strong>{{ number_format($stats['trashed'] ?? 0) }}</strong></div>
    </div>

    <div class="card">
        <form method="GET" action="{{ $isTrash ? route('circulars.trash') : route('circulars.index') }}" class="archive-module-filter">
            <div class="form-group">
                <label>بحث</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="الرقم، الموضوع، الجهة المصدرة أو النطاق">
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
                <label>الحالة</label>
                <select name="status">
                    <option value="all">كل الحالات</option>
                    @foreach(['active' => 'ساري', 'expired' => 'منتهي', 'cancelled' => 'ملغي', 'archived' => 'مؤرشف'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? 'all') === $value)>{{ $label }}</option>
                    @endforeach
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
                <a class="btn btn-light" href="{{ $isTrash ? route('circulars.trash') : route('circulars.index') }}">إلغاء</a>
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
                        <th>الجهة المصدرة</th>
                        <th>السريان</th>
                        <th>الحالة</th>
                        <th>المرفقات</th>
                        <th>الفهرسة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($circulars as $circular)
                        <tr>
                            <td><span class="archive-module-number">{{ $circular->circular_number }}</span></td>
                            <td>{{ $circular->formatted_date }}</td>
                            <td class="archive-module-subject"><strong>{{ $circular->subject }}</strong></td>
                            <td>{{ $circular->category?->full_name ?: '-' }}</td>
                            <td>{{ $circular->issuing_entity ?: '-' }}</td>
                            <td>
                                {{ $circular->effective_date?->format('d/m/Y') ?: '-' }}
                                @if($circular->expiry_date)
                                    <br><small>حتى {{ $circular->expiry_date->format('d/m/Y') }}</small>
                                @endif
                            </td>
                            <td><span class="archive-module-badge {{ $circular->status }}">{{ $circular->status_name }}</span></td>
                            <td>{{ number_format($circular->attachments_count ?? 0) }}</td>
                            <td>@include('partials.attachment-index-status', ['record' => $circular, 'sourceType' => 'circular'])</td>
                            <td>
                                <div class="archive-module-actions">
                                    @if($isTrash)
                                        @if(auth()->user()?->hasPermission('circulars.restore'))
                                            <form method="POST" action="{{ route('circulars.restore', $circular) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-success" type="submit">استعادة</button>
                                            </form>
                                        @endif
                                        @if(auth()->user()?->hasPermission('circulars.force_delete'))
                                            <form method="POST" action="{{ route('circulars.force-delete', $circular) }}" data-confirm="الحذف النهائي غير قابل للاستعادة. هل أنت متأكد؟">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger" type="submit">حذف نهائي</button>
                                            </form>
                                        @endif
                                    @else
                                        <a class="btn btn-sm btn-light" href="{{ route('circulars.show', $circular) }}">عرض</a>
                                        @if(auth()->user()?->hasPermission('circulars.edit') && $circular->canBeModifiedBy(auth()->user()))
                                            <a class="btn btn-sm btn-primary" href="{{ route('circulars.edit', $circular) }}">تعديل</a>
                                        @endif
                                        @if(auth()->user()?->hasPermission('circulars.delete') && $circular->canBeModifiedBy(auth()->user()))
                                            <form method="POST" action="{{ route('circulars.destroy', $circular) }}" data-confirm="هل تريد نقل التعميم إلى السلة؟">
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
                        <tr><td colspan="10">{{ $isTrash ? 'سلة التعاميم فارغة.' : 'لا توجد تعاميم مطابقة.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $circulars->links() }}</div>
    </div>
</div>
@endsection

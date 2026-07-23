@extends('layouts.app')
@section('title', 'عرض كتاب متفرق')
@section('page_title', 'الكتاب المتفرق ' . $miscBook->misc_number)
@section('page_subtitle', $miscBook->subject)
@section('content')
<div class="archive-module-page">
    <div class="archive-module-header">
        <div>
            <h1>بيانات الكتاب المتفرق</h1>
            <p class="archive-module-number">{{ $miscBook->misc_number }}</p>
        </div>
        <div class="archive-module-actions">
            <a class="btn btn-light" href="{{ route('misc-books.index') }}">القائمة</a>
            @if(auth()->user()?->hasPermission('misc_books.edit') && $miscBook->canBeModifiedBy(auth()->user()))
                <a class="btn btn-primary" href="{{ route('misc-books.edit', $miscBook) }}">تعديل</a>
            @endif
            @if(auth()->user()?->hasPermission('misc_books.delete') && $miscBook->canBeModifiedBy(auth()->user()))
                <form method="POST" action="{{ route('misc-books.destroy', $miscBook) }}" data-confirm="هل تريد نقل الكتاب إلى السلة؟">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">حذف</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="archive-module-detail-grid">
            <div class="archive-module-detail"><span>الرقم الداخلي</span><strong class="archive-module-number">{{ $miscBook->misc_number }}</strong></div>
            <div class="archive-module-detail"><span>الرقم الأصلي</span><strong>{{ $miscBook->original_number ?: '-' }}</strong></div>
            <div class="archive-module-detail"><span>التاريخ</span><strong>{{ $miscBook->formatted_date }}</strong></div>
            <div class="archive-module-detail"><span>التصنيف</span><strong>{{ $miscBook->category?->full_name ?: '-' }}</strong></div>
            <div class="archive-module-detail"><span>الاتجاه</span><strong>{{ $miscBook->direction_name }}</strong></div>
            <div class="archive-module-detail"><span>الطبيعة</span><strong>{{ $miscBook->nature_name }}</strong></div>
            <div class="archive-module-detail"><span>المرسل</span><strong>{{ $miscBook->sender ?: '-' }}</strong></div>
            <div class="archive-module-detail"><span>المستلم</span><strong>{{ $miscBook->receiver ?: '-' }}</strong></div>
            <div class="archive-module-detail"><span>اسم الموظف</span><strong>{{ $miscBook->employee_name ?: '-' }}</strong></div>
            <div class="archive-module-detail"><span>اسم الهيئة</span><strong>{{ $miscBook->authority_name ?: '-' }}</strong></div>
            <div class="archive-module-detail"><span>الحالة</span><strong>{{ $miscBook->status_name }}</strong></div>
            <div class="archive-module-detail"><span>السرية</span><strong>{{ $miscBook->confidentiality_name }}</strong></div>
            <div class="archive-module-detail"><span>الأولوية</span><strong>{{ $miscBook->priority_name }}</strong></div>
            <div class="archive-module-detail"><span>منشئ السجل</span><strong>{{ $miscBook->creator?->name ?: '-' }}</strong></div>
            <div class="archive-module-detail full"><span>الموضوع</span><div>{{ $miscBook->subject }}</div></div>
            <div class="archive-module-detail full"><span>الكلمات المفتاحية</span><div>{{ $miscBook->keywords ?: '-' }}</div></div>
            <div class="archive-module-detail full"><span>الملاحظات</span><div>{{ $miscBook->notes ?: '-' }}</div></div>
        </div>
    </div>

    <div class="card">
        <div class="archive-module-header">
            <div><h2>المرفقات</h2><p>المعاينة والتنزيل للملفات المرتبطة بالكتاب.</p></div>
        </div>
        @if($miscBook->attachments->count())
            <div class="archive-module-table-wrap">
                <table class="archive-module-table" style="min-width:780px;">
                    <thead><tr><th>الملف</th><th>الإصدار</th><th>الحجم</th><th>رفع بواسطة</th><th>الإجراءات</th></tr></thead>
                    <tbody>
                    @foreach($miscBook->attachments as $attachment)
                        <tr>
                            <td>{{ $attachment->original_name }}</td>
                            <td>#{{ $attachment->version_no }}</td>
                            <td>{{ $attachment->file_size_for_humans }}</td>
                            <td>{{ $attachment->uploader?->name ?: '-' }}</td>
                            <td>
                                @if(auth()->user()?->hasPermission('misc_books.attachments'))
                                    <div class="archive-module-actions">
                                        <a class="btn btn-sm btn-light" href="{{ route('misc-books.attachments.preview', [$miscBook, $attachment]) }}">معاينة</a>
                                        <a class="btn btn-sm btn-primary" href="{{ route('misc-books.attachments.download', [$miscBook, $attachment]) }}">تنزيل</a>
                                    </div>
                                @else
                                    <span class="text-muted">لا توجد صلاحية</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="empty-state">لا توجد مرفقات لهذا الكتاب.</p>
        @endif
    </div>
</div>
@endsection

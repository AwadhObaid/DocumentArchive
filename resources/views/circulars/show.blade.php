@extends('layouts.app')
@section('title', 'عرض تعميم')
@section('page_title', 'التعميم ' . $circular->circular_number)
@section('page_subtitle', $circular->subject)
@section('content')
<div class="archive-module-page">
    <div class="archive-module-header">
        <div>
            <h1>بيانات التعميم</h1>
            <p class="archive-module-number">{{ $circular->circular_number }}</p>
        </div>
        <div class="archive-module-actions">
            <a class="btn btn-light" href="{{ route('circulars.index') }}">القائمة</a>
            @if(auth()->user()?->hasPermission('circulars.edit') && $circular->canBeModifiedBy(auth()->user()))
                <a class="btn btn-primary" href="{{ route('circulars.edit', $circular) }}">تعديل</a>
            @endif
            @if(auth()->user()?->hasPermission('circulars.delete') && $circular->canBeModifiedBy(auth()->user()))
                <form method="POST" action="{{ route('circulars.destroy', $circular) }}" data-confirm="هل تريد نقل التعميم إلى السلة؟">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">حذف</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="archive-module-detail-grid">
            <div class="archive-module-detail"><span>الرقم الداخلي</span><strong class="archive-module-number">{{ $circular->circular_number }}</strong></div>
            <div class="archive-module-detail"><span>الرقم الأصلي</span><strong>{{ $circular->original_number ?: '-' }}</strong></div>
            <div class="archive-module-detail"><span>التاريخ</span><strong>{{ $circular->formatted_date }}</strong></div>
            <div class="archive-module-detail"><span>التصنيف</span><strong>{{ $circular->category?->full_name ?: '-' }}</strong></div>
            <div class="archive-module-detail"><span>الجهة المصدرة</span><strong>{{ $circular->issuing_entity ?: '-' }}</strong></div>
            <div class="archive-module-detail"><span>نطاق التطبيق</span><strong>{{ $circular->scope ?: '-' }}</strong></div>
            <div class="archive-module-detail"><span>بدء السريان</span><strong>{{ $circular->effective_date?->format('d/m/Y') ?: '-' }}</strong></div>
            <div class="archive-module-detail"><span>انتهاء السريان</span><strong>{{ $circular->expiry_date?->format('d/m/Y') ?: '-' }}</strong></div>
            <div class="archive-module-detail"><span>الحالة</span><strong>{{ $circular->status_name }}</strong></div>
            <div class="archive-module-detail"><span>السرية</span><strong>{{ $circular->confidentiality_name }}</strong></div>
            <div class="archive-module-detail"><span>الأولوية</span><strong>{{ $circular->priority_name }}</strong></div>
            <div class="archive-module-detail"><span>منشئ السجل</span><strong>{{ $circular->creator?->name ?: '-' }}</strong></div>
            <div class="archive-module-detail full"><span>الموضوع</span><div>{{ $circular->subject }}</div></div>
            <div class="archive-module-detail full"><span>الكلمات المفتاحية</span><div>{{ $circular->keywords ?: '-' }}</div></div>
            <div class="archive-module-detail full"><span>الملاحظات</span><div>{{ $circular->notes ?: '-' }}</div></div>
        </div>
    </div>

    <div class="card">
        <div class="archive-module-header">
            <div><h2>المرفقات</h2><p>المعاينة والتنزيل للملفات المرتبطة بالتعميم.</p></div>
        </div>
        @if($circular->attachments->count())
            <div class="archive-module-table-wrap">
                <table class="archive-module-table" style="min-width:780px;">
                    <thead><tr><th>الملف</th><th>الإصدار</th><th>الحجم</th><th>رفع بواسطة</th><th>الإجراءات</th></tr></thead>
                    <tbody>
                    @foreach($circular->attachments as $attachment)
                        <tr>
                            <td>{{ $attachment->original_name }}</td>
                            <td>#{{ $attachment->version_no }}</td>
                            <td>{{ $attachment->file_size_for_humans }}</td>
                            <td>{{ $attachment->uploader?->name ?: '-' }}</td>
                            <td>
                                @if(auth()->user()?->hasPermission('circulars.attachments'))
                                    <div class="archive-module-actions">
                                        <a class="btn btn-sm btn-light" href="{{ route('circulars.attachments.preview', [$circular, $attachment]) }}">معاينة</a>
                                        <a class="btn btn-sm btn-primary" href="{{ route('circulars.attachments.download', [$circular, $attachment]) }}">تنزيل</a>
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
            <p class="empty-state">لا توجد مرفقات لهذا التعميم.</p>
        @endif
    </div>
</div>
@endsection

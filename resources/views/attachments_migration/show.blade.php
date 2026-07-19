@extends('layouts.app')

@section('title', 'سجل ترتيب المرفقات')
@section('page_title', 'سجل ترتيب المرفقات')
@section('page_subtitle', 'تفاصيل عملية الفحص أو النقل')

@section('content')
<div class="v75v80-page">
    <div class="page-title">
        <h1>عملية #{{ $run->id }} - {{ $run->mode_name }}</h1>
        <div class="actions"><a href="{{ route('attachments-migration.index') }}" class="btn btn-secondary">رجوع</a></div>
    </div>

    <div class="v75v80-grid v75v80-stats-grid">
        <div class="v75v80-stat"><span>الإجمالي</span><strong>{{ $run->total_items }}</strong></div>
        <div class="v75v80-stat"><span>جاهز</span><strong>{{ $run->ready_items }}</strong></div>
        <div class="v75v80-stat"><span>منسوخ</span><strong>{{ $run->copied_items }}</strong></div>
        <div class="v75v80-stat"><span>منقول</span><strong>{{ $run->moved_items }}</strong></div>
        <div class="v75v80-stat"><span>مفقود/فشل</span><strong>{{ $run->missing_items + $run->failed_items }}</strong></div>
    </div>

    <div class="v75v80-card">
        <div class="table-responsive">
            <table class="v75v80-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الكتاب</th>
                        <th>الحالة</th>
                        <th>الرسالة</th>
                        <th>المسار الحالي</th>
                        <th>المسار الجديد</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($run->items as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ $item->reference_number ?: '-' }}</td>
                            <td><span class="v75v80-badge status-{{ $item->status }}">{{ $item->status_name }}</span></td>
                            <td>{{ $item->message }}</td>
                            <td class="v75v80-ltr">{{ $item->original_path }}</td>
                            <td class="v75v80-ltr">{{ $item->target_path }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

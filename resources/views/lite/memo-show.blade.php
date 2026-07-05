@extends('lite.layout')

@section('title', 'مذكرة ' . $memo->memo_number)

@section('content')
    <section class="lite-title">
        <h2>مذكرة رقم {{ $memo->memo_number }}</h2>
        <p>معاينة بيانات المذكرة ومرفقاتها.</p>
    </section>

    <section class="lite-card lite-detail-grid">
        <div class="lite-field"><span>التاريخ</span><strong>{{ optional($memo->memo_date)->format('d/m/Y') ?: '-' }}</strong></div>
        <div class="lite-field"><span>الموضوع</span><div>{{ $memo->subject }}</div></div>
        <div class="lite-field"><span>الإدارة</span><strong>{{ $memo->department?->name ?? '-' }}</strong></div>
        <div class="lite-field"><span>الواردة من</span><strong>{{ $memo->sender ?: '-' }}</strong></div>
        <div class="lite-field"><span>المستلم</span><strong>{{ $memo->receiver ?: '-' }}</strong></div>
        <div class="lite-field"><span>الحالة</span><span class="lite-pill success">{{ $memo->status_name }}</span></div>
        @if($memo->description || $memo->notes)
            <div class="lite-field"><span>ملاحظات</span><div>{{ $memo->description ?: $memo->notes }}</div></div>
        @endif
    </section>

    <section class="lite-section">
        <div class="lite-section-head"><h3>المرفقات</h3></div>
        <div class="lite-attachments">
            @forelse($memo->attachments as $attachment)
                <div class="lite-attachment">
                    <span class="lite-attachment-name">📎 {{ $attachment->original_name ?: $attachment->file_name }}</span>
                    <a class="lite-btn secondary" href="{{ route('memos.attachments.preview', [$memo, $attachment]) }}">معاينة</a>
                </div>
            @empty
                <div class="lite-card">لا توجد مرفقات لهذه المذكرة.</div>
            @endforelse
        </div>
    </section>
@endsection

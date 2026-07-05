@extends('lite.layout')

@section('title', 'كتاب ' . $document->reference_number)

@section('content')
    <section class="lite-title">
        <h2>كتاب رقم {{ $document->reference_number }}</h2>
        <p>معاينة بيانات الكتاب ومرفقاته.</p>
    </section>

    <section class="lite-card lite-detail-grid">
        <div class="lite-field"><span>التاريخ</span><strong>{{ optional($document->reference_date)->format('d/m/Y') ?: '-' }}</strong></div>
        <div class="lite-field"><span>العنوان</span><strong>{{ $document->title ?: '-' }}</strong></div>
        <div class="lite-field"><span>الموضوع</span><div>{{ $document->bookSubject?->name ?? $document->subject ?? '-' }}</div></div>
        <div class="lite-field"><span>الإدارة</span><strong>{{ $document->department?->name ?? '-' }}</strong></div>
        <div class="lite-field"><span>نوع الكتاب</span><strong>{{ $document->documentType?->name ?? '-' }}</strong></div>
        <div class="lite-field"><span>المرسل / المستلم</span><div>{{ $document->sender ?: '-' }} ← {{ $document->receiver ?: '-' }}</div></div>
        @if($document->description || $document->notes)
            <div class="lite-field"><span>ملاحظات</span><div>{{ $document->description ?: $document->notes }}</div></div>
        @endif
    </section>

    <section class="lite-section">
        <div class="lite-section-head"><h3>المرفقات</h3></div>
        <div class="lite-attachments">
            @forelse($document->attachments as $attachment)
                <div class="lite-attachment">
                    <span class="lite-attachment-name">📎 {{ $attachment->original_name ?: $attachment->file_name }}</span>
                    <a class="lite-btn secondary" href="{{ route('attachments.preview', $attachment) }}">معاينة</a>
                </div>
            @empty
                <div class="lite-card">لا توجد مرفقات لهذا الكتاب.</div>
            @endforelse
        </div>
    </section>
@endsection

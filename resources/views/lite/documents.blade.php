@extends('lite.layout')

@section('title', 'الكتب')

@section('content')
    <section class="lite-title">
        <h2>الكتب</h2>
        <p>بحث ومعاينة فقط، بدون أزرار إدخال أو تعديل.</p>
    </section>

    <form method="GET" action="{{ route('lite.documents.index') }}" class="lite-search">
        <input type="search" name="q" value="{{ $q }}" placeholder="رقم الكتاب، الموضوع، الجهة، المرسل...">
        <button type="submit" class="lite-btn">بحث</button>
    </form>

    <div class="lite-list">
        @forelse($documents as $document)
            <a class="lite-item" href="{{ route('lite.documents.show', $document) }}">
                <div class="lite-item-top">
                    <span class="lite-item-number">{{ $document->reference_number }}</span>
                    <span class="lite-item-date">{{ optional($document->reference_date)->format('d/m/Y') }}</span>
                </div>
                <h4>{{ $document->title ?: $document->subject ?: 'بدون عنوان' }}</h4>
                <div class="lite-meta">
                    {{ $document->department?->name ?? '-' }} · {{ $document->documentType?->name ?? '-' }} · المرفقات: {{ $document->attachments_count ?? 0 }}
                </div>
            </a>
        @empty
            <div class="lite-card">لا توجد نتائج مطابقة.</div>
        @endforelse
    </div>

    <div class="lite-pagination">{{ $documents->links() }}</div>
@endsection

@extends('lite.layout')

@section('title', 'الرئيسية')

@section('content')
    <section class="lite-title">
        <h2>نسخة الهاتف لايت</h2>
        <p>معاينة سريعة للكتب والمذكرات والإشعارات بدون إدخال أو تعديل أو حذف.</p>
    </section>

    <section class="lite-grid">
        <div class="lite-stat"><span>الكتب</span><strong>{{ $stats['documents'] }}</strong></div>
        <div class="lite-stat"><span>المذكرات</span><strong>{{ $stats['memos'] }}</strong></div>
        <div class="lite-stat"><span>الإشعارات</span><strong>{{ $stats['notifications'] }}</strong></div>
        <div class="lite-stat"><span>غير المقروءة</span><strong data-lite-unread-count>{{ $stats['unread_notifications'] }}</strong></div>
    </section>

    <section class="lite-section">
        <div class="lite-section-head">
            <h3>آخر الكتب</h3>
            <a class="lite-link" href="{{ route('lite.documents.index') }}">عرض الكل</a>
        </div>
        <div class="lite-list">
            @forelse($latestDocuments as $document)
                <a class="lite-item" href="{{ route('lite.documents.show', $document) }}">
                    <div class="lite-item-top">
                        <span class="lite-item-number">{{ $document->reference_number }}</span>
                        <span class="lite-item-date">{{ optional($document->reference_date)->format('d/m/Y') }}</span>
                    </div>
                    <h4>{{ $document->title ?: $document->subject ?: 'بدون عنوان' }}</h4>
                    <div class="lite-meta">{{ $document->department?->name ?? '-' }} · {{ $document->bookSubject?->name ?? $document->subject ?? '-' }}</div>
                </a>
            @empty
                <div class="lite-card">لا توجد كتب حالياً.</div>
            @endforelse
        </div>
    </section>

    <section class="lite-section">
        <div class="lite-section-head">
            <h3>آخر المذكرات</h3>
            <a class="lite-link" href="{{ route('lite.memos.index') }}">عرض الكل</a>
        </div>
        <div class="lite-list">
            @forelse($latestMemos as $memo)
                <a class="lite-item" href="{{ route('lite.memos.show', $memo) }}">
                    <div class="lite-item-top">
                        <span class="lite-item-number">{{ $memo->memo_number }}</span>
                        <span class="lite-item-date">{{ optional($memo->memo_date)->format('d/m/Y') }}</span>
                    </div>
                    <h4>{{ $memo->subject }}</h4>
                    <div class="lite-meta">{{ $memo->department?->name ?? '-' }} · {{ $memo->sender ?: '-' }}</div>
                </a>
            @empty
                <div class="lite-card">لا توجد مذكرات حالياً.</div>
            @endforelse
        </div>
    </section>
@endsection

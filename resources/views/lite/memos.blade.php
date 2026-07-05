@extends('lite.layout')

@section('title', 'المذكرات')

@section('content')
    <section class="lite-title">
        <h2>المذكرات</h2>
        <p>بحث ومعاينة فقط للمذكرات الواردة.</p>
    </section>

    <form method="GET" action="{{ route('lite.memos.index') }}" class="lite-search">
        <input type="search" name="q" value="{{ $q }}" placeholder="رقم المذكرة، الموضوع، الجهة، الوارد من...">
        <button type="submit" class="lite-btn">بحث</button>
    </form>

    <div class="lite-list">
        @forelse($memos as $memo)
            <a class="lite-item" href="{{ route('lite.memos.show', $memo) }}">
                <div class="lite-item-top">
                    <span class="lite-item-number">{{ $memo->memo_number }}</span>
                    <span class="lite-item-date">{{ optional($memo->memo_date)->format('d/m/Y') }}</span>
                </div>
                <h4>{{ $memo->subject }}</h4>
                <div class="lite-meta">
                    {{ $memo->department?->name ?? '-' }} · {{ $memo->sender ?: '-' }} · المرفقات: {{ $memo->attachments_count ?? 0 }}
                </div>
            </a>
        @empty
            <div class="lite-card">لا توجد نتائج مطابقة.</div>
        @endforelse
    </div>

    <div class="lite-pagination">{{ $memos->links() }}</div>
@endsection

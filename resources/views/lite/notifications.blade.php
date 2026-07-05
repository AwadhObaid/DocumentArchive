@extends('lite.layout')

@section('title', 'الإشعارات')

@section('content')
    <section class="lite-title">
        <h2>الإشعارات</h2>
        <p>الإشعارات غير المقروءة: <span class="lite-badge" data-lite-unread-count>{{ $unreadCount }}</span></p>
    </section>

    <div class="lite-list">
        @forelse($notifications as $notification)
            @php
                $link = $notification->link ?? $notification->url ?? null;
                $body = $notification->body ?? $notification->message ?? '';
            @endphp
            <a class="lite-item" href="{{ $link ?: '#' }}">
                <div class="lite-item-top">
                    <span class="lite-pill {{ $notification->read_at ? '' : 'warning' }}">{{ $notification->read_at ? 'مقروء' : 'جديد' }}</span>
                    <span class="lite-item-date">{{ optional($notification->created_at)->format('d/m/Y H:i') }}</span>
                </div>
                <h4>{{ $notification->title ?? 'إشعار' }}</h4>
                <div class="lite-meta">{{ $body }}</div>
            </a>
        @empty
            <div class="lite-card">لا توجد إشعارات حالياً.</div>
        @endforelse
    </div>

    <div class="lite-pagination">{{ $notifications->links() }}</div>
@endsection

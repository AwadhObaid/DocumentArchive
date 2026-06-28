@extends('layouts.app')

@section('content')
<div class="page-header" style="margin-bottom: 20px;">
    <h1>🔔 مركز الإشعارات</h1>
    <p>متابعة التنبيهات المهمة الخاصة بالنظام وجودة البيانات والنسخ الاحتياطي.</p>
</div>

<div class="card" style="padding: 18px; margin-bottom: 18px;">
    <div style="display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
        <strong>الإشعارات غير المقروءة: {{ $unreadCount }}</strong>
        <form method="POST" action="{{ route('notifications.read_all') }}" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-primary">تعليم الكل كمقروء</button>
        </form>
    </div>
</div>

<div class="card" style="padding: 0; overflow:hidden;">
    @forelse($notifications as $notification)
        <div style="padding:16px 18px; border-bottom:1px solid rgba(148,163,184,.22); {{ $notification->read_at ? '' : 'background:rgba(37,99,235,.10);' }}">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;">
                <div style="flex:1; min-width:240px;">
                    <div style="font-weight:900; font-size:16px; margin-bottom:6px;">
                        @if(!$notification->read_at)<span style="color:#60a5fa;">●</span>@endif
                        {{ $notification->title }}
                    </div>
                    @if($notification->body)
                        <div style="color:var(--muted, #94a3b8); line-height:1.8;">{{ $notification->body }}</div>
                    @endif
                    <div style="font-size:12px; color:var(--muted, #94a3b8); margin-top:7px;">
                        {{ optional($notification->created_at)->format('Y-m-d H:i') }}
                    </div>
                </div>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    @if($notification->link)
                        <a href="{{ $notification->link }}" class="btn btn-secondary">فتح</a>
                    @endif
                    @if(!$notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}" style="margin:0;">
                            @csrf
                            <button class="btn btn-primary" type="submit">مقروء</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('notifications.destroy', $notification) }}" style="margin:0;">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger" type="submit" onclick="return confirm('هل تريد إخفاء هذا الإشعار؟')">إخفاء</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div style="padding: 38px; text-align:center; color:var(--muted, #94a3b8);">
            لا توجد إشعارات حالياً.
        </div>
    @endforelse
</div>

<div style="margin-top:16px;">
    {{ $notifications->links() }}
</div>
@endsection
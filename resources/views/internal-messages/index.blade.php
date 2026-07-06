@extends('layouts.app')

@section('title', 'المراسلات الداخلية')
@section('page_title', 'المراسلات الداخلية')
@section('page_subtitle', 'إحالة الكتب والمذكرات والملفات بين مستخدمي النظام مع التنبيهات')

@section('content')
<div class="internal-messages-page">
    <div class="internal-messages-header">
        <div>
            <h1>المراسلات الداخلية</h1>
            <p>إرسال واستقبال رسائل داخل النظام مع ربط الكتب والمذكرات والمرفقات.</p>
        </div>
        @if(auth()->user()?->hasPermission('internal_messages.send'))
            <a href="{{ route('internal-messages.create') }}" class="btn btn-primary">رسالة جديدة</a>
        @endif
    </div>

    <div class="internal-message-stats">
        <div class="internal-message-stat"><span>صندوق الوارد</span><strong>{{ number_format($stats['inbox'] ?? 0) }}</strong></div>
        <div class="internal-message-stat"><span>غير مقروءة</span><strong>{{ number_format($stats['unread'] ?? 0) }}</strong></div>
        <div class="internal-message-stat"><span>صندوق الصادر</span><strong>{{ number_format($stats['sent'] ?? 0) }}</strong></div>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
            <div class="internal-message-tabs">
                <a href="{{ route('internal-messages.index', ['folder' => 'inbox']) }}" class="{{ $folder === 'inbox' ? 'active' : '' }}">📥 الوارد</a>
                <a href="{{ route('internal-messages.index', ['folder' => 'sent']) }}" class="{{ $folder === 'sent' ? 'active' : '' }}">📤 الصادر</a>
            </div>
        </div>

        <form method="GET" action="{{ route('internal-messages.index') }}" class="internal-message-filter" style="margin-bottom:14px;">
            <input type="hidden" name="folder" value="{{ $folder }}">
            <div class="form-group" style="margin:0;">
                <label for="q">بحث</label>
                <input id="q" type="search" name="q" value="{{ $q }}" placeholder="ابحث بالعنوان، المستخدم، رقم الكتاب أو رقم المذكرة">
            </div>
            <button type="submit" class="btn btn-primary">بحث</button>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>الحالة</th>
                        <th>العنوان</th>
                        <th>{{ $folder === 'sent' ? 'المستلم' : 'المرسل' }}</th>
                        <th>الارتباط</th>
                        <th>المرفقات</th>
                        <th>التاريخ</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $message)
                        @php
                            $isUnread = $folder === 'inbox' && $message->read_at === null;
                            $party = $folder === 'sent' ? $message->receiver : $message->sender;
                            $referenceUrl = $message->document
                                ? route('documents.show', $message->document)
                                : ($message->memo ? route('memos.show', $message->memo) : null);
                        @endphp
                        <tr class="{{ $isUnread ? 'internal-message-row-unread' : '' }}">
                            <td><span class="internal-message-status {{ $isUnread ? 'unread' : '' }}">{{ $folder === 'sent' ? 'مرسلة' : $message->status_name }}</span></td>
                            <td class="internal-message-subject">
                                <strong>{{ $message->subject }}</strong>
                                @if($message->body)
                                    <small>{{ \Illuminate\Support\Str::limit($message->body, 105) }}</small>
                                @endif
                            </td>
                            <td>{{ $party?->name ?: '-' }}</td>
                            <td>
                                @if($referenceUrl)
                                    <a class="internal-message-reference" href="{{ $referenceUrl }}">{{ $message->reference_label }}</a>
                                @else
                                    <span class="text-muted">بدون ارتباط</span>
                                @endif
                            </td>
                            <td>{{ number_format($message->attachments_count ?? 0) }}</td>
                            <td>{{ $message->created_at?->format('Y-m-d H:i') ?: '-' }}</td>
                            <td>
                                <div class="internal-message-actions">
                                    <a href="{{ route('internal-messages.show', $message) }}" class="btn btn-secondary">عرض</a>
                                    <form method="POST" action="{{ route('internal-messages.archive', $message) }}" data-confirm="هل تريد أرشفة هذه الرسالة من صندوقك؟">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-light">أرشفة</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">لا توجد رسائل داخلية في هذا الصندوق.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $messages->links() }}
        </div>
    </div>
</div>
@endsection

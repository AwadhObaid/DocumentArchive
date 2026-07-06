@extends('layouts.app')

@section('title', 'عرض رسالة داخلية')
@section('page_title', 'عرض رسالة داخلية')
@section('page_subtitle', 'تفاصيل المراسلة الداخلية والارتباطات والمرفقات')

@section('content')
@php
    $isReceiver = (int) $message->receiver_id === (int) auth()->id();
    $isSender = (int) $message->sender_id === (int) auth()->id();
    $linkedUrl = $message->document
        ? route('documents.show', $message->document)
        : ($message->memo ? route('memos.show', $message->memo) : null);
    $linkedTitle = $message->document
        ? ('كتاب رقم ' . $message->document->reference_number)
        : ($message->memo ? ('مذكرة رقم ' . $message->memo->memo_number) : null);
    $linkedSubject = $message->document
        ? ($message->document->subject ?: $message->document->title ?: 'بدون موضوع')
        : ($message->memo ? ($message->memo->subject ?: 'بدون موضوع') : null);
@endphp

<div class="internal-messages-page">
    <div class="internal-messages-header">
        <div>
            <h1>{{ $message->subject }}</h1>
            <p>{{ $isReceiver ? 'رسالة واردة' : 'رسالة صادرة' }} - {{ $message->created_at?->format('Y-m-d H:i') }}</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('internal-messages.index', ['folder' => $isSender && !$isReceiver ? 'sent' : 'inbox']) }}" class="btn btn-light">رجوع</a>
            @if(auth()->user()?->hasPermission('internal_messages.send'))
                <a href="{{ route('internal-messages.create', ['document_id' => $message->document_id, 'memo_id' => $message->memo_id]) }}" class="btn btn-primary">إعادة إرسال/تحويل</a>
            @endif
            <form method="POST" action="{{ route('internal-messages.archive', $message) }}" data-confirm="هل تريد أرشفة هذه الرسالة من صندوقك؟">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-secondary">أرشفة</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="internal-message-card-grid">
            <div class="internal-message-info"><span>المرسل</span><strong>{{ $message->sender?->name ?: '-' }}</strong></div>
            <div class="internal-message-info"><span>المستلم</span><strong>{{ $message->receiver?->name ?: '-' }}</strong></div>
            <div class="internal-message-info"><span>حالة القراءة</span><strong>{{ $message->read_at ? 'مقروءة في ' . $message->read_at->format('Y-m-d H:i') : 'غير مقروءة' }}</strong></div>
            <div class="internal-message-info"><span>عدد المرفقات الإضافية</span><strong>{{ number_format($message->attachments->count()) }}</strong></div>
        </div>
    </div>

    @if($linkedUrl)
        <div class="internal-message-linked-box">
            <div>
                <strong>{{ $linkedTitle }}</strong>
                <small>{{ $linkedSubject }}</small>
            </div>
            <a href="{{ $linkedUrl }}" class="btn btn-primary">فتح {{ $message->document ? 'الكتاب' : 'المذكرة' }}</a>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><h2>نص الرسالة</h2></div>
        <div class="internal-message-body">{{ $message->body ?: 'لا يوجد نص إضافي.' }}</div>
    </div>

    <div class="card">
        <div class="card-header"><h2>المرفقات الإضافية</h2></div>
        @if($message->attachments->count())
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>اسم الملف</th>
                            <th>النوع</th>
                            <th>الحجم</th>
                            <th>حالة التخزين</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($message->attachments as $attachment)
                            @php
                                $exists = $attachment->existsOnDisk();
                                $extension = strtoupper($attachment->extension ?: pathinfo($attachment->original_name, PATHINFO_EXTENSION));
                            @endphp
                            <tr>
                                <td style="min-width:240px;white-space:normal;word-break:break-word;">{{ $attachment->original_name }}</td>
                                <td>{{ $extension ?: 'ملف' }}</td>
                                <td>{{ $attachment->file_size_for_humans }}</td>
                                <td>
                                    @if($exists)
                                        <span class="badge" style="background:#dcfce7;color:#166534;border:1px solid #86efac;">موجود</span>
                                    @else
                                        <span class="badge" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;">مفقود</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="internal-message-actions">
                                        @if($exists)
                                            <a class="btn btn-light" href="{{ route('internal-messages.attachments.preview', [$message, $attachment]) }}">عرض</a>
                                            <a class="btn btn-primary" href="{{ route('internal-messages.attachments.download', [$message, $attachment]) }}">تنزيل</a>
                                        @else
                                            <span class="text-muted">الملف غير موجود على التخزين</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="empty-state">لا توجد مرفقات إضافية داخل الرسالة.</p>
        @endif
    </div>
</div>
@endsection

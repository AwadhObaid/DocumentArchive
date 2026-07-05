@extends('layouts.app')

@section('title', 'تفاصيل البريد')
@section('page_title', 'تفاصيل البريد')
@section('page_subtitle', 'تفاصيل عملية إرسال البريد الإلكتروني وحالتها.')

@section('content')
<div class="email-page">
    <div class="page-header">
        <div>
            <h1>تفاصيل البريد</h1>
            <p>{{ $emailMessage->subject }}</p>
        </div>
        <div class="page-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <a href="{{ route('emails.index') }}" class="btn btn-light">رجوع لسجل البريد</a>
            @if($emailMessage->document && auth()->user()?->hasPermission('emails.send'))
                <a href="{{ route('documents.email.compose', $emailMessage->document) }}" class="btn btn-primary">إرسال جديد لهذا الكتاب</a>
            @endif
            @if($emailMessage->memo && auth()->user()?->hasPermission('emails.send'))
                <a href="{{ route('memos.email.compose', $emailMessage->memo) }}" class="btn btn-primary">إرسال جديد لهذه المذكرة</a>
            @endif
        </div>
    </div>

    <div class="email-compose-layout">
        <div class="email-log-card">
            <h2>بيانات الإرسال</h2>
            <div class="table-responsive">
                <table class="table details-table">
                    <tbody>
                        <tr><th>الحالة</th><td><span class="email-status {{ $emailMessage->status }}">{{ $emailMessage->status_name }}</span></td></tr>
                        <tr><th>إلى</th><td>{{ implode('، ', (array) $emailMessage->to_recipients) ?: '-' }}</td></tr>
                        <tr><th>CC</th><td>{{ implode('، ', (array) $emailMessage->cc_recipients) ?: '-' }}</td></tr>
                        <tr><th>BCC</th><td>{{ implode('، ', (array) $emailMessage->bcc_recipients) ?: '-' }}</td></tr>
                        <tr><th>الموضوع</th><td>{{ $emailMessage->subject }}</td></tr>
                        <tr><th>المرفقات</th><td>{{ $emailMessage->attachments_count }} / {{ $emailMessage->total_attachment_size_for_humans }}</td></tr>
                        <tr><th>المستخدم</th><td>{{ $emailMessage->creator?->name ?? '-' }}</td></tr>
                        <tr><th>وقت الإرسال</th><td>{{ optional($emailMessage->sent_at)->format('Y-m-d H:i') ?: '-' }}</td></tr>
                        <tr><th>وقت الإنشاء</th><td>{{ optional($emailMessage->created_at)->format('Y-m-d H:i') }}</td></tr>
                        @if($emailMessage->error_message)
                            <tr><th>رسالة الخطأ</th><td style="white-space:pre-wrap;color:#b91c1c;">{{ $emailMessage->error_message }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <h2 style="margin-top:18px;">نص الرسالة</h2>
            <div class="email-note-box" style="white-space:pre-wrap;">{{ $emailMessage->body }}</div>
        </div>

        <aside class="email-document-panel">
            @if($emailMessage->document)
                <h2>الكتاب المرتبط</h2>
                <div class="email-document-meta">
                    <div><span>رقم الكتاب</span><strong>{{ $emailMessage->document->reference_number }}</strong></div>
                    <div><span>التاريخ</span><strong>{{ optional($emailMessage->document->reference_date)->format('d/m/Y') ?: '-' }}</strong></div>
                    <div><span>الموضوع</span><strong>{{ \Illuminate\Support\Str::limit($emailMessage->document->subject ?: $emailMessage->document->title ?: '-', 70) }}</strong></div>
                    <div><span>الإدارة</span><strong>{{ $emailMessage->document->department?->name ?? '-' }}</strong></div>
                    <div><span>النوع</span><strong>{{ $emailMessage->document->documentType?->name ?? '-' }}</strong></div>
                </div>
                <a href="{{ route('documents.show', $emailMessage->document) }}" class="btn btn-secondary">فتح الكتاب</a>
            @elseif($emailMessage->memo)
                <h2>المذكرة المرتبطة</h2>
                <div class="email-document-meta">
                    <div><span>رقم المذكرة</span><strong>{{ $emailMessage->memo->memo_number }}</strong></div>
                    <div><span>التاريخ</span><strong>{{ optional($emailMessage->memo->memo_date)->format('d/m/Y') ?: '-' }}</strong></div>
                    <div><span>الموضوع</span><strong>{{ \Illuminate\Support\Str::limit($emailMessage->memo->subject ?: '-', 70) }}</strong></div>
                    <div><span>الإدارة</span><strong>{{ $emailMessage->memo->department?->name ?? '-' }}</strong></div>
                </div>
                <a href="{{ route('memos.show', $emailMessage->memo) }}" class="btn btn-secondary">فتح المذكرة</a>
            @else
                <h2>لا يوجد كتاب أو مذكرة مرتبطة</h2>
                <div class="email-note-box">هذه الرسالة لم تُرسل من كتاب أو مذكرة محددة.</div>
            @endif

            @if($emailMessage->attachment_names)
                <h2 style="margin-top:18px;">المرفقات المرسلة</h2>
                <div class="email-attachments-list">
                    @foreach((array) $emailMessage->attachment_names as $name)
                        <div class="email-attachment-item"><span>📎</span><strong>{{ $name }}</strong></div>
                    @endforeach
                </div>
            @endif
        </aside>
    </div>
</div>
@endsection

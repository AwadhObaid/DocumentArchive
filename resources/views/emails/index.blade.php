@extends('layouts.app')

@section('title', 'البريد الإلكتروني')
@section('page_title', 'البريد الإلكتروني')
@section('page_subtitle', 'إرسال الكتب ومرفقاتها ومتابعة سجل الإرسال.')

@section('content')
<div class="email-page">
    <div class="page-header">
        <div>
            <h1>البريد الإلكتروني</h1>
            <p>إرسال الكتب المؤرشفة مع بياناتها ومرفقاتها، ومتابعة سجل الإرسال.</p>
        </div>
        <div class="page-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            @if(auth()->user()?->hasPermission('emails.send'))
                <a href="{{ route('emails.compose') }}" class="btn btn-primary">رسالة جديدة</a>
            @endif
            <a href="{{ route('emails.index') }}" class="btn btn-secondary">تحديث</a>
        </div>
    </div>

    <div class="email-summary-grid">
        <div class="email-summary-card"><span>إجمالي عمليات الإرسال</span><strong>{{ number_format($summary['total'] ?? 0) }}</strong></div>
        <div class="email-summary-card"><span>تم الإرسال</span><strong>{{ number_format($summary['sent'] ?? 0) }}</strong></div>
        <div class="email-summary-card"><span>فشل الإرسال</span><strong>{{ number_format($summary['failed'] ?? 0) }}</strong></div>
        <div class="email-summary-card"><span>مرتبطة بكتب</span><strong>{{ number_format($summary['with_documents'] ?? 0) }}</strong></div>
    </div>

    <div class="email-log-card">
        <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
            <h2>سجل البريد المرسل</h2>
            <span class="text-muted">يعرض آخر عمليات الإرسال وحالتها.</span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>الحالة</th>
                        <th>الموضوع</th>
                        <th>إلى</th>
                        <th>الكتاب</th>
                        <th>المرفقات</th>
                        <th>المستخدم</th>
                        <th>التاريخ</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $message)
                        <tr>
                            <td><span class="email-status {{ $message->status }}">{{ $message->status_name }}</span></td>
                            <td style="min-width:220px;white-space:normal;word-break:break-word;">{{ $message->subject }}</td>
                            <td style="min-width:180px;white-space:normal;word-break:break-word;">{{ $message->recipients_summary ?: '-' }}</td>
                            <td>
                                @if($message->document)
                                    <a href="{{ route('documents.show', $message->document) }}">{{ $message->document->reference_number }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $message->attachments_count }} / {{ $message->total_attachment_size_for_humans }}</td>
                            <td>{{ $message->creator?->name ?? '-' }}</td>
                            <td>{{ optional($message->sent_at ?: $message->created_at)->format('Y-m-d H:i') }}</td>
                            <td><a href="{{ route('emails.show', $message) }}" class="btn btn-sm btn-light">تفاصيل</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">لا توجد عمليات إرسال حتى الآن.</td>
                        </tr>
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

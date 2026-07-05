@extends('layouts.app')

@section('title', 'واتساب')
@section('page_title', 'واتساب')
@section('page_subtitle', 'تجهيز رسائل واتساب للكتب ومتابعة سجل الفتح.')

@section('content')
<div class="whatsapp-page">
    <div class="page-header">
        <div>
            <h1>واتساب</h1>
            <p>جهّز رسالة واتساب من بيانات الكتاب وافتحها مباشرة في WhatsApp Web أو تطبيق واتساب.</p>
        </div>
        <div class="page-actions">
            @if(auth()->user()?->hasPermission('whatsapp.send'))
                <a href="{{ route('whatsapp.compose') }}" class="btn btn-primary">رسالة واتساب جديدة</a>
            @endif
            <a href="{{ route('whatsapp.index') }}" class="btn btn-light">تحديث</a>
        </div>
    </div>

    <div class="whatsapp-warning-box">
        <strong>تنبيه مهم:</strong>
        واتساب لا يسمح بإرفاق ملفات الكتاب تلقائيًا عبر الرابط العادي. هذه المرحلة تجهّز نص الرسالة وتفتح واتساب، أما المرفقات فيتم إرسالها يدويًا عند الحاجة.
    </div>

    <div class="whatsapp-summary-grid">
        <div class="whatsapp-stat-card"><div><span>إجمالي العمليات</span><strong>{{ $summary['total'] ?? 0 }}</strong></div><div class="icon">🟢</div></div>
        <div class="whatsapp-stat-card"><div><span>تم فتح واتساب</span><strong>{{ $summary['opened'] ?? 0 }}</strong></div><div class="icon">✅</div></div>
        <div class="whatsapp-stat-card"><div><span>مرتبطة بكتب / مذكرات</span><strong>{{ ($summary['with_documents'] ?? 0) + ($summary['with_memos'] ?? 0) }}</strong></div><div class="icon">📄</div></div>
        <div class="whatsapp-stat-card"><div><span>عمليات اليوم</span><strong>{{ $summary['today'] ?? 0 }}</strong></div><div class="icon">📅</div></div>
    </div>

    <div class="whatsapp-panel">
        <div class="page-header" style="margin-bottom:12px;">
            <div>
                <h2>سجل رسائل واتساب</h2>
                <p>يعرض عمليات فتح واتساب التي تم تجهيزها من النظام.</p>
            </div>
        </div>

        <div class="whatsapp-table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>الحالة</th>
                        <th>المستلم</th>
                        <th>الرقم</th>
                        <th>الكتاب/المذكرة</th>
                        <th>معاينة الرسالة</th>
                        <th>المستخدم</th>
                        <th>التاريخ</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $message)
                        <tr>
                            <td><span class="whatsapp-status {{ $message->status }}">{{ $message->status_name }}</span></td>
                            <td>{{ $message->recipient_name ?: '-' }}</td>
                            <td dir="ltr">+{{ $message->normalized_phone }}</td>
                            <td>
                                @if($message->document)
                                    <a href="{{ route('documents.show', $message->document) }}">{{ $message->document->reference_number }}</a>
                                @elseif($message->memo)
                                    <a href="{{ route('memos.show', $message->memo) }}">{{ $message->memo->memo_number }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td style="min-width:260px;white-space:normal;">{{ $message->message_preview ?: '-' }}</td>
                            <td>{{ $message->creator?->name ?? '-' }}</td>
                            <td>{{ optional($message->opened_at ?: $message->created_at)->format('Y-m-d H:i') }}</td>
                            <td><a href="{{ route('whatsapp.show', $message) }}" class="btn btn-sm btn-light">تفاصيل</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">لا توجد عمليات واتساب حتى الآن.</td>
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

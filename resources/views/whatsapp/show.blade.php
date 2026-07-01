@extends('layouts.app')

@section('title', 'تفاصيل رسالة واتساب')
@section('page_title', 'تفاصيل رسالة واتساب')
@section('page_subtitle', 'عرض تفاصيل عملية فتح واتساب المسجلة في النظام.')

@section('content')
<div class="whatsapp-page">
    <div class="page-header">
        <div>
            <h1>تفاصيل رسالة واتساب</h1>
            <p>رقم المستلم، الرسالة، والكتاب المرتبط إن وجد.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('whatsapp.index') }}" class="btn btn-light">رجوع للسجل</a>
            <a href="{{ $whatsappMessage->whatsapp_url }}" target="_blank" rel="noopener" class="btn btn-primary">فتح واتساب مرة أخرى</a>
        </div>
    </div>

    <div class="whatsapp-compose-layout">
        <section class="whatsapp-panel">
            <h2>بيانات العملية</h2>
            <div class="whatsapp-document-meta">
                <div><span>الحالة</span><strong><span class="whatsapp-status {{ $whatsappMessage->status }}">{{ $whatsappMessage->status_name }}</span></strong></div>
                <div><span>اسم المستلم</span><strong>{{ $whatsappMessage->recipient_name ?: '-' }}</strong></div>
                <div><span>رقم واتساب</span><strong dir="ltr">+{{ $whatsappMessage->normalized_phone }}</strong></div>
                <div><span>المستخدم</span><strong>{{ $whatsappMessage->creator?->name ?? '-' }}</strong></div>
                <div><span>التاريخ</span><strong>{{ optional($whatsappMessage->opened_at ?: $whatsappMessage->created_at)->format('Y-m-d H:i') }}</strong></div>
            </div>

            <h2 style="margin-top:18px;">نص الرسالة</h2>
            <div class="whatsapp-message-box">{{ $whatsappMessage->message_body }}</div>
        </section>

        <aside class="whatsapp-document-panel">
            @if($whatsappMessage->document)
                @php($document = $whatsappMessage->document)
                <h2>الكتاب المرتبط</h2>
                <div class="whatsapp-document-meta">
                    <div><span>رقم الكتاب</span><strong>{{ $document->reference_number }}</strong></div>
                    <div><span>التاريخ</span><strong>{{ optional($document->reference_date)->format('d/m/Y') ?: '-' }}</strong></div>
                    <div><span>الموضوع</span><strong>{{ \Illuminate\Support\Str::limit($document->subject ?: $document->title ?: '-', 70) }}</strong></div>
                    <div><span>الإدارة</span><strong>{{ $document->department?->name ?? '-' }}</strong></div>
                    <div><span>النوع</span><strong>{{ $document->documentType?->name ?? '-' }}</strong></div>
                </div>
                <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary">فتح صفحة الكتاب</a>
            @else
                <h2>بدون كتاب</h2>
                <div class="whatsapp-note-box">هذه الرسالة غير مرتبطة بكتاب محدد.</div>
            @endif
        </aside>
    </div>
</div>
@endsection

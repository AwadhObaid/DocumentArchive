@extends('layouts.app')

@section('title', 'رسالة داخلية جديدة')
@section('page_title', 'رسالة داخلية جديدة')
@section('page_subtitle', 'إرسال رسالة أو إحالة كتاب/مذكرة لمستخدم داخل النظام')

@section('content')
@php
    $selectedDocumentId = old('document_id', $selectedDocument?->id);
    $selectedMemoId = old('memo_id', $selectedMemo?->id);
@endphp

<div class="internal-messages-page">
    <div class="internal-messages-header">
        <div>
            <h1>إرسال رسالة داخلية</h1>
            <p>اختر المستلم ثم اربط كتابًا أو مذكرة أو أرفق ملفات إضافية عند الحاجة.</p>
        </div>
        <a href="{{ route('internal-messages.index') }}" class="btn btn-light">رجوع</a>
    </div>

    @if($selectedDocument)
        <div class="internal-message-linked-box">
            <div>
                <strong>سيتم ربط الرسالة بالكتاب رقم {{ $selectedDocument->reference_number }}</strong>
                <small>{{ $selectedDocument->subject ?: $selectedDocument->title ?: 'بدون موضوع' }}</small>
            </div>
            <a href="{{ route('documents.show', $selectedDocument) }}" class="btn btn-secondary">عرض الكتاب</a>
        </div>
    @endif

    @if($selectedMemo)
        <div class="internal-message-linked-box">
            <div>
                <strong>سيتم ربط الرسالة بالمذكرة رقم {{ $selectedMemo->memo_number }}</strong>
                <small>{{ $selectedMemo->subject ?: 'بدون موضوع' }}</small>
            </div>
            <a href="{{ route('memos.show', $selectedMemo) }}" class="btn btn-secondary">عرض المذكرة</a>
        </div>
    @endif

    <form method="POST" action="{{ route('internal-messages.store') }}" enctype="multipart/form-data" class="card">
        @csrf

        <div class="internal-message-compose-grid">
            <div class="form-group">
                <label for="receiver_id">المستلم <span class="text-danger">*</span></label>
                <select id="receiver_id" name="receiver_id" required>
                    <option value="">اختر المستلم</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((string) old('receiver_id') === (string) $user->id)>{{ $user->name }} @if($user->username) - {{ $user->username }} @endif</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="subject">عنوان الرسالة <span class="text-danger">*</span></label>
                <input id="subject" type="text" name="subject" required maxlength="255" value="{{ old('subject', $selectedDocument ? 'إحالة كتاب رقم ' . $selectedDocument->reference_number : ($selectedMemo ? 'إحالة مذكرة رقم ' . $selectedMemo->memo_number : '')) }}">
            </div>

            <div class="form-group">
                <label for="document_id">ربط كتاب</label>
                <select id="document_id" name="document_id">
                    <option value="">بدون كتاب</option>
                    @foreach($documents as $document)
                        <option value="{{ $document->id }}" @selected((string) $selectedDocumentId === (string) $document->id)>{{ $document->reference_number }} - {{ \Illuminate\Support\Str::limit($document->subject ?: $document->title ?: 'بدون موضوع', 70) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="memo_id">ربط مذكرة</label>
                <select id="memo_id" name="memo_id">
                    <option value="">بدون مذكرة</option>
                    @foreach($memos as $memo)
                        <option value="{{ $memo->id }}" @selected((string) $selectedMemoId === (string) $memo->id)>{{ $memo->memo_number }} - {{ \Illuminate\Support\Str::limit($memo->subject ?: 'بدون موضوع', 70) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group full">
                <label for="body">نص الرسالة</label>
                <textarea id="body" name="body" rows="6" placeholder="اكتب الملاحظات أو المطلوب من المستلم">{{ old('body') }}</textarea>
            </div>

            <div class="form-group full">
                <label for="attachments">مرفقات إضافية اختيارية</label>
                <input id="attachments" type="file" name="attachments[]" multiple>
                <small class="text-muted">يمكن ربط الكتاب أو المذكرة بدون إعادة رفع ملفاتها. المرفقات هنا للملفات الإضافية فقط.</small>
            </div>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;">
            <button type="submit" class="btn btn-primary">إرسال الرسالة</button>
            <a href="{{ route('internal-messages.index') }}" class="btn btn-light">إلغاء</a>
        </div>
    </form>
</div>
@endsection

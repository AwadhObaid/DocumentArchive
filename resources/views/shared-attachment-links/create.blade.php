@extends('layouts.app')

@section('title', 'إنشاء رابط مشاركة')
@section('page_title', 'إنشاء رابط مشاركة')
@section('page_subtitle', 'اختر كتابًا ومرفقاته لإنشاء رابط آمن مؤقت.')

@section('content')
@php
    $selectedDocumentId = old('document_id', $document?->id);
    $selectedAttachments = array_map('strval', (array) old('attachment_ids', $document?->attachments?->pluck('id')->map(fn ($id) => (string) $id)->all() ?? []));
@endphp

<div class="share-page">
    <div class="share-page-header">
        <div>
            <h1>إنشاء رابط مشاركة</h1>
            <p>الرابط يمكن مشاركته عبر واتساب أو البريد، ويعمل بدون تسجيل دخول إلى أن تنتهي صلاحيته.</p>
        </div>
        <div class="share-actions">
            <a href="{{ route('shared-attachment-links.index') }}" class="btn btn-light">سجل الروابط</a>
            @if($document)
                <a href="{{ route('documents.show', $document) }}" class="btn btn-secondary">عرض الكتاب</a>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('shared-attachment-links.store') }}" class="share-form-card">
        @csrf

        <div class="share-form-grid">
            <div class="form-group full">
                <label>اختيار كتاب <span class="required">*</span></label>
                <select name="document_id" onchange="if(this.value){ window.location='{{ route('shared-attachment-links.create') }}?document_id=' + this.value; } else { window.location='{{ route('shared-attachment-links.create') }}'; }" required>
                    <option value="">اختر كتابًا</option>
                    @foreach($documents as $doc)
                        <option value="{{ $doc->id }}" @selected((int) $selectedDocumentId === (int) $doc->id)>
                            {{ $doc->reference_number }} - {{ \Illuminate\Support\Str::limit($doc->subject ?: $doc->title ?: 'بدون موضوع', 90) }} — {{ $doc->attachments_count }} مرفق
                        </option>
                    @endforeach
                </select>
                @error('document_id')<small class="field-error">{{ $message }}</small>@enderror
            </div>

            <div class="form-group">
                <label>مدة صلاحية الرابط</label>
                <select name="expires_in">
                    <option value="1h" @selected(old('expires_in') === '1h')>ساعة واحدة</option>
                    <option value="3h" @selected(old('expires_in') === '3h')>3 ساعات</option>
                    <option value="12h" @selected(old('expires_in') === '12h')>12 ساعة</option>
                    <option value="24h" @selected(old('expires_in', '24h') === '24h')>24 ساعة</option>
                    <option value="3d" @selected(old('expires_in') === '3d')>3 أيام</option>
                    <option value="7d" @selected(old('expires_in') === '7d')>7 أيام</option>
                </select>
            </div>

            <div class="form-group">
                <label>حد التحميلات</label>
                <input type="number" name="max_downloads" min="1" max="1000" value="{{ old('max_downloads') }}" placeholder="اختياري">
                <small class="share-help">اتركه فارغًا لعدم تحديد حد.</small>
            </div>

            <div class="form-group">
                <label>كلمة مرور للرابط</label>
                <input type="text" name="password" value="{{ old('password') }}" placeholder="اختياري">
                <small class="share-help">استخدمها عند مشاركة مرفقات حساسة.</small>
            </div>

            <div class="form-group full">
                <label>ملاحظات داخلية</label>
                <textarea name="notes" rows="3" placeholder="اختياري، لا يظهر للمستلم الخارجي">{{ old('notes') }}</textarea>
            </div>
        </div>

        @if($document)
            <div class="share-document-box">
                <h2>مرفقات الكتاب رقم {{ $document->reference_number }}</h2>
                @if($document->attachments->count())
                    <div class="share-attachments-list">
                        @foreach($document->attachments as $attachment)
                            @php
                                $exists = method_exists($attachment, 'existsOnDisk') ? $attachment->existsOnDisk() : false;
                                $fileName = $attachment->original_name ?: $attachment->file_name;
                            @endphp
                            <label class="share-attachment-item">
                                <input type="checkbox" name="attachment_ids[]" value="{{ $attachment->id }}" @checked(in_array((string) $attachment->id, $selectedAttachments, true)) @disabled(!$exists)>
                                <span>
                                    <strong>{{ $fileName }}</strong>
                                    <small>{{ $attachment->file_size_for_humans }} — {{ $exists ? 'موجود على التخزين' : 'مفقود من التخزين' }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('attachment_ids')<small class="field-error">{{ $message }}</small>@enderror
                @else
                    <div class="share-note-box">هذا الكتاب لا يحتوي على مرفقات.</div>
                @endif
            </div>
        @else
            <div class="share-note-box">اختر كتابًا أولًا حتى تظهر مرفقاته.</div>
        @endif

        <div class="share-actions-row">
            <button type="submit" class="btn btn-primary" @disabled(!$document || !$document->attachments->count())>إنشاء الرابط</button>
            <a href="{{ route('shared-attachment-links.index') }}" class="btn btn-light">إلغاء</a>
        </div>
    </form>
</div>
@endsection

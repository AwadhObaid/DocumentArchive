@extends('layouts.app')

@section('title', 'صندوق الماسح')
@section('page_title', 'صندوق الماسح الضوئي')
@section('page_subtitle', 'ربط الملفات الممسوحة ضوئياً بالكتب دون التحكم المباشر بالماسح من المتصفح')

@section('content')
<div class="v75v80-page scanner-inbox-page">
    @if(!empty($scannerError))
        <div class="alert-error">
            <strong>تعذر قراءة صندوق الماسح:</strong> {{ $scannerError }}
        </div>
    @endif

    <div class="v75v80-hero">
        <div>
            <h2>Scanner Workflow</h2>
            <p>اجعل برنامج المسح مثل Windows Scan أو NAPS2 يحفظ الملفات داخل صندوق الماسح، ثم اربطها من هنا بالكتاب المطلوب.</p>
        </div>
        <div class="v75v80-path-box">
            <strong>المسار الحالي</strong>
            <span class="v75v80-ltr">{{ $inboxPath }}</span>
        </div>
    </div>

    <div class="v75v80-card">
        <h3>إعداد مسار صندوق الماسح</h3>
        <form method="POST" action="{{ route('scanner-inbox.update-path') }}" class="v75v80-inline-form">
            @csrf
            <input type="text" name="scanner_inbox_path" value="{{ old('scanner_inbox_path', $inboxPath) }}" dir="ltr">
            <button class="btn btn-primary" type="submit">حفظ المسار</button>
        </form>
        <small>مثال مناسب على السيرفر: D:\DocumentArchiveScannerInbox</small>
    </div>

    <div class="v75v80-card">
        <h3>البحث عن الكتاب المراد ربط الملفات به</h3>
        <form method="GET" action="{{ route('scanner-inbox.index') }}" class="v75v80-inline-form">
            <input type="text" name="q" value="{{ $q }}" placeholder="رقم الكتاب / العنوان / البوليصة">
            <button class="btn btn-secondary" type="submit">بحث</button>
        </form>
    </div>

    <div class="v75v80-card">
        <h3>الملفات الموجودة في صندوق الماسح</h3>
        <div class="scanner-inbox-note">
            الملفات المدعومة: PDF, JPG, PNG, TIFF, BMP, WEBP. إذا لم يظهر الملف، تأكد أنه محفوظ مباشرة داخل مجلد الصندوق وليس داخل مجلد فرعي.
        </div>
        <div class="table-responsive">
            <table class="v75v80-table scanner-table">
                <thead>
                    <tr>
                        <th>الملف</th>
                        <th>الحجم</th>
                        <th>آخر تعديل</th>
                        <th>الكتاب</th>
                        <th>خيارات</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($files as $file)
                        <tr>
                            <td class="v75v80-ltr">{{ $file['name'] }}</td>
                            <td>{{ app(\App\Services\ScannerInboxService::class)->formatBytes((int) $file['size']) }}</td>
                            <td>{{ date('Y-m-d H:i', $file['modified_at']) }}</td>
                            <td>
                                <form method="POST" action="{{ route('scanner-inbox.attach') }}" class="scanner-attach-form">
                                    @csrf
                                    <input type="hidden" name="file" value="{{ $file['name'] }}">
                                    <select name="document_id" required>
                                        <option value="">اختر الكتاب</option>
                                        @foreach($documents as $document)
                                            <option value="{{ $document->id }}">
                                                {{ $document->reference_number }} - {{ $document->title ?: $document->subject }}
                                            </option>
                                        @endforeach
                                    </select>
                            </td>
                            <td>
                                    <label class="v75v80-check"><input type="checkbox" name="make_main" value="1" checked> مرفق رئيسي</label>
                                    <label class="v75v80-check"><input type="checkbox" name="keep_source" value="1" checked> إبقاء نسخة بالصندوق</label>
                            </td>
                            <td>
                                    <button class="btn btn-primary btn-sm" type="submit">ربط</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">لا توجد ملفات ممسوحة حالياً في صندوق الماسح.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

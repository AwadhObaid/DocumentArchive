@extends('layouts.app')

@section('title', 'عرض الكتاب')

@section('content')
@php
    $value = function ($row, string $key, $default = '-') {
        $v = data_get($row, $key);
        return ($v === null || $v === '') ? $default : $v;
    };

    $docId = $value($document ?? null, 'id', null);
    $attachmentsList = collect(data_get($document ?? null, 'attachments', $attachments ?? []));

    $currentUser = auth()->user();
    $canPreviewAttachment = $currentUser && method_exists($currentUser, 'hasPermission')
        && ($currentUser->hasPermission('documents.view') || $currentUser->hasPermission('attachments.preview'));
    $canDownloadAttachment = $currentUser && method_exists($currentUser, 'hasPermission')
        && $currentUser->hasPermission('attachments.download');
    $canSendEmail = $currentUser && method_exists($currentUser, 'hasPermission')
        && $currentUser->hasPermission('emails.send');
    $canSendWhatsapp = $currentUser && method_exists($currentUser, 'hasPermission')
        && $currentUser->hasPermission('whatsapp.send');
    $canShareAttachments = $currentUser && method_exists($currentUser, 'hasPermission')
        && $currentUser->hasPermission('attachment_shares.create');
    $canSendInternal = $currentUser && method_exists($currentUser, 'hasPermission')
        && $currentUser->hasPermission('internal_messages.send');

    $arabicDocumentValue = function (string $field, $raw) {
        if ($raw === null || $raw === '') {
            return '-';
        }

        $display = trim((string) $raw);
        if ($display === '-' || $display === '') {
            return $display === '' ? '-' : $display;
        }

        $key = strtolower(trim($display));

        $maps = [
            'status' => [
                'active' => 'نشط',
                'inactive' => 'غير نشط',
                'enabled' => 'مفعل',
                'disabled' => 'غير مفعل',
                'draft' => 'مسودة',
                'pending' => 'قيد المتابعة',
                'completed' => 'مكتمل',
                'done' => 'مكتمل',
                'cancelled' => 'ملغى',
                'canceled' => 'ملغى',
                'archived' => 'مؤرشف',
                'deleted' => 'محذوف',
            ],
            'confidentiality' => [
                'normal' => 'عادي',
                'public' => 'عام',
                'internal' => 'داخلي',
                'confidential' => 'سري',
                'secret' => 'سري',
                'very_confidential' => 'سري جداً',
                'very confidential' => 'سري جداً',
                'top_secret' => 'سري للغاية',
                'top secret' => 'سري للغاية',
                'very_secret' => 'سري جداً',
                'very secret' => 'سري جداً',
            ],
            'priority' => [
                'normal' => 'عادي',
                'low' => 'منخفض',
                'medium' => 'متوسط',
                'high' => 'عالي',
                'urgent' => 'عاجل',
                'critical' => 'حرج',
            ],
        ];

        return $maps[$field][$key] ?? $display;
    };

    $attachmentExists = function ($attachment): bool {
        try {
            if (is_object($attachment) && method_exists($attachment, 'existsOnDisk')) {
                return $attachment->existsOnDisk();
            }

            $disk = data_get($attachment, 'disk', 'local') ?: 'local';
            $path = data_get($attachment, 'file_path');
            return $path ? \Illuminate\Support\Facades\Storage::disk($disk)->exists($path) : false;
        } catch (\Throwable $exception) {
            return false;
        }
    };

    $attachmentSize = function ($attachment): string {
        $human = data_get($attachment, 'file_size_for_humans') ?: data_get($attachment, 'size_human');
        if ($human) {
            return (string) $human;
        }

        $bytes = (int) data_get($attachment, 'file_size', 0);
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' Bytes';
    };

    $attachmentTypeLabel = function ($attachment): string {
        $extension = strtolower((string) (data_get($attachment, 'extension') ?: pathinfo((string) data_get($attachment, 'original_name', ''), PATHINFO_EXTENSION)));
        $mime = strtolower((string) data_get($attachment, 'mime_type', ''));

        if ($extension === 'pdf' || str_contains($mime, 'pdf')) {
            return 'PDF';
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true) || str_starts_with($mime, 'image/')) {
            return 'صورة';
        }
        if (in_array($extension, ['doc', 'docx'], true)) {
            return 'Word';
        }
        if (in_array($extension, ['xls', 'xlsx'], true)) {
            return 'Excel';
        }

        return strtoupper($extension ?: 'ملف');
    };
@endphp

<div class="page-header">
    <div>
        <h1>عرض الكتاب</h1>
        <p>بيانات الكتاب، البوالص، المرفقات، وسجل الحركة.</p>
    </div>
    <div class="page-actions no-print" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <a href="{{ url('/documents') }}" class="btn btn-light">رجوع</a>
        @if($docId)
            <a href="{{ url('/documents/'.$docId.'/edit') }}" class="btn btn-primary">تعديل</a>
            <a href="{{ url('/documents/'.$docId.'/activity') }}" class="btn btn-info">سجل الحركة</a>
            <a href="{{ url('/documents/'.$docId.'/print-reference') }}" class="btn btn-warning">طباعة رقم الكتاب</a>
            @if($canSendEmail)
                <a href="{{ route('documents.email.compose', $document) }}" class="btn btn-info">إرسال بالبريد</a>
            @endif
            @if($canSendWhatsapp)
                <a href="{{ route('documents.whatsapp.compose', $document) }}" class="btn btn-success">إرسال واتساب</a>
            @endif
            @if($canShareAttachments && $attachmentsList->count())
                <a href="{{ route('documents.shared-attachments.create', $document) }}" class="btn btn-secondary">رابط مرفقات آمن</a>
            @endif
            @if($canSendInternal)
                <a href="{{ route('documents.internal-message.create', $document) }}" class="btn btn-primary">إرسال داخلي</a>
            @endif
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>رقم الكتاب: {{ $value($document ?? null, 'reference_number') }}</h2>
    </div>
    <div class="table-responsive">
        <table class="table details-table">
            <tbody>
                <tr><th>تاريخ الكتاب</th><td>{{ optional($value($document ?? null, 'reference_date', null))->format('Y-m-d') ?? $value($document ?? null, 'reference_date') }}</td></tr>
                <tr><th>موضوع الكتاب من القائمة</th><td>{{ $document?->bookSubject?->name ?: '-' }}</td></tr>
                <tr><th>موضوع الكتاب التفصيلي</th><td>{{ $value($document ?? null, 'subject') }}</td></tr>
                <tr><th>عنوان الكتاب</th><td>{{ $value($document ?? null, 'title') }}</td></tr>
                <tr><th>البوليصة الرئيسية</th><td>{{ $value($document ?? null, 'main_policy_number') }}</td></tr>
                <tr><th>البوليصة الفرعية</th><td>{{ $value($document ?? null, 'sub_policy_number') }}</td></tr>
                <tr><th>الإدارة</th><td>{{ $value($document ?? null, 'department.name', $value($document ?? null, 'department_name')) }}</td></tr>
                <tr><th>نوع الكتاب</th><td>{{ $value($document ?? null, 'documentType.name', $value($document ?? null, 'document_type_name')) }}</td></tr>
                <tr><th>المرسل</th><td>{{ $value($document ?? null, 'sender') }}</td></tr>
                <tr><th>المستلم</th><td>{{ $value($document ?? null, 'recipient') }}</td></tr>
                <tr><th>الحالة</th><td>{{ $arabicDocumentValue('status', $value($document ?? null, 'status')) }}</td></tr>
                <tr><th>درجة السرية</th><td>{{ $arabicDocumentValue('confidentiality', $value($document ?? null, 'confidentiality')) }}</td></tr>
                <tr><th>الأولوية</th><td>{{ $arabicDocumentValue('priority', $value($document ?? null, 'priority')) }}</td></tr>
                <tr><th>الوصف</th><td>{{ $value($document ?? null, 'description') }}</td></tr>
                <tr><th>الملاحظات</th><td>{{ $value($document ?? null, 'notes') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4 document-attachments-card">
    <div class="card-header">
        <h2>المرفقات</h2>
    </div>

    @if($attachmentsList->count())
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>اسم الملف</th>
                        <th>النوع</th>
                        <th>الحجم</th>
                        <th>حالة التخزين</th>
                        <th class="no-print">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attachmentsList as $attachment)
                        @php
                            $attId = data_get($attachment, 'id');
                            $exists = $attachmentExists($attachment);
                            $fileName = data_get($attachment, 'original_name', data_get($attachment, 'file_name', 'مرفق'));
                        @endphp
                        <tr>
                            <td style="min-width:240px;white-space:normal;word-break:break-word;">{{ $fileName }}</td>
                            <td>{{ $attachmentTypeLabel($attachment) }}</td>
                            <td>{{ $attachmentSize($attachment) }}</td>
                            <td>
                                @if($exists)
                                    <span class="badge" style="background:#dcfce7;color:#166534;border:1px solid #86efac;">موجود</span>
                                @else
                                    <span class="badge" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;">مفقود</span>
                                @endif
                            </td>
                            <td class="no-print">
                                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                    @if($attId && $exists && $canPreviewAttachment)
                                        <a class="btn btn-sm btn-light" href="{{ url('/attachments/'.$attId.'/preview') }}">معاينة</a>
                                    @endif

                                    @if($attId && $exists && $canDownloadAttachment)
                                        <a class="btn btn-sm btn-primary" href="{{ url('/attachments/'.$attId.'/download') }}">تنزيل</a>
                                    @endif

                                    @if(!$exists)
                                        <span class="text-muted" style="font-size:12px;">الملف غير موجود على التخزين</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="empty-state">لا توجد مرفقات.</p>
    @endif
</div>
@endsection

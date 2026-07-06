@extends('layouts.app')

@php
    $attachmentsList = collect($memo->attachments ?? []);
    $currentUser = auth()->user();
    $canUseMemoAttachments = $currentUser && method_exists($currentUser, 'hasPermission')
        && $currentUser->hasPermission('memos.attachments');
    $canEditMemo = $currentUser && method_exists($currentUser, 'hasPermission')
        && $currentUser->hasPermission('memos.edit');
    $canSendInternalMemo = $currentUser && method_exists($currentUser, 'hasPermission')
        && $currentUser->hasPermission('internal_messages.send');

    $memoValue = function ($value, string $fallback = '-') {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return $value;
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
        $human = data_get($attachment, 'file_size_for_humans');
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

@section('title', 'المذكرة ' . $memo->memo_number)
@section('page_title', 'عرض مذكرة')
@section('page_subtitle', 'بيانات المذكرة ومرفقاتها المؤرشفة')

@section('content')
<style>
    .memo-number-large {
        direction: ltr;
        display: inline-flex;
        padding: 8px 14px;
        border-radius: 14px;
        background: rgba(37, 99, 235, .16);
        border: 1px solid rgba(59, 130, 246, .28);
        font-size: 24px;
        font-weight: 950;
        letter-spacing: .5px;
    }

    .memo-details-table th {
        width: 190px;
        color: var(--muted);
        white-space: nowrap;
    }

    .memo-details-table td {
        white-space: normal;
        word-break: break-word;
        line-height: 1.9;
    }
</style>

<div class="page-header">
    <div>
        <h1>عرض المذكرة</h1>
        <p>بيانات المذكرة ومرفقاتها بنفس طريقة عرض الكتب في النظام.</p>
    </div>
    <div class="page-actions no-print" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <a href="{{ route('memos.index') }}" class="btn btn-light">رجوع</a>
        @if($canEditMemo)
            <a href="{{ route('memos.edit', $memo) }}" class="btn btn-primary">تعديل</a>
        @endif
        @if($canSendInternalMemo)
            <a href="{{ route('memos.internal-message.create', $memo) }}" class="btn btn-info">إرسال داخلي</a>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>رقم المذكرة: <span class="memo-number-large">{{ $memo->memo_number }}</span></h2>
    </div>
    <div class="table-responsive">
        <table class="table details-table memo-details-table">
            <tbody>
                <tr><th>رقم المذكرة</th><td><strong dir="ltr">{{ $memo->memo_number }}</strong></td></tr>
                <tr><th>تاريخ المذكرة</th><td>{{ $memo->formatted_date }}</td></tr>
                <tr><th>موضوع المذكرة</th><td>{{ $memoValue($memo->subject) }}</td></tr>
                <tr><th>الإدارة</th><td>{{ $memoValue($memo->department?->name) }}</td></tr>
                <tr><th>الواردة من</th><td>{{ $memoValue($memo->sender) }}</td></tr>
                <tr><th>موجهة إلى</th><td>{{ $memoValue($memo->receiver) }}</td></tr>
                <tr><th>الحالة</th><td>{{ $memo->status_name }}</td></tr>
                <tr><th>التفاصيل</th><td>{!! nl2br(e($memoValue($memo->description))) !!}</td></tr>
                <tr><th>الملاحظات</th><td>{!! nl2br(e($memoValue($memo->notes))) !!}</td></tr>
                <tr><th>أضيفت بواسطة</th><td>{{ $memoValue($memo->creator?->name) }}</td></tr>
                <tr><th>تاريخ الإضافة</th><td>{{ $memo->created_at?->format('Y-m-d H:i') ?: '-' }}</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4 memo-attachments-card">
    <div class="card-header">
        <h2>مرفقات المذكرة</h2>
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
                                    @if($exists && $canUseMemoAttachments)
                                        <a class="btn btn-sm btn-light" href="{{ route('memos.attachments.preview', [$memo, $attachment]) }}">معاينة</a>
                                        <a class="btn btn-sm btn-primary" href="{{ route('memos.attachments.download', [$memo, $attachment]) }}">تنزيل</a>
                                    @endif

                                    @if(!$exists)
                                        <span class="text-muted" style="font-size:12px;">الملف غير موجود على التخزين</span>
                                    @elseif(!$canUseMemoAttachments)
                                        <span class="text-muted" style="font-size:12px;">لا توجد صلاحية لعرض المرفقات</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="empty-state">لا توجد مرفقات لهذه المذكرة.</p>
    @endif
</div>
@endsection

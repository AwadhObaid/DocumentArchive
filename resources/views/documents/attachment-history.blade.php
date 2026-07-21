@extends('layouts.app')

@section('title', 'سجل مرفقات الكتاب')

@section('content')
@php
    $exists = function ($attachment) use ($pathService): bool {
        try {
            return $pathService->attachmentExists($attachment);
        } catch (\Throwable $exception) {
            return false;
        }
    };

    $size = function ($bytes): string {
        $bytes = (int) $bytes;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' Bytes';
    };
@endphp

<style>
    .attachment-history-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
    }

    .attachment-history-dialog {
        width: min(570px, calc(100vw - 28px));
        border: 1px solid #334155;
        border-radius: 16px;
        padding: 0;
        color: #f8fafc;
        background: #101b2d;
        box-shadow: 0 24px 70px rgba(0, 0, 0, .5);
    }

    .attachment-history-dialog::backdrop {
        background: rgba(2, 6, 23, .76);
        backdrop-filter: blur(3px);
    }

    .attachment-history-dialog-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        padding: 16px 18px;
        border-bottom: 1px solid #334155;
    }

    .attachment-history-dialog-body {
        padding: 18px;
    }

    .attachment-history-dialog-body label {
        display: block;
        margin: 14px 0 7px;
        font-weight: 700;
    }

    .attachment-history-dialog-body textarea,
    .attachment-history-dialog-body input[type="text"] {
        width: 100%;
        border: 1px solid #475569;
        border-radius: 10px;
        padding: 10px 12px;
        color: #f8fafc;
        background: #0b1525;
    }

    .attachment-history-dialog-body textarea {
        min-height: 100px;
        resize: vertical;
    }

    .attachment-history-danger-note {
        padding: 12px 13px;
        border: 1px solid rgba(239, 68, 68, .48);
        border-radius: 11px;
        color: #fecaca;
        background: rgba(127, 29, 29, .22);
        line-height: 1.75;
        font-size: 12px;
    }

    .attachment-history-dialog-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 17px;
    }
</style>

<div class="page-header" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
    <div>
        <h1>سجل مرفقات الكتاب</h1>
        <p>
            رقم الكتاب:
            <strong>{{ $document->reference_number }}</strong>
            — يعرض الإصدارات الحالية والمحذوفة والمستبدلة.
        </p>
    </div>

    <a class="btn btn-light" href="{{ route('documents.show', $document) }}">
        العودة إلى عرض الكتاب
    </a>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h2>الإصدارات والمرفقات</h2>
    </div>

    @if($attachments->count())
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>الإصدار</th>
                        <th>اسم الملف</th>
                        <th>الحالة</th>
                        <th>وجود الملف</th>
                        <th>الحجم</th>
                        <th>أضيف بواسطة</th>
                        <th>تاريخ الإضافة</th>
                        <th>سبب الحذف / الاستبدال</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($attachments as $attachment)
                        @php
                            $fileExists = $exists($attachment);
                            $isReplaced = (bool) $attachment->replaced_by_attachment_id;
                            $isDeleted = $attachment->trashed();
                            $status = $isReplaced
                                ? 'إصدار مستبدل'
                                : ($isDeleted ? 'محذوف ظاهريًا' : 'حالي');
                            $reason = $attachment->replacement_reason
                                ?: $attachment->deletion_reason
                                ?: '-';
                        @endphp

                        <tr>
                            <td>#{{ $attachment->version_no }}</td>
                            <td style="min-width:240px;white-space:normal;word-break:break-word;">
                                {{ $attachment->original_name ?: $attachment->file_name }}
                                @if($attachment->is_main && ! $isDeleted)
                                    <span class="badge" style="margin-inline-start:6px;">رئيسي</span>
                                @endif
                            </td>
                            <td>
                                @if($isReplaced)
                                    <span class="badge" style="background:#fef3c7;color:#92400e;">{{ $status }}</span>
                                @elseif($isDeleted)
                                    <span class="badge" style="background:#fee2e2;color:#991b1b;">{{ $status }}</span>
                                @else
                                    <span class="badge" style="background:#dcfce7;color:#166534;">{{ $status }}</span>
                                @endif
                            </td>
                            <td>
                                @if($fileExists)
                                    <span style="color:#22c55e;font-weight:700;">موجود</span>
                                @else
                                    <span style="color:#ef4444;font-weight:700;">مفقود</span>
                                @endif
                            </td>
                            <td>{{ $size($attachment->file_size) }}</td>
                            <td>{{ $attachment->uploader?->name ?: $attachment->uploader?->username ?: '-' }}</td>
                            <td>{{ optional($attachment->created_at)->format('Y-m-d H:i') ?: '-' }}</td>
                            <td style="min-width:220px;white-space:normal;">
                                {{ $reason }}

                                @if($attachment->deletedBy)
                                    <div style="margin-top:5px;color:#94a3b8;font-size:11px;">
                                        بواسطة:
                                        {{ $attachment->deletedBy->name ?: $attachment->deletedBy->username }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="attachment-history-actions">
                                    @if($canPreview && $fileExists)
                                        <a class="btn btn-sm btn-light"
                                           href="{{ route('attachments.archived-preview', [$document, $attachment->id]) }}"
                                           target="_blank"
                                           rel="noopener">
                                            معاينة
                                        </a>
                                    @endif

                                    @if($canManage && $fileExists)
                                        <a class="btn btn-sm btn-primary"
                                           href="{{ route('attachments.archived-download', [$document, $attachment->id]) }}">
                                            تنزيل النسخة
                                        </a>
                                    @endif

                                    @if($canManage && $isDeleted && ! $isReplaced)
                                        <form method="POST"
                                              action="{{ route('attachments.restore', [$document, $attachment->id]) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-success"
                                                    type="submit"
                                                    onclick="return confirm('هل تريد استعادة هذا المرفق؟')">
                                                استعادة
                                            </button>
                                        </form>
                                    @endif

                                    @if($attachment->replacedByAttachment)
                                        <span class="text-muted" style="font-size:11px;">
                                            استُبدل بالإصدار
                                            #{{ $attachment->replacedByAttachment->version_no }}
                                        </span>
                                    @endif

                                    @if($canPermanentlyDelete)
                                        <button class="btn btn-sm btn-danger"
                                                type="button"
                                                onclick="document.getElementById('force-delete-attachment-{{ $attachment->id }}').showModal()">
                                            حذف نهائي
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($canPermanentlyDelete)
            @foreach($attachments as $attachment)
                <dialog class="attachment-history-dialog no-print"
                        id="force-delete-attachment-{{ $attachment->id }}">
                    <div class="attachment-history-dialog-head">
                        <strong>حذف المرفق نهائيًا</strong>
                        <button class="btn btn-sm btn-light"
                                type="button"
                                onclick="this.closest('dialog').close()">
                            إغلاق
                        </button>
                    </div>

                    <form method="POST"
                          action="{{ route('attachments.force-delete', [$document, $attachment->id]) }}"
                          class="attachment-history-dialog-body">
                        @csrf
                        @method('DELETE')

                        <div class="attachment-history-danger-note">
                            هذه العملية غير قابلة للاستعادة. سيتم حذف سجل المرفق
                            نهائيًا، كما سيُحذف الملف من وسيط التخزين عندما لا يكون
                            مستخدمًا بواسطة سجل مرفق آخر.
                        </div>

                        <label for="force-delete-reason-{{ $attachment->id }}">
                            سبب الحذف النهائي
                        </label>
                        <textarea id="force-delete-reason-{{ $attachment->id }}"
                                  name="permanent_deletion_reason"
                                  minlength="5"
                                  maxlength="1000"
                                  required
                                  placeholder="اكتب سبب الحذف النهائي للمرفق."></textarea>

                        <label for="force-delete-confirmation-{{ $attachment->id }}">
                            للتأكيد اكتب: حذف نهائي
                        </label>
                        <input id="force-delete-confirmation-{{ $attachment->id }}"
                               type="text"
                               name="confirmation_text"
                               autocomplete="off"
                               required
                               placeholder="حذف نهائي">

                        <div class="attachment-history-dialog-actions">
                            <button class="btn btn-light"
                                    type="button"
                                    onclick="this.closest('dialog').close()">
                                إلغاء
                            </button>
                            <button class="btn btn-danger" type="submit">
                                تنفيذ الحذف النهائي
                            </button>
                        </div>
                    </form>
                </dialog>
            @endforeach
        @endif
    @else
        <p class="empty-state">لا يوجد سجل مرفقات لهذا الكتاب.</p>
    @endif
</div>
@endsection

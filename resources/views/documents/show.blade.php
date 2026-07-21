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
    $canEditDocument = $currentUser && method_exists($currentUser, 'hasPermission')
        && $currentUser->hasPermission('documents.edit')
        && (! method_exists($document, 'canBeModifiedBy') || $document->canBeModifiedBy($currentUser));

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



    $attachmentIndexBadge = function ($attachment): string {
        $index = data_get($attachment, 'textIndex');
        if (! $index) {
            return '<span class="pdf-status-pill pdf-status-muted">غير مفهرس</span>';
        }

        $class = e(data_get($index, 'status_class', 'muted'));
        $name = e(data_get($index, 'status_name', 'غير مفهرس'));
        $length = (int) data_get($index, 'text_length', 0);
        $extra = $length > 0 ? '<div style="color:#94a3b8;font-size:12px;margin-top:5px;">' . number_format($length) . ' حرف</div>' : '';

        return '<span class="pdf-status-pill pdf-status-' . $class . '">' . $name . '</span>' . $extra;
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
            @if($canEditDocument)
                <a href="{{ url('/documents/'.$docId.'/edit') }}" class="btn btn-primary">تعديل</a>
            @endif
            <a href="{{ url('/documents/'.$docId.'/activity') }}" class="btn btn-info">سجل الحركة</a>
                        {{-- REFERENCE_PRINT_CHOICE_V81_9:start --}}
            <div class="da-reference-print-control"
                 data-preview-url="{{ url('/documents/'.$docId.'/print-reference') }}?mode=preview"
                 data-direct-url="{{ url('/documents/'.$docId.'/print-reference') }}?mode=direct">
                <a class="btn btn-warning da-reference-print-primary"
                   href="{{ url('/documents/'.$docId.'/print-reference') }}?mode=preview"
                   target="_blank"
                   rel="noopener">
                    <span>طباعة رقم الكتاب</span>
                    <small class="da-reference-print-mode-label">معاينة</small>
                </a>

                <button class="btn btn-warning da-reference-print-toggle"
                        type="button"
                        aria-label="خيارات طباعة رقم الكتاب"
                        aria-expanded="false">
                    ▾
                </button>

                <div class="da-reference-print-menu" hidden>
                    <a href="{{ url('/documents/'.$docId.'/print-reference') }}?mode=direct"
                       target="_blank"
                       rel="noopener"
                       data-reference-print-mode="direct">
                        <strong>🖨️ طباعة مباشرة</strong>
                        <small>فتح نافذة الطباعة فورًا دون الوقوف في صفحة المعاينة.</small>
                    </a>

                    <a href="{{ url('/documents/'.$docId.'/print-reference') }}?mode=preview"
                       target="_blank"
                       rel="noopener"
                       data-reference-print-mode="preview">
                        <strong>👁️ عرض صفحة الطباعة</strong>
                        <small>مراجعة موضع رقم الكتاب والتاريخ قبل الطباعة.</small>
                    </a>

                    <label class="da-reference-print-remember">
                        <input type="checkbox" class="da-reference-print-remember-input">
                        <span>تذكّر آخر اختيار على هذا الجهاز</span>
                    </label>
                </div>
            </div>

            <style>
                .da-reference-print-control {
                    position: relative;
                    display: inline-flex;
                    direction: rtl;
                    isolation: isolate;
                }

                .da-reference-print-primary {
                    display: inline-flex !important;
                    align-items: center;
                    gap: 7px;
                    border-start-end-radius: 0 !important;
                    border-end-end-radius: 0 !important;
                    white-space: nowrap;
                }

                .da-reference-print-mode-label {
                    padding: 2px 6px;
                    border-radius: 999px;
                    background: rgba(255, 255, 255, 0.2);
                    color: inherit;
                    font-size: 10px;
                    line-height: 1.35;
                }

                .da-reference-print-toggle {
                    min-width: 35px;
                    padding-inline: 10px !important;
                    border-start-start-radius: 0 !important;
                    border-end-start-radius: 0 !important;
                    border-inline-start: 1px solid rgba(255, 255, 255, 0.3) !important;
                }

                .da-reference-print-menu {
                    position: absolute;
                    top: calc(100% + 8px);
                    inset-inline-end: 0;
                    z-index: 1100;
                    width: min(330px, calc(100vw - 32px));
                    overflow: hidden;
                    border: 1px solid var(--border-color, #334155);
                    border-radius: 13px;
                    background: var(--card-bg, #111c2e);
                    box-shadow: 0 18px 45px rgba(0, 0, 0, 0.35);
                }

                .da-reference-print-menu[hidden] {
                    display: none !important;
                }

                .da-reference-print-menu > a {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                    padding: 12px 14px;
                    color: var(--text-color, #f8fafc);
                    text-decoration: none;
                    border-bottom: 1px solid var(--border-color, #334155);
                    background: transparent;
                }

                .da-reference-print-menu > a:hover,
                .da-reference-print-menu > a:focus {
                    background: rgba(59, 130, 246, 0.14);
                    outline: none;
                }

                .da-reference-print-menu > a small {
                    color: var(--text-muted, #94a3b8);
                    font-size: 11px;
                    line-height: 1.6;
                }

                .da-reference-print-remember {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 10px 14px;
                    color: var(--text-muted, #cbd5e1);
                    font-size: 11px;
                    cursor: pointer;
                }

                .da-reference-print-remember input {
                    width: 15px;
                    height: 15px;
                    accent-color: #f59e0b;
                }

                @media (max-width: 640px) {
                    .da-reference-print-primary > span {
                        display: none;
                    }

                    .da-reference-print-mode-label {
                        font-size: 11px;
                    }
                }
            </style>

            <script>
                (function () {
                    var controls = document.querySelectorAll('.da-reference-print-control');

                    if (! controls.length) {
                        return;
                    }

                    var rememberKey = 'documentArchive.referencePrint.remember';
                    var modeKey = 'documentArchive.referencePrint.mode';

                    controls.forEach(function (control) {
                        var primary = control.querySelector('.da-reference-print-primary');
                        var toggle = control.querySelector('.da-reference-print-toggle');
                        var menu = control.querySelector('.da-reference-print-menu');
                        var remember = control.querySelector('.da-reference-print-remember-input');
                        var label = control.querySelector('.da-reference-print-mode-label');
                        var previewUrl = control.getAttribute('data-preview-url');
                        var directUrl = control.getAttribute('data-direct-url');

                        if (! primary || ! toggle || ! menu || ! remember) {
                            return;
                        }

                        var storageAvailable = true;

                        try {
                            remember.checked = window.localStorage.getItem(rememberKey) === '1';
                        } catch (error) {
                            storageAvailable = false;
                            remember.checked = false;
                        }

                        var currentMode = 'preview';

                        if (storageAvailable && remember.checked) {
                            try {
                                currentMode = window.localStorage.getItem(modeKey) === 'direct'
                                    ? 'direct'
                                    : 'preview';
                            } catch (error) {
                                currentMode = 'preview';
                            }
                        }

                        /*
                         * REFERENCE_PRINT_RETURN_V81_9_1
                         *
                         * Open direct printing from a real JavaScript-created
                         * window. Browsers then permit window.close() after the
                         * native print dialog finishes.
                         */
                        function openDirectPrint(url) {
                            var popup = window.open(
                                url,
                                'documentArchiveReferencePrintWindow',
                                'popup=yes,width=1100,height=850,resizable=yes,scrollbars=yes'
                            );

                            if (popup) {
                                popup.focus();
                                return;
                            }

                            // Popup blocked: continue in the current tab.
                            // The direct-print page will return to this book.
                            window.location.href = url;
                        }
                        function applyMode(mode) {
                            currentMode = mode === 'direct' ? 'direct' : 'preview';
                            primary.href = currentMode === 'direct' ? directUrl : previewUrl;
                            primary.setAttribute(
                                'title',
                                currentMode === 'direct'
                                    ? 'طباعة مباشرة'
                                    : 'عرض صفحة الطباعة'
                            );

                            if (label) {
                                label.textContent = currentMode === 'direct'
                                    ? 'مباشرة'
                                    : 'معاينة';
                            }
                        }

                        function closeMenu() {
                            menu.hidden = true;
                            toggle.setAttribute('aria-expanded', 'false');
                        }

                        function saveChoice(mode) {
                            applyMode(mode);

                            if (! storageAvailable) {
                                return;
                            }

                            try {
                                if (remember.checked) {
                                    window.localStorage.setItem(rememberKey, '1');
                                    window.localStorage.setItem(modeKey, currentMode);
                                } else {
                                    window.localStorage.removeItem(rememberKey);
                                    window.localStorage.removeItem(modeKey);
                                }
                            } catch (error) {
                                storageAvailable = false;
                            }
                        }

                        applyMode(currentMode);

                        toggle.addEventListener('click', function (event) {
                            event.preventDefault();
                            event.stopPropagation();

                            var willOpen = menu.hidden;

                            document
                                .querySelectorAll('.da-reference-print-menu:not([hidden])')
                                .forEach(function (openMenu) {
                                    openMenu.hidden = true;
                                });

                            menu.hidden = ! willOpen;
                            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                        });

                        menu.querySelectorAll('[data-reference-print-mode]').forEach(function (link) {
                            link.addEventListener('click', function (event) {
                                var selectedMode = link.getAttribute('data-reference-print-mode');

                                saveChoice(selectedMode);
                                closeMenu();

                                if (selectedMode === 'direct') {
                                    event.preventDefault();
                                    openDirectPrint(link.href);
                                }
                            });
                        });

                        primary.addEventListener('click', function (event) {
                            if (currentMode !== 'direct') {
                                return;
                            }

                            event.preventDefault();
                            openDirectPrint(primary.href);
                        });

                        window.addEventListener('message', function (event) {
                            if (event.origin !== window.location.origin) {
                                return;
                            }

                            if (event.data
                                && event.data.type === 'documentArchive.referencePrint.finished') {
                                window.focus();
                            }
                        });

                        remember.addEventListener('change', function () {
                            if (! storageAvailable) {
                                return;
                            }

                            try {
                                if (remember.checked) {
                                    window.localStorage.setItem(rememberKey, '1');
                                    window.localStorage.setItem(modeKey, currentMode);
                                } else {
                                    window.localStorage.removeItem(rememberKey);
                                    window.localStorage.removeItem(modeKey);
                                }
                            } catch (error) {
                                storageAvailable = false;
                            }
                        });

                        document.addEventListener('click', function (event) {
                            if (! control.contains(event.target)) {
                                closeMenu();
                            }
                        });

                        document.addEventListener('keydown', function (event) {
                            if (event.key === 'Escape') {
                                closeMenu();
                            }
                        });
                    });
                })();
            </script>
            {{-- REFERENCE_PRINT_CHOICE_V81_9:end --}}
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
                <tr><th>شركة / جهة حفظ المرفقات</th><td>{{ $document?->attachment_company_name ?: '-' }}</td></tr>
                <tr><th>نوع عملية حفظ المرفقات</th><td>{{ $document?->attachment_category_name ?: '-' }}</td></tr>
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

@include('partials.workflow-panel', ['record' => $document, 'type' => 'document'])

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
                        <th>مسار الحفظ</th>
                        <th>فهرسة PDF</th>
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
                            <td style="direction:ltr;text-align:left;min-width:220px;white-space:normal;word-break:break-word;">{{ data_get($attachment, 'classification_folder') ?: data_get($attachment, 'file_path', '-') }}</td>
                            <td>{!! $attachmentIndexBadge($attachment) !!}</td>
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

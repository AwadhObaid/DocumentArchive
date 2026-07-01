@extends('layouts.app')

@section('title', 'الكتب')

@section('content')
@php
    /* DOCUMENTS_SEARCH_POLISH_VIEW_START */
    $user = auth()->user();

    $can = function (string $permission) use ($user): bool {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if (method_exists($user, 'hasPermission')) {
            return $user->hasPermission($permission);
        }

        return false;
    };

    $statusLabel = function (?string $status) use ($statusOptions): string {
        return $statusOptions[$status ?? ''] ?? ($status ?: '-');
    };

    $priorityLabel = function (?string $priority) use ($priorityOptions): string {
        return $priorityOptions[$priority ?? ''] ?? ($priority ?: '-');
    };

    $confidentialityLabel = function (?string $confidentiality) use ($confidentialityOptions): string {
        return $confidentialityOptions[$confidentiality ?? ''] ?? ($confidentiality ?: '-');
    };

    $formatDocumentDate = function ($document): string {
        if (!empty($document->formatted_date)) {
            return (string) $document->formatted_date;
        }

        if (!empty($document->reference_date)) {
            try {
                return \Illuminate\Support\Carbon::parse($document->reference_date)->format('d/m/Y');
            } catch (\Throwable $e) {
                return (string) $document->reference_date;
            }
        }

        return '-';
    };

    $formatDeletedAt = function ($document): string {
        if (empty($document->deleted_at)) {
            return '-';
        }

        try {
            return \Illuminate\Support\Carbon::parse($document->deleted_at)->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return (string) $document->deleted_at;
        }
    };

    $selectedSort = request('sort', $defaultSort ?? 'created_at');
    $selectedDirection = request('direction', $direction ?? 'desc');
    $selectedPerPage = (int) request('per_page', $perPage ?? 15);
@endphp

<style>
    .documents-page { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    .documents-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
    .documents-head h1 { margin: 0; font-size: clamp(26px, 3vw, 38px); font-weight: 950; }
    .documents-head p { margin: 7px 0 0; color: #94a3b8; font-weight: 700; line-height: 1.7; }

    .doc-summary-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
    .doc-summary-card { background: rgba(15,23,42,.72); border: 1px solid rgba(148,163,184,.20); border-radius: 18px; padding: 16px; min-width: 0; }
    .doc-summary-card span { display: block; color: #94a3b8; font-size: 12px; font-weight: 900; margin-bottom: 8px; line-height: 1.6; }
    .doc-summary-card strong { display: block; color: #fff; font-size: 26px; font-weight: 950; }
    .doc-summary-card.is-filtered { border-color: rgba(59,130,246,.45); box-shadow: 0 0 0 1px rgba(59,130,246,.08) inset; }

    .advanced-filter-card { background: rgba(15,23,42,.72); border: 1px solid rgba(148,163,184,.20); border-radius: 18px; padding: 16px; overflow: hidden; }
    .filter-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
    .filter-header h2 { margin: 0; color: #fff; font-size: 18px; font-weight: 950; }
    .filter-header p { margin: 5px 0 0; color: #94a3b8; font-weight: 750; line-height: 1.7; }
    .filter-count { display: inline-flex; align-items: center; justify-content: center; padding: 7px 12px; border-radius: 999px; background: rgba(59,130,246,.14); border: 1px solid rgba(59,130,246,.30); color: #dbeafe; font-weight: 950; font-size: 12px; white-space: nowrap; }
    .advanced-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .advanced-filter-grid .form-group { min-width: 0; }
    .advanced-filter-grid label { line-height: 1.7; }
    .advanced-filter-grid input,
    .advanced-filter-grid select { width: 100%; }
    .filter-actions { display: flex; align-items: end; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
    .filter-actions .btn { white-space: nowrap; }

    .quick-filter-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .quick-chip { display: inline-flex; align-items: center; justify-content: center; padding: 8px 11px; border-radius: 999px; border: 1px solid rgba(148,163,184,.22); background: rgba(148,163,184,.09); color: #e2e8f0; font-weight: 900; font-size: 12px; text-decoration: none; }
    .quick-chip.active { background: rgba(59,130,246,.18); border-color: rgba(59,130,246,.42); color: #dbeafe; }

    .documents-table-wrap { overflow-x: auto; }
    .documents-table { width: 100%; border-collapse: collapse; min-width: 1460px; }
    .documents-table th,
    .documents-table td { padding: 12px 10px; border-bottom: 1px solid rgba(148,163,184,.16); text-align: right; vertical-align: middle; }
    .documents-table th { color: #94a3b8; font-size: 12px; font-weight: 950; white-space: nowrap; }
    .documents-table td { color: #f8fafc; font-weight: 750; }

    .document-number-cell strong { display: block; font-size: 14px; font-weight: 950; letter-spacing: .2px; direction: ltr; text-align: right; }
    .document-date-cell { white-space: nowrap; font-weight: 900; }
    .document-subject-cell { min-width: 280px; white-space: normal !important; }
    .document-subject-cell strong { display: block; margin-bottom: 5px; font-weight: 950; line-height: 1.6; }
    .document-subject-cell small { display: block; color: #94a3b8; font-weight: 750; line-height: 1.6; }
    .policy-cell { min-width: 150px; white-space: normal !important; }
    .policy-cell strong { display: block; direction: ltr; text-align: right; font-size: 13px; font-weight: 950; }
    .policy-cell small { color: #94a3b8; font-weight: 750; }

    .pill { display: inline-flex; align-items: center; justify-content: center; padding: 5px 10px; border-radius: 999px; background: rgba(37,99,235,.14); border: 1px solid rgba(37,99,235,.28); font-size: 12px; font-weight: 900; color: #dbeafe; white-space: nowrap; }
    .pill-muted { background: rgba(148,163,184,.10); border-color: rgba(148,163,184,.22); color: #cbd5e1; }
    .pill-warning { background: rgba(245,158,11,.13); border-color: rgba(245,158,11,.28); color: #fde68a; }
    .pill-danger { background: rgba(239,68,68,.13); border-color: rgba(239,68,68,.28); color: #fecaca; }
    .pill-success { background: rgba(34,197,94,.13); border-color: rgba(34,197,94,.28); color: #bbf7d0; }
    .empty-documents { text-align: center; padding: 34px; color: #94a3b8; font-weight: 900; }

    .document-mobile-list { display: none; }
    .document-mobile-card { display: none; }

    .documents-page .pagination svg { width: 18px !important; height: 18px !important; max-width: 18px !important; max-height: 18px !important; }
    .documents-page .pagination nav { width: 100%; }
    .documents-page .pagination .hidden { display: none !important; }

    @media (max-width: 1280px) {
        .doc-summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .advanced-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @media (max-width: 980px) {
        .doc-summary-grid, .advanced-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 760px) {
        .doc-summary-grid, .advanced-filter-grid { grid-template-columns: 1fr; }
        .filter-actions { align-items: stretch; }
        .filter-actions .btn { width: 100%; }
        .quick-filter-row { display: grid; grid-template-columns: 1fr; }

        .documents-table { display: none; }
        .document-mobile-list { display: block; }
        .document-mobile-card { display: block; background: rgba(15,23,42,.72); border: 1px solid rgba(148,163,184,.20); border-radius: 18px; padding: 14px; margin-bottom: 12px; }
        .document-mobile-card h3 { margin: 0 0 10px; color: #fff; font-size: 18px; font-weight: 950; line-height: 1.6; }
        .mobile-info-grid { display: grid; grid-template-columns: 1fr; gap: 8px; margin-bottom: 12px; }
        .mobile-info-item { display: flex; justify-content: space-between; gap: 10px; border-bottom: 1px solid rgba(148,163,184,.13); padding-bottom: 7px; }
        .mobile-info-item span { color: #94a3b8; font-weight: 900; }
        .mobile-info-item strong { color: #f8fafc; font-weight: 950; text-align: left; direction: ltr; }
        .document-mobile-card .actions { justify-content: flex-start; flex-wrap: wrap; }
    }

    /* DOCUMENTS_SEARCH_TABLE_SCROLL_START */
    html,
    body {
        overflow-x: hidden !important;
    }

    .documents-page,
    .documents-page .card {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .documents-page > .card {
        overflow: hidden !important;
    }

    #documentsTableScroll {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        direction: ltr !important;
        box-sizing: border-box !important;
        padding: 0 0 14px 0 !important;
        margin: 0 !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: auto;
        scrollbar-color: rgba(59,130,246,.85) rgba(148,163,184,.18);
    }

    #documentsTableScroll::-webkit-scrollbar { height: 12px; }
    #documentsTableScroll::-webkit-scrollbar-track { background: rgba(148,163,184,.18); border-radius: 999px; }
    #documentsTableScroll::-webkit-scrollbar-thumb { background: rgba(59,130,246,.85); border-radius: 999px; }

    #documentsTableScroll .documents-table {
        display: table !important;
        direction: rtl !important;
        width: max-content !important;
        min-width: 1460px !important;
        max-width: none !important;
        table-layout: auto !important;
        border-collapse: collapse !important;
        margin: 0 !important;
    }

    #documentsTableScroll .documents-table th,
    #documentsTableScroll .documents-table td {
        overflow: hidden !important;
        text-overflow: clip !important;
        white-space: nowrap !important;
        vertical-align: middle !important;
        box-sizing: border-box !important;
    }

    #documentsTableScroll .documents-table th:nth-child(1),
    #documentsTableScroll .documents-table td:nth-child(1) { min-width: 130px !important; }
    #documentsTableScroll .documents-table th:nth-child(2),
    #documentsTableScroll .documents-table td:nth-child(2) { min-width: 120px !important; }
    #documentsTableScroll .documents-table th:nth-child(3),
    #documentsTableScroll .documents-table td:nth-child(3) { min-width: 300px !important; max-width: 380px !important; }
    #documentsTableScroll .documents-table th:nth-child(4),
    #documentsTableScroll .documents-table td:nth-child(4) { min-width: 155px !important; }
    #documentsTableScroll .documents-table th:nth-child(5),
    #documentsTableScroll .documents-table td:nth-child(5) { min-width: 155px !important; }
    #documentsTableScroll .documents-table th:nth-child(6),
    #documentsTableScroll .documents-table td:nth-child(6) { min-width: 135px !important; }
    #documentsTableScroll .documents-table th:nth-child(7),
    #documentsTableScroll .documents-table td:nth-child(7) { min-width: 125px !important; }
    #documentsTableScroll .documents-table th:nth-child(8),
    #documentsTableScroll .documents-table td:nth-child(8) { min-width: 112px !important; }
    #documentsTableScroll .documents-table th:nth-child(9),
    #documentsTableScroll .documents-table td:nth-child(9) { min-width: 112px !important; }
    #documentsTableScroll .documents-table th:nth-child(10),
    #documentsTableScroll .documents-table td:nth-child(10) { min-width: 110px !important; }
    #documentsTableScroll .documents-table th:nth-child(11),
    #documentsTableScroll .documents-table td:nth-child(11) { min-width: 120px !important; }
    #documentsTableScroll .documents-table th:nth-child(12),
    #documentsTableScroll .documents-table td:nth-child(12) { min-width: 310px !important; }

    #documentsTableScroll .document-subject-cell,
    #documentsTableScroll .document-subject-cell strong,
    #documentsTableScroll .document-subject-cell small {
        white-space: normal !important;
        overflow: visible !important;
        line-height: 1.65 !important;
    }

    #documentsTableScroll .policy-cell strong {
        white-space: nowrap !important;
        direction: ltr !important;
        text-align: right !important;
    }

    #documentsTableScroll .actions {
        display: inline-flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-start !important;
        flex-wrap: nowrap !important;
        gap: 8px !important;
        width: max-content !important;
        max-width: none !important;
        white-space: nowrap !important;
    }

    #documentsTableScroll .actions form,
    #documentsTableScroll .actions .btn,
    #documentsTableScroll .actions button,
    #documentsTableScroll .actions a {
        flex: 0 0 auto !important;
        white-space: nowrap !important;
        margin: 0 !important;
    }

    @media (max-width: 760px) {
        #documentsTableScroll {
            overflow-x: visible !important;
            padding-bottom: 0 !important;
            direction: rtl !important;
        }

        #documentsTableScroll .documents-table {
            display: none !important;
        }
    }
    /* DOCUMENTS_SEARCH_TABLE_SCROLL_END */
</style>

<div class="documents-page">
    <div class="documents-head">
        <div>
            <h1>الكتب</h1>
            <p>بحث متقدم وفرز سريع في الكتب والمرفقات والبوالص مع دعم النشطة والمحذوفة.</p>
        </div>

        <div class="actions">
            @if($can('documents.create') || $can('documents.add'))
                <a href="{{ route('documents.create') }}" class="btn btn-primary">+ إضافة كتاب جديد</a>
            @endif
            @if($canViewTrashed)
                <a href="{{ route('documents.trash') }}" class="btn btn-secondary">سلة المحذوفات</a>
            @endif
            <a href="{{ route('documents.index') }}" class="btn btn-secondary">تحديث</a>
        </div>
    </div>

    <div class="doc-summary-grid">
        <div class="doc-summary-card"><span>إجمالي الكتب النشطة</span><strong>{{ number_format($summary['total'] ?? 0) }}</strong></div>
        <div class="doc-summary-card"><span>الكتب المحذوفة</span><strong>{{ number_format($summary['trashed_total'] ?? 0) }}</strong></div>
        <div class="doc-summary-card is-filtered"><span>نتائج الفلترة الحالية</span><strong>{{ number_format($summary['filtered'] ?? 0) }}</strong></div>
        <div class="doc-summary-card"><span>ضمن النتائج: لديها مرفقات</span><strong>{{ number_format($summary['with_attachments'] ?? 0) }}</strong></div>
        <div class="doc-summary-card"><span>ضمن النتائج: بلا مرفقات</span><strong>{{ number_format($summary['without_attachments'] ?? 0) }}</strong></div>
    </div>

    <div class="advanced-filter-card">
        <div class="filter-header">
            <div>
                <h2>فلاتر البحث</h2>
                <p>استخدم البحث العام أو الحقول الدقيقة للوصول إلى الكتاب المطلوب بسرعة.</p>
            </div>
            <span class="filter-count">{{ $activeFiltersCount ?? 0 }} فلتر نشط</span>
        </div>

        <form method="GET" action="{{ route('documents.index') }}">
            <div class="advanced-filter-grid">
                <div class="form-group">
                    <label>بحث عام</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="رقم الكتاب / الموضوع / البوليصة / الإدارة / النوع">
                </div>

                <div class="form-group">
                    <label>رقم الكتاب</label>
                    <input type="text" name="reference_number" value="{{ request('reference_number') }}" placeholder="مثال: 251230000">
                </div>

                <div class="form-group">
                    <label>رقم البوليصة الرئيسية</label>
                    <input type="text" name="main_policy_number" value="{{ request('main_policy_number') }}" placeholder="بحث مرن بدون اعتبار الفراغات">
                </div>

                <div class="form-group">
                    <label>رقم البوليصة الفرعية</label>
                    <input type="text" name="sub_policy_number" value="{{ request('sub_policy_number') }}" placeholder="بحث مرن بدون اعتبار الفراغات">
                </div>

                <div class="form-group">
                    <label>العنوان</label>
                    <input type="text" name="title" value="{{ request('title') }}" placeholder="عنوان الكتاب">
                </div>

                <div class="form-group">
                    <label>الموضوع</label>
                    <input type="text" name="subject" value="{{ request('subject') }}" placeholder="موضوع الكتاب">
                </div>

                <div class="form-group">
                    <label>المرسل</label>
                    <input type="text" name="sender" value="{{ request('sender') }}" placeholder="اسم الجهة المرسلة">
                </div>

                <div class="form-group">
                    <label>المستلم</label>
                    <input type="text" name="receiver" value="{{ request('receiver') }}" placeholder="اسم الجهة المستلمة">
                </div>

                <div class="form-group">
                    <label>الإدارة</label>
                    <select name="department_id">
                        <option value="">كل الإدارات</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>نوع الكتاب</label>
                    <select name="document_type_id">
                        <option value="">كل الأنواع</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->id }}" @selected(request('document_type_id') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>حالة الكتاب</label>
                    <select name="status">
                        <option value="">كل الحالات</option>
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>حالة السجل</label>
                    <select name="record_state">
                        @foreach($recordStateOptions as $key => $label)
                            <option value="{{ $key }}" @selected(($recordState ?? 'active') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>درجة السرية</label>
                    <select name="confidentiality">
                        <option value="">كل الدرجات</option>
                        @foreach($confidentialityOptions as $key => $label)
                            <option value="{{ $key }}" @selected(request('confidentiality') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>الأولوية</label>
                    <select name="priority">
                        <option value="">كل الأولويات</option>
                        @foreach($priorityOptions as $key => $label)
                            <option value="{{ $key }}" @selected(request('priority') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>المرفقات</label>
                    <select name="has_attachment">
                        <option value="">الكل</option>
                        <option value="yes" @selected(request('has_attachment') === 'yes')>لديه مرفق</option>
                        <option value="no" @selected(request('has_attachment') === 'no')>بدون مرفق</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>الترتيب حسب</label>
                    <select name="sort">
                        @foreach($sortOptions as $key => $label)
                            <option value="{{ $key }}" @selected($selectedSort === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>تاريخ الكتاب من</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}">
                </div>

                <div class="form-group">
                    <label>تاريخ الكتاب إلى</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}">
                </div>

                <div class="form-group">
                    <label>تاريخ الإضافة من</label>
                    <input type="date" name="created_from" value="{{ request('created_from') }}">
                </div>

                <div class="form-group">
                    <label>تاريخ الإضافة إلى</label>
                    <input type="date" name="created_to" value="{{ request('created_to') }}">
                </div>

                <div class="form-group">
                    <label>الاتجاه</label>
                    <select name="direction">
                        <option value="desc" @selected($selectedDirection === 'desc')>الأحدث أولاً</option>
                        <option value="asc" @selected($selectedDirection === 'asc')>الأقدم أولاً</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>عدد النتائج</label>
                    <select name="per_page">
                        @foreach([15, 25, 50, 100] as $count)
                            <option value="{{ $count }}" @selected($selectedPerPage === $count)>{{ $count }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="quick-filter-row">
                <a class="quick-chip {{ request('has_attachment') === 'yes' ? 'active' : '' }}" href="{{ route('documents.index', array_merge(request()->except(['page', 'has_attachment']), ['has_attachment' => 'yes'])) }}">لديها مرفقات</a>
                <a class="quick-chip {{ request('has_attachment') === 'no' ? 'active' : '' }}" href="{{ route('documents.index', array_merge(request()->except(['page', 'has_attachment']), ['has_attachment' => 'no'])) }}">بلا مرفقات</a>
                @if($canViewTrashed)
                    <a class="quick-chip {{ ($recordState ?? 'active') === 'active' ? 'active' : '' }}" href="{{ route('documents.index', array_merge(request()->except(['page', 'record_state']), ['record_state' => 'active'])) }}">النشطة</a>
                    <a class="quick-chip {{ ($recordState ?? 'active') === 'trashed' ? 'active' : '' }}" href="{{ route('documents.index', array_merge(request()->except(['page', 'record_state']), ['record_state' => 'trashed'])) }}">المحذوفة</a>
                    <a class="quick-chip {{ ($recordState ?? 'active') === 'all' ? 'active' : '' }}" href="{{ route('documents.index', array_merge(request()->except(['page', 'record_state']), ['record_state' => 'all'])) }}">الكل</a>
                @endif
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">بحث</button>
                <a href="{{ route('documents.index') }}" class="btn btn-secondary">إلغاء الفلترة</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="documents-table-wrap" id="documentsTableScroll">
            <table class="documents-table">
                <thead>
                <tr>
                    <th>رقم الكتاب</th>
                    <th>التاريخ</th>
                    <th>الموضوع</th>
                    <th>البوليصة الرئيسية</th>
                    <th>البوليصة الفرعية</th>
                    <th>الإدارة</th>
                    <th>النوع</th>
                    <th>الحالة</th>
                    <th>الأولوية</th>
                    <th>المرفقات</th>
                    <th>حالة السجل</th>
                    <th>إجراءات</th>
                </tr>
                </thead>
                <tbody>
                @forelse($documents as $document)
                    @php
                        $priorityClass = match($document->priority ?? '') {
                            'urgent' => 'pill-danger',
                            'high' => 'pill-warning',
                            default => 'pill-muted',
                        };

                        $isTrashed = method_exists($document, 'trashed') && $document->trashed();
                        $attachmentsCount = $document->attachments_count ?? ($document->mainAttachment ? 1 : 0);
                        $subject = $document->subject ?: $document->title ?: $document->description ?: '-';
                        $mainPolicy = $document->main_policy_number ?: '-';
                        $subPolicy = $document->sub_policy_number ?: '-';
                    @endphp
                    <tr>
                        <td class="document-number-cell"><strong>{{ $document->reference_number }}</strong></td>
                        <td class="document-date-cell">{{ $formatDocumentDate($document) }}</td>
                        <td class="document-subject-cell">
                            <strong>{{ \Illuminate\Support\Str::limit($subject, 90) }}</strong>
                            @if(!empty($document->title) && $document->title !== $subject)
                                <small>{{ \Illuminate\Support\Str::limit($document->title, 80) }}</small>
                            @endif
                            @if(!empty($document->sender) || !empty($document->receiver))
                                <small>من: {{ $document->sender ?: '-' }} / إلى: {{ $document->receiver ?: '-' }}</small>
                            @endif
                        </td>
                        <td class="policy-cell"><strong>{{ $mainPolicy }}</strong></td>
                        <td class="policy-cell"><strong>{{ $subPolicy }}</strong></td>
                        <td>{{ $document->department?->name ?? '-' }}</td>
                        <td>{{ $document->documentType?->name ?? '-' }}</td>
                        <td><span class="pill">{{ $document->status_name ?? $statusLabel($document->status ?? null) }}</span></td>
                        <td><span class="pill {{ $priorityClass }}">{{ $document->priority_name ?? $priorityLabel($document->priority ?? null) }}</span></td>
                        <td>
                            @if($attachmentsCount > 0)
                                <span class="pill">{{ $attachmentsCount }} مرفق</span>
                            @else
                                <span class="pill pill-muted">لا يوجد</span>
                            @endif
                        </td>
                        <td>
                            @if($isTrashed)
                                <span class="pill pill-danger">محذوف</span>
                                <small style="display:block;color:#94a3b8;margin-top:5px;">{{ $formatDeletedAt($document) }}</small>
                            @else
                                <span class="pill pill-success">نشط</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                @if(!$isTrashed)
                                    @if($can('documents.view'))
                                        <a class="btn btn-secondary" href="{{ route('documents.show', $document) }}">عرض</a>
                                    @endif
                                    @if($can('documents.edit'))
                                        <a class="btn btn-primary" href="{{ route('documents.edit', $document) }}">تعديل</a>
                                    @endif
                                    @if($can('documents.print'))
                                        <a class="btn btn-warning" target="_blank" href="{{ route('documents.print-reference', $document) }}">طباعة الرقم</a>
                                    @endif
                                    @if($can('emails.send'))
                                        <a class="btn btn-info" href="{{ route('documents.email.compose', $document) }}">إرسال بالبريد</a>
                                    @endif
                                    @if($can('whatsapp.send'))
                                        <a class="btn btn-success" href="{{ route('documents.whatsapp.compose', $document) }}">إرسال واتساب</a>
                                    @endif
                                    @if($can('attachment_shares.create') && $attachmentsCount > 0)
                                        <a class="btn btn-secondary" href="{{ route('documents.shared-attachments.create', $document) }}">رابط مرفقات</a>
                                    @endif
                                    @if($can('documents.delete') || $can('documents.destroy'))
                                        <form method="POST" action="{{ route('documents.destroy', $document) }}" data-confirm="هل أنت متأكد من حذف هذا الكتاب؟">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">حذف</button>
                                        </form>
                                    @endif
                                @else
                                    @if($can('documents.restore'))
                                        <form method="POST" action="{{ route('documents.restore', $document->id) }}" data-confirm="هل تريد استعادة هذا الكتاب؟">
                                            @csrf
                                            <button type="submit" class="btn btn-success">استعادة</button>
                                        </form>
                                    @else
                                        <span class="pill pill-muted">لا توجد إجراءات</span>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="empty-documents">لا توجد كتب مطابقة لمعايير البحث الحالية.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            <div class="document-mobile-list">
                @foreach($documents as $document)
                    @php
                        $isTrashed = method_exists($document, 'trashed') && $document->trashed();
                        $attachmentsCount = $document->attachments_count ?? ($document->mainAttachment ? 1 : 0);
                        $subject = $document->subject ?: $document->title ?: $document->description ?: '-';
                        $mainPolicy = $document->main_policy_number ?: '-';
                        $subPolicy = $document->sub_policy_number ?: '-';
                    @endphp
                    <div class="document-mobile-card">
                        <h3>{{ \Illuminate\Support\Str::limit($subject, 90) }}</h3>
                        <div class="mobile-info-grid">
                            <div class="mobile-info-item"><span>رقم الكتاب</span><strong>{{ $document->reference_number }}</strong></div>
                            <div class="mobile-info-item"><span>التاريخ</span><strong>{{ $formatDocumentDate($document) }}</strong></div>
                            <div class="mobile-info-item"><span>البوليصة الرئيسية</span><strong>{{ $mainPolicy }}</strong></div>
                            <div class="mobile-info-item"><span>البوليصة الفرعية</span><strong>{{ $subPolicy }}</strong></div>
                            <div class="mobile-info-item"><span>الإدارة</span><strong>{{ $document->department?->name ?? '-' }}</strong></div>
                            <div class="mobile-info-item"><span>النوع</span><strong>{{ $document->documentType?->name ?? '-' }}</strong></div>
                            <div class="mobile-info-item"><span>المرفقات</span><strong>{{ $attachmentsCount > 0 ? $attachmentsCount . ' مرفق' : 'لا يوجد' }}</strong></div>
                            <div class="mobile-info-item"><span>حالة السجل</span><strong>{{ $isTrashed ? 'محذوف' : 'نشط' }}</strong></div>
                        </div>
                        <div class="actions">
                            @if(!$isTrashed)
                                @if($can('documents.view'))
                                    <a class="btn btn-secondary" href="{{ route('documents.show', $document) }}">عرض</a>
                                @endif
                                @if($can('documents.edit'))
                                    <a class="btn btn-primary" href="{{ route('documents.edit', $document) }}">تعديل</a>
                                @endif
                                @if($can('documents.print'))
                                    <a class="btn btn-warning" target="_blank" href="{{ route('documents.print-reference', $document) }}">طباعة الرقم</a>
                                @endif
                                @if($can('emails.send'))
                                    <a class="btn btn-info" href="{{ route('documents.email.compose', $document) }}">إرسال بالبريد</a>
                                @endif
                                @if($can('whatsapp.send'))
                                    <a class="btn btn-success" href="{{ route('documents.whatsapp.compose', $document) }}">إرسال واتساب</a>
                                @endif
                                @if($can('attachment_shares.create') && $attachmentsCount > 0)
                                    <a class="btn btn-secondary" href="{{ route('documents.shared-attachments.create', $document) }}">رابط مرفقات</a>
                                @endif
                            @else
                                @if($can('documents.restore'))
                                    <form method="POST" action="{{ route('documents.restore', $document->id) }}" data-confirm="هل تريد استعادة هذا الكتاب؟">
                                        @csrf
                                        <button type="submit" class="btn btn-success">استعادة</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="pagination">
            {{ $documents->links() }}
        </div>
    </div>
</div>
<script>
(function () {
    function alignDocumentsTableScroll() {
        var wrap = document.getElementById('documentsTableScroll');
        if (!wrap || window.innerWidth <= 760) return;
        // Wrapper is LTR while the table is RTL, so max scrollLeft shows the first Arabic/rightmost columns.
        wrap.scrollLeft = wrap.scrollWidth;
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', alignDocumentsTableScroll);
    } else {
        alignDocumentsTableScroll();
    }
    window.addEventListener('resize', alignDocumentsTableScroll);
})();
</script>
@endsection

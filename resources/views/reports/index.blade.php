@extends('layouts.app')

@section('title', 'التقارير')

@section('content')
@php
    $filterSummary = [];

    if (!empty($filters['date_from'])) {
        $filterSummary[] = 'من تاريخ: ' . $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $filterSummary[] = 'إلى تاريخ: ' . $filters['date_to'];
    }

    if (!empty($filters['department_id'])) {
        $selectedDepartment = $departments->firstWhere('id', (int) $filters['department_id']);
        if ($selectedDepartment) {
            $filterSummary[] = 'الإدارة: ' . $selectedDepartment->name;
        }
    }

    if (!empty($filters['document_type_id'])) {
        $selectedType = $documentTypes->firstWhere('id', (int) $filters['document_type_id']);
        if ($selectedType) {
            $filterSummary[] = 'نوع الكتاب: ' . $selectedType->name;
        }
    }

    if (!empty($filters['keyword'])) {
        $filterSummary[] = 'بحث: ' . $filters['keyword'];
    }

    if (!empty($filters['include_deleted'])) {
        $filterSummary[] = 'يشمل المحذوفات';
    }
@endphp

<div class="reports-print-root" dir="rtl">
    <div class="reports-print-paper">
        <div class="page-header reports-page-header">
            <div>
                <h1>📊 التقارير</h1>
                <p>تقرير شامل للكتب والمرفقات مع الفلترة والطباعة والتصدير.</p>
                <div class="print-only print-report-meta">
                    <span>تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}</span>
                    @if(count($filterSummary))
                        <span>الفلاتر: {{ implode(' | ', $filterSummary) }}</span>
                    @else
                        <span>الفلاتر: كل البيانات</span>
                    @endif
                </div>
            </div>
            <div class="page-actions no-print">
                @if(auth()->user()?->hasPermission('reports.export'))
                <a href="{{ route('reports.pdf', request()->query()) }}" class="btn btn-danger">📄 تصدير PDF</a>
                @endif
                <a href="{{ route('reports.export', request()->query()) }}" class="btn btn-primary">⬇️ تصدير Excel/CSV</a>
                @if(auth()->user()?->hasPermission('reports.print'))
                <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ طباعة التقرير</button>
                @endif
            </div>
        </div>

        <div class="report-filter-box no-print">
            @if(auth()->user()?->hasPermission('reports.view'))
            
{{-- REPORTS_PRINT_BUTTON_FIX_START --}}
<style>
    .reports-print-button-panel {
        display: flex;
        justify-content: flex-start;
        align-items: center;
        gap: .65rem;
        flex-wrap: wrap;
        margin: 0 0 1rem 0;
        direction: rtl;
    }

    .reports-print-button-panel .report-print-professional-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .45rem;
        min-height: 40px;
        padding: .65rem 1rem;
        border-radius: .7rem;
        border: 1px solid rgba(59, 130, 246, .35);
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff !important;
        text-decoration: none !important;
        font-weight: 800;
        line-height: 1;
        box-shadow: 0 10px 22px rgba(37, 99, 235, .18);
        white-space: nowrap;
    }

    .reports-print-button-panel .report-print-professional-btn:hover {
        transform: translateY(-1px);
        filter: brightness(1.04);
    }

    @media print {
        .reports-print-button-panel {
            display: none !important;
        }
    }
</style>

<div class="reports-print-button-panel" aria-label="إجراءات التقرير الرسمي">
    <a href="{{ url('/reports/print') }}" class="report-print-professional-btn" target="_blank" rel="noopener">
        <span aria-hidden="true">🧾</span>
        <span>تقرير رسمي منسق</span>
    </a>
</div>
{{-- REPORTS_PRINT_BUTTON_FIX_END --}}
<form method="GET" action="{{ route('reports.index') }}" class="report-filter-grid">
                <div>
                    <label>من تاريخ</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
                </div>
                <div>
                    <label>إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}">
                </div>
                <div>
                    <label>الإدارة</label>
                    <select name="department_id">
                        <option value="">الكل</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>نوع الكتاب</label>
                    <select name="document_type_id">
                        <option value="">الكل</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) $filters['document_type_id'] === (string) $type->id)>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>بحث</label>
                    <input type="text" name="keyword" value="{{ $filters['keyword'] }}" placeholder="رقم الكتاب، الموضوع، البوليصة...">
                </div>
                <div>
                    <label>خيارات</label>
                    <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                        <input type="checkbox" name="include_deleted" value="1" id="include_deleted" @checked($filters['include_deleted']) style="width:auto;">
                        <label for="include_deleted" style="margin:0; font-weight:500;">إظهار المحذوف</label>
                    </div>
                    <button class="btn btn-primary" type="submit">تطبيق الفلتر</button>
                    <a class="btn btn-secondary" href="{{ route('reports.index') }}">تصفير</a>
                </div>
            </form>
            @endif
        </div>

        <div class="reports-grid">
            <div class="report-card summary-card">
                <div class="label">إجمالي الكتب حسب الفلتر</div>
                <div class="value">{{ number_format($stats['total_documents']) }}</div>
            </div>
            <div class="report-card summary-card">
                <div class="label">كتب هذا الشهر</div>
                <div class="value">{{ number_format($stats['this_month']) }}</div>
            </div>
            <div class="report-card summary-card">
                <div class="label">إجمالي المرفقات</div>
                <div class="value">{{ number_format($stats['attachments_count']) }}</div>
            </div>
            <div class="report-card summary-card">
                <div class="label">كتب في سلة المحذوفات</div>
                <div class="value">{{ number_format($stats['deleted_documents']) }}</div>
            </div>
        </div>

        <div class="mini-report-grid">
            <div class="report-card report-block">
                <h2 class="report-section-title">حسب الإدارة</h2>
                <table class="mini-table">
                    <thead>
                        <tr><th>الإدارة</th><th>العدد</th></tr>
                    </thead>
                    <tbody>
                        @forelse($byDepartment as $row)
                            <tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['total']) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="muted">لا توجد بيانات.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="report-card report-block">
                <h2 class="report-section-title">حسب نوع الكتاب</h2>
                <table class="mini-table">
                    <thead>
                        <tr><th>نوع الكتاب</th><th>العدد</th></tr>
                    </thead>
                    <tbody>
                        @forelse($byType as $row)
                            <tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['total']) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="muted">لا توجد بيانات.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="details-section">
            <h2 class="report-section-title">تفاصيل الكتب</h2>
            <div class="table-responsive">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>رقم الكتاب</th>
                            <th>تاريخ الكتاب</th>
                            <th>الموضوع</th>
                            <th>الإدارة</th>
                            <th>نوع الكتاب</th>
                            <th>البوليصة</th>
                            <th class="no-print">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $document)
                            <tr>
                                <td><strong>{{ $document->reference_number ?? '-' }}</strong></td>
                                <td>{{ optional($document->reference_date)->format('Y-m-d') ?? ($document->reference_date ?? '-') }}</td>
                                <td>{{ $document->subject ?? '-' }}</td>
                                <td>{{ optional($document->department ?? null)->name ?? '-' }}</td>
                                <td>{{ optional($document->documentType ?? null)->name ?? '-' }}</td>
                                <td>
                                    <div>رئيسية: {{ $document->main_policy_number ?? '-' }}</div>
                                    <div>فرعية: {{ $document->sub_policy_number ?? '-' }}</div>
                                </td>
                                <td class="no-print">
                                    @if(auth()->user()?->hasPermission('documents.view'))
                                    <a class="btn btn-sm btn-secondary" href="{{ route('documents.show', $document) }}">عرض</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="muted">لا توجد كتب مطابقة للفلاتر الحالية.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="no-print" style="margin-top:16px;">
            {{ $documents->links() }}
        </div>
    </div>
</div>

<style>
    .print-only { display: none; }

    .reports-print-paper {
        width: 100%;
    }

    .reports-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }

    .reports-page-header h1 {
        margin: 0 0 6px;
        font-size: 30px;
        font-weight: 900;
    }

    .reports-page-header p {
        margin: 0;
        color: #64748b;
    }

    .reports-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .report-card {
        background: var(--card-bg, #fff);
        border: 1px solid rgba(148, 163, 184, .25);
        border-radius: 16px;
        padding: 18px;
        box-shadow: 0 10px 25px rgba(15, 23, 42, .06);
    }

    .report-card .label {
        color: #64748b;
        font-size: 13px;
        margin-bottom: 8px;
    }

    .report-card .value {
        font-size: 28px;
        font-weight: 800;
    }

    .report-filter-box {
        background: var(--card-bg, #fff);
        border: 1px solid rgba(148, 163, 184, .25);
        border-radius: 16px;
        padding: 16px;
        margin-bottom: 18px;
    }

    .report-filter-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 10px;
        align-items: end;
    }

    .report-filter-grid label {
        display: block;
        font-weight: 700;
        margin-bottom: 6px;
        font-size: 13px;
    }

    .report-filter-grid input,
    .report-filter-grid select {
        width: 100%;
        border: 1px solid #d8dee9;
        border-radius: 10px;
        padding: 9px 10px;
        background: #fff;
    }

    .report-section-title {
        margin: 22px 0 10px;
        font-size: 18px;
        font-weight: 800;
    }

    .mini-report-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 18px;
    }

    .mini-table,
    .report-table {
        width: 100%;
        border-collapse: collapse;
    }

    .mini-table th,
    .mini-table td,
    .report-table th,
    .report-table td {
        border-bottom: 1px solid rgba(148, 163, 184, .25);
        padding: 11px;
        text-align: right;
        vertical-align: top;
    }

    .report-table {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        overflow: hidden;
    }

    .mini-table th,
    .report-table th {
        background: rgba(15, 23, 42, .06);
        font-weight: 800;
    }

    .muted {
        color: #64748b;
        font-size: 12px;
    }

    @media (max-width: 1100px) {
        .reports-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .report-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .mini-report-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .reports-grid,
        .report-filter-grid {
            grid-template-columns: 1fr;
        }

        .reports-page-header {
            align-items: flex-start;
            flex-direction: column;
        }
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 9mm 8mm 9mm 8mm;
        }

        html,
        body {
            width: 210mm !important;
            min-height: 297mm !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            color: #111827 !important;
            direction: rtl !important;
            font-family: "Cairo", Tahoma, Arial, sans-serif !important;
            font-size: 9pt !important;
            line-height: 1.45 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body * {
            visibility: hidden !important;
        }

        .reports-print-root,
        .reports-print-root * {
            visibility: visible !important;
        }

        .reports-print-root {
            position: absolute !important;
            top: 0 !important;
            right: 0 !important;
            left: 0 !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
        }

        .reports-print-paper {
            width: 194mm !important;
            margin: 0 auto !important;
            padding: 0 !important;
            background: #ffffff !important;
        }

        .no-print,
        .sidebar,
        .side-nav,
        .topbar,
        .app-sidebar,
        .main-sidebar,
        .navbar,
        .page-actions,
        nav,
        aside,
        footer,
        header:not(.reports-page-header) {
            display: none !important;
            visibility: hidden !important;
        }

        .print-only {
            display: block !important;
        }

        .content,
        .main-content,
        .app-content,
        .page-content,
        .container,
        .container-fluid {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: none !important;
            background: #ffffff !important;
            box-shadow: none !important;
        }

        .reports-page-header {
            display: block !important;
            text-align: center !important;
            margin: 0 0 5mm !important;
            padding: 0 0 3mm !important;
            border-bottom: 1px solid #d1d5db !important;
            break-inside: avoid !important;
        }

        .reports-page-header h1 {
            margin: 0 0 2mm !important;
            font-size: 20pt !important;
            font-weight: 900 !important;
            color: #111827 !important;
        }

        .reports-page-header p {
            margin: 0 0 2mm !important;
            font-size: 9pt !important;
            color: #374151 !important;
        }

        .print-report-meta {
            display: flex !important;
            justify-content: center !important;
            gap: 6mm !important;
            flex-wrap: wrap !important;
            font-size: 8pt !important;
            color: #4b5563 !important;
        }

        .reports-grid {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 3mm !important;
            margin: 0 0 4mm !important;
            break-inside: avoid !important;
        }

        .summary-card,
        .report-card {
            border: 1px solid #d1d5db !important;
            border-radius: 3mm !important;
            padding: 3mm !important;
            box-shadow: none !important;
            background: #ffffff !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        .report-card .label {
            margin: 0 0 1.5mm !important;
            font-size: 8pt !important;
            color: #4b5563 !important;
        }

        .report-card .value {
            font-size: 17pt !important;
            line-height: 1 !important;
            font-weight: 900 !important;
            color: #111827 !important;
        }

        .mini-report-grid {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 4mm !important;
            margin: 0 0 4mm !important;
            break-inside: avoid !important;
        }

        .report-section-title {
            margin: 0 0 2.5mm !important;
            font-size: 11pt !important;
            font-weight: 900 !important;
            color: #111827 !important;
        }

        .details-section {
            margin-top: 2mm !important;
        }

        .table-responsive {
            overflow: visible !important;
        }

        .mini-table,
        .report-table {
            width: 100% !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
            border: 1px solid #d1d5db !important;
            border-radius: 0 !important;
            overflow: visible !important;
            background: #ffffff !important;
            font-size: 8.2pt !important;
        }

        .mini-table th,
        .mini-table td,
        .report-table th,
        .report-table td {
            padding: 1.8mm 1.6mm !important;
            border: 1px solid #d1d5db !important;
            text-align: right !important;
            vertical-align: top !important;
            color: #111827 !important;
            word-break: break-word !important;
            overflow-wrap: anywhere !important;
        }

        .mini-table th,
        .report-table th {
            background: #f3f4f6 !important;
            font-weight: 900 !important;
            white-space: nowrap !important;
        }

        .report-table th:nth-child(1),
        .report-table td:nth-child(1) { width: 18%; }

        .report-table th:nth-child(2),
        .report-table td:nth-child(2) { width: 14%; }

        .report-table th:nth-child(3),
        .report-table td:nth-child(3) { width: 20%; }

        .report-table th:nth-child(4),
        .report-table td:nth-child(4) { width: 16%; }

        .report-table th:nth-child(5),
        .report-table td:nth-child(5) { width: 13%; }

        .report-table th:nth-child(6),
        .report-table td:nth-child(6) { width: 19%; }

        .muted {
            color: #6b7280 !important;
            font-size: 8pt !important;
        }
    }
</style>
@endsection

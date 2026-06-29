@php
    $systemTitle = 'نظام الأرشيف الإلكتروني الخاص بقسم الشحن والتأمين';
    $reportTitle = 'تقرير الكتب والمرفقات';
    $formatDate = function ($value) {
        if (empty($value)) return '—';
        try { return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d'); } catch (\Throwable $e) { return (string) $value; }
    };
    $attachmentCount = function ($document) {
        try {
            if (isset($document->attachments)) return $document->attachments->count();
        } catch (\Throwable $e) {}
        return '—';
    };
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reportTitle }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm 15mm; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #e5e7eb;
            color: #111827;
            font-family: "Cairo", "Tahoma", "Arial", sans-serif;
            font-size: 11.5px;
            line-height: 1.65;
        }
        body { direction: rtl; }
        .screen-toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            background: #0f172a;
            border-bottom: 1px solid #1e293b;
        }
        .screen-toolbar a, .screen-toolbar button {
            border: 0;
            border-radius: 10px;
            padding: 9px 15px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-light { background: #f8fafc; color: #0f172a; }
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            background: #fff;
            border: 1px solid #d1d5db;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .18);
        }
        .report { padding: 15mm 13mm 14mm; }
        .report-header {
            display: grid;
            grid-template-columns: 1fr 55mm;
            gap: 12px;
            align-items: stretch;
            padding-bottom: 10px;
            border-bottom: 3px solid #111827;
        }
        .brand { display: flex; gap: 12px; align-items: center; }
        .brand-icon {
            width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center;
            background: #eff6ff; border: 1px solid #bfdbfe; font-size: 21px;
        }
        .brand h1 { margin: 0; font-size: 20px; line-height: 1.25; font-weight: 900; }
        .brand p { margin: 4px 0 0; color: #4b5563; font-size: 10.5px; font-weight: 700; }
        .meta-box { border: 1px solid #d1d5db; border-radius: 12px; padding: 8px 10px; background: #f9fafb; font-size: 10px; }
        .meta-box div { display: flex; justify-content: space-between; gap: 8px; border-bottom: 1px dashed #d1d5db; padding: 3px 0; }
        .meta-box div:last-child { border-bottom: 0; }
        .meta-box strong { color: #111827; }
        .title-block { text-align: center; padding: 16px 0 10px; }
        .title-block h2 { margin: 0; font-size: 23px; font-weight: 900; }
        .title-block p { margin: 5px auto 0; color: #4b5563; max-width: 150mm; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 12px 0; }
        .summary-card { border: 1px solid #d1d5db; border-radius: 13px; padding: 9px; min-height: 62px; break-inside: avoid; }
        .summary-card .label { color: #6b7280; font-size: 10px; font-weight: 800; margin-bottom: 5px; }
        .summary-card .value { font-size: 23px; line-height: 1; font-weight: 900; }
        .filters-box { border: 1px solid #d1d5db; border-radius: 14px; padding: 10px; background: #f9fafb; margin: 12px 0 14px; break-inside: avoid; }
        .filters-title { font-weight: 900; margin-bottom: 6px; }
        .filters-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px 10px; font-size: 10.5px; }
        .filters-grid span { color: #6b7280; font-weight: 800; }
        .section { margin-top: 14px; break-inside: avoid; }
        .section-title { margin: 0 0 8px; font-size: 14px; font-weight: 900; color: #111827; }
        .two-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        th { background: #111827; color: #fff; font-weight: 900; padding: 7px 6px; border: 1px solid #111827; text-align: right; }
        td { padding: 7px 6px; border: 1px solid #d1d5db; vertical-align: top; overflow-wrap: anywhere; }
        tbody tr:nth-child(even) td { background: #f9fafb; }
        .mini-table th { background: #f3f4f6; color: #111827; border-color: #d1d5db; }
        .pill { display: inline-block; border: 1px solid #bfdbfe; background: #eff6ff; color: #1e3a8a; border-radius: 999px; padding: 2px 7px; font-weight: 900; }
        .muted { color: #6b7280; }
        .empty { border: 1px dashed #d1d5db; border-radius: 10px; padding: 10px; color: #6b7280; text-align: center; }
        .footer { margin-top: 18px; padding-top: 8px; border-top: 1px solid #d1d5db; color: #6b7280; font-size: 9.5px; display: flex; justify-content: space-between; gap: 12px; }
        @media print {
            html, body { background: #fff !important; }
            .screen-toolbar { display: none !important; }
            .page { width: auto; min-height: auto; margin: 0; border: 0; box-shadow: none; }
            .report { padding: 0; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/professional-report-table-fit-fix.css') }}?v=20260628v2">
</head>
<body class="professional-report-page">
    <div class="screen-toolbar">
        <a href="{{ route('reports.index', request()->query()) }}" class="btn-light">رجوع للتقارير</a>
        <button type="button" onclick="window.print()" class="btn-primary">🖨️ طباعة / حفظ PDF</button>
    </div>

    <main class="page">
        <article class="report">
            <header class="report-header">
                <div class="brand">
                    <div class="brand-icon">📁</div>
                    <div>
                        <h1>{{ $systemTitle }}</h1>
                        <p>إدارة الكتب، المرفقات، البوالص، والطباعة الرسمية</p>
                    </div>
                </div>
                <div class="meta-box">
                    <div><span>رقمالتقرير</span><strong>{{ $reportCode }}</strong></div>
                    <div><span>تاريخ الإنشاء</span><strong>{{ $generatedAt->format('Y-m-d H:i') }}</strong></div>
                    <div><span>أعد بواسطة</span><strong>{{ $generatedBy }}</strong></div>
                </div>
            </header>

            <section class="title-block">
                <h2>{{ $reportTitle }}</h2>
                <p>تقرير رسمي يعرض ملخص الكتب والمرفقات حسب الفلاتر المحددة، مع تفاصيل البوالص والإدارات وأنواع الكتب.</p>
            </section>

            <section class="summary-grid">
                <div class="summary-card"><div class="label">إجمالي الكتب</div><div class="value">{{ number_format($summary['total_documents']) }}</div></div>
                <div class="summary-card"><div class="label">كتب هذا الشهر</div><div class="value">{{ number_format($summary['this_month']) }}</div></div>
                <div class="summary-card"><div class="label">إجمالي المرفقات</div><div class="value">{{ number_format($summary['attachments_count']) }}</div></div>
                <div class="summary-card"><div class="label">كتب محذوفة</div><div class="value">{{ number_format($summary['deleted_documents']) }}</div></div>
            </section>

            <section class="filters-box">
                <div class="filters-title">نطاق التقرير والفلاتر</div>
                <div class="filters-grid">
                    <div><span>الفترة:</span> {{ $filterLabels['period'] }}</div>
                    <div><span>الإدارة:</span> {{ $filterLabels['department'] }}</div>
                    <div><span>نوع الكتاب:</span> {{ $filterLabels['document_type'] }}</div>
                    <div><span>البحث:</span> {{ $filterLabels['keyword'] }}</div>
                    <div><span>يشمل المحذوف:</span> {{ $filterLabels['include_deleted'] }}</div>
                    <div><span>عدد النتائج:</span> {{ number_format($documents->count()) }}</div>
                </div>
            </section>

            <section class="two-cols section">
                <div>
                    <h3 class="section-title">توزيع الكتب حسب الإدارة</h3>
                    @include('reports.partials.print-summary-table', ['rows' => $byDepartment, 'firstColumn' => 'الإدارة'])
                </div>
                <div>
                    <h3 class="section-title">توزيع الكتب حسب نوع الكتاب</h3>
                    @include('reports.partials.print-summary-table', ['rows' => $byType, 'firstColumn' => 'نوع الكتاب'])
                </div>
            </section>

            <section class="section">
                <h3 class="section-title">تفاصيل الكتب</h3>
                @if($documents->isEmpty())
                    <div class="empty">لا توجد كتب مطابقة للفلاتر الحالية.</div>
                @else
                    <table class="professional-report-table professional-report-details-table">
                        <thead>
                            <tr>
                                <th style="width: 13%;">رقم الكتاب</th>
                                <th style="width: 11%;">التاريخ</th>
                                <th style="width: 22%;">الموضوع</th>
                                <th style="width: 14%;">الإدارة</th>
                                <th style="width: 13%;">نوع الكتاب</th>
                                <th style="width: 12%;">الرئيسية</th>
                                <th style="width: 12%;">الفرعية</th>
                                <th style="width: 7%;">مرفقات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documents as $document)
                                <tr>
                                    <td><span class="pill">{{ $document->reference_number ?? '—' }}</span></td>
                                    <td>{{ $formatDate($document->reference_date ?? $document->created_at ?? null) }}</td>
                                    <td>{{ $document->subject ?? $document->title ?? '—' }}</td>
                                    <td>{{ optional($document->department ?? null)->name ?? '—' }}</td>
                                    <td>{{ optional($document->documentType ?? null)->name ?? '—' }}</td>
                                    <td>{{ $document->main_policy_number ?? '—' }}</td>
                                    <td>{{ $document->sub_policy_number ?? '—' }}</td>
                                    <td>{{ $attachmentCount($document) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <footer class="footer">
                <span>تمإنشاء هذا التقرير آلياً من نظام الأرشيف الإلكتروني.</span>
                <span>{{ $generatedAt->format('Y-m-d H:i:s') }}</span>
            </footer>
        </article>
    </main>
</body>
</html>

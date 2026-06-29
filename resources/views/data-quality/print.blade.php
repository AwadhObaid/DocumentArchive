@php
    $reportTitle = 'تقرير جودة البيانات';
    $systemTitle = 'نظام الأرشيف الإلكتروني الخاص بقسم الشحن والتأمين';
    $fmtDate = function ($value) {
        if (empty($value)) return '—';
        try { return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d'); } catch (\Throwable $e) { return $value; }
    };
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reportTitle }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 12mm 16mm 12mm;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #e5e7eb;
            color: #111827;
            font-family: "Cairo", "Tahoma", "Arial", sans-serif;
            font-size: 12px;
            line-height: 1.75;
        }

        body { direction: rtl; }

        .screen-toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            background: #0f172a;
            border-bottom: 1px solid #1e293b;
        }

        .screen-toolbar a,
        .screen-toolbar button {
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
            margin: 18px auto;
            padding: 0;
            background: #fff;
            border: 1px solid #d1d5db;
            box-shadow: 0 18px 50px rgba(15, 23, 42, .18);
        }

        .report {
            padding: 18mm 15mm 16mm;
        }

        .report-header {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 3px solid #111827;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            font-size: 22px;
        }

        .brand h1 {
            margin: 0;
            font-size: 22px;
            line-height: 1.25;
            color: #111827;
            font-weight: 900;
        }

        .brand p {
            margin: 4px 0 0;
            color: #4b5563;
            font-size: 11px;
            font-weight: 700;
        }

        .meta-box {
            min-width: 54mm;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 9px 11px;
            background: #f9fafb;
            font-size: 10.5px;
            color: #374151;
        }

        .meta-box div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px dashed #d1d5db;
            padding: 3px 0;
        }

        .meta-box div:last-child { border-bottom: 0; }
        .meta-box strong { color: #111827; }

        .title-block {
            padding: 18px 0 12px;
            text-align: center;
        }

        .title-block h2 {
            margin: 0;
            font-size: 24px;
            color: #111827;
            font-weight: 900;
        }

        .title-block p {
            margin: 6px auto 0;
            max-width: 150mm;
            color: #4b5563;
            font-size: 12px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 9px;
            margin: 12px 0 14px;
        }

        .summary-card {
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 10px;
            min-height: 68px;
            background: #ffffff;
            break-inside: avoid;
        }

        .summary-card .label {
            color: #6b7280;
            font-size: 10.5px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .summary-card .value {
            font-size: 24px;
            line-height: 1;
            color: #111827;
            font-weight: 900;
        }

        .summary-card.warning { border-color: #f59e0b; background: #fffbeb; }
        .summary-card.success { border-color: #22c55e; background: #f0fdf4; }

        .notice {
            margin: 12px 0 16px;
            border: 1px solid #f59e0b;
            border-right-width: 5px;
            border-radius: 12px;
            padding: 10px 12px;
            background: #fffbeb;
            color: #92400e;
            font-weight: 800;
            break-inside: avoid;
        }

        .section {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            break-inside: avoid;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .section-title {
            margin: 0;
            font-size: 15px;
            color: #111827;
            font-weight: 900;
        }

        .section-desc {
            margin: 2px 0 0;
            color: #6b7280;
            font-size: 10.5px;
        }

        .badge {
            min-width: 28px;
            height: 28px;
            padding: 0 9px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 900;
        }

        .empty {
            padding: 10px 12px;
            border: 1px dashed #d1d5db;
            border-radius: 10px;
            color: #6b7280;
            background: #f9fafb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 8px;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 7px 8px;
            vertical-align: top;
            text-align: right;
            word-break: break-word;
        }

        th {
            background: #111827;
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 900;
        }

        td { color: #1f2937; background: #fff; }
        tr:nth-child(even) td { background: #f9fafb; }

        .status-table th { background: #374151; }
        .ok { color: #047857; font-weight: 900; }
        .warn { color: #b45309; font-weight: 900; }
        .danger { color: #b91c1c; font-weight: 900; }

        .pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            border-radius: 999px;
            padding: 2px 8px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #111827;
            font-weight: 900;
            margin: 1px 2px;
            white-space: nowrap;
        }

        .report-footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #d1d5db;
            color: #6b7280;
            font-size: 10px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .page-break { page-break-before: always; }

        @media print {
            html, body { background: #fff !important; }
            .screen-toolbar { display: none !important; }
            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                border: 0;
                box-shadow: none;
            }
            .report { padding: 0; }
            a { color: inherit; text-decoration: none; }
            .section { break-inside: avoid; }
            tr { break-inside: avoid; page-break-inside: avoid; }
        }

        @media screen and (max-width: 900px) {
            .page { width: calc(100% - 20px); }
            .report { padding: 22px; }
            .report-header { grid-template-columns: 1fr; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="screen-toolbar">
        <button class="btn-primary" onclick="window.print()">🖨️ طباعة التقرير / حفظ PDF</button>
        <a class="btn-light" href="{{ route('data-quality.index') }}">رجوع لجودة البيانات</a>
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
                    <div><span>نوع التقرير</span><strong>جودة البيانات</strong></div>
                    <div><span>تاريخ الإنشاء</span><strong>{{ $generatedAt->format('Y-m-d') }}</strong></div>
                    <div><span>الوقت</span><strong>{{ $generatedAt->format('H:i') }}</strong></div>
                    <div><span>أُنشئ بواسطة</span><strong>{{ $generatedBy }}</strong></div>
                </div>
            </header>

            <section class="title-block">
                <h2>{{ $reportTitle }}</h2>
                <p>تقرير رسمي لمراجعة سلامة بيانات الأرشيف: المرفقات الناقصة، البوالص المكررة، البيانات غير المكتملة، والكتب المحذوفة مؤقتاً.</p>
            </section>

            <section class="summary-grid">
                <div class="summary-card success">
                    <div class="label">إجمالي الكتب الفعالة</div>
                    <div class="value">{{ $summary['active_documents'] }}</div>
                </div>
                <div class="summary-card {{ $summary['without_attachments'] > 0 ? 'warning' : 'success' }}">
                    <div class="label">كتب بلا مرفقات</div>
                    <div class="value">{{ $summary['without_attachments'] }}</div>
                </div>
                <div class="summary-card {{ $summary['duplicate_main_policies'] > 0 ? 'warning' : 'success' }}">
                    <div class="label">بوالص رئيسية مكررة</div>
                    <div class="value">{{ $summary['duplicate_main_policies'] }}</div>
                </div>
                <div class="summary-card {{ $summary['duplicate_sub_policies'] > 0 ? 'warning' : 'success' }}">
                    <div class="label">بوالص فرعية مكررة</div>
                    <div class="value">{{ $summary['duplicate_sub_policies'] }}</div>
                </div>
            </section>

            <section class="section">
                <div class="section-header">
                    <div>
                        <h3 class="section-title">ملخص نتيجة المراجعة</h3>
                        <p class="section-desc">قراءة سريعة للحالات التي تحتاج متابعة من المدير.</p>
                    </div>
                    <span class="badge">{{ $summary['issues_total'] }}</span>
                </div>
                <table class="status-table">
                    <thead>
                        <tr>
                            <th style="width: 42%;">البند</th>
                            <th style="width: 18%;">العدد</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>كتب بلا مرفقات</td><td>{{ $summary['without_attachments'] }}</td><td class="{{ $summary['without_attachments'] > 0 ? 'warn' : 'ok' }}">{{ $summary['without_attachments'] > 0 ? 'يحتاج مراجعة' : 'سليم
' }}</td></tr>
                        <tr><td>بوالص رئيسية مكررة</td><td>{{ $summary['duplicate_main_policies'] }}</td><td class="{{ $summary['duplicate_main_policies'] > 0 ? 'warn' : 'ok' }}">{{ $summary['duplicate_main_policies'] > 0 ? 'يحتاج مراجعة' : 'سليم
' }}</td></tr>
                        <tr><td>بوالص فرعية مكررة</td><td>{{ $summary['duplicate_sub_policies'] }}</td><td class="{{ $summary['duplicate_sub_policies'] > 0 ? 'warn' : 'ok' }}">{{ $summary['duplicate_sub_policies'] > 0 ? 'يحتاج مراجعة' : 'سليم
' }}</td></tr>
                        <tr><td>كتب بدون بوليصة رئيسية</td><td>{{ $summary['missing_main_policy'] }}</td><td class="{{ $summary['missing_main_policy'] > 0 ? 'warn' : 'ok' }}">{{ $summary['missing_main_policy'] > 0 ? 'يحتاج مراجعة' : 'سليم
' }}</td></tr>
                        <tr><td>كتب بدون بوليصة فرعية</td><td>{{ $summary['missing_sub_policy'] }}</td><td class="{{ $summary['missing_sub_policy'] > 0 ? 'warn' : 'ok' }}">{{ $summary['missing_sub_policy'] > 0 ? 'يحتاج مراجعة' : 'سليم
' }}</td></tr>
                        <tr><td>كتب في سلة المحذوفات</td><td>{{ $summary['trashed_documents'] }}</td><td class="{{ $summary['trashed_documents'] > 0 ? 'warn' : 'ok' }}">{{ $summary['trashed_documents'] > 0 ? 'يحتاج مراجعة' : 'سليم
' }}</td></tr>
                    </tbody>
                </table>
            </section>

            @if($summary['issues_total'] > 0)
                <div class="notice">توجد ملاحظات تحتاج مراجعة. هذا التقرير لا يمنع سير العمل، لكنه يساعد الإدارة على تنظيف بيانات الأرشيف قبل الاعتماد النهائي.</div>
            @else
                <div class="notice" style="border-color:#22c55e;background:#f0fdf4;color:#047857;">لا توجد ملاحظات مؤثرة حالياً. بيانات الأرشيف سليمة حسب الفحوصات الحالية.</div>
            @endif

            <section class="section">
                <div class="section-header">
                    <div>
                        <h3 class="section-title">كتب بلا مرفقات</h3>
                        <p class="section-desc">كتب تمإنشاؤها ولميتمرفع مرفق لها بعد.</p>
                    </div>
                    <span class="badge">{{ $withoutAttachments->count() }}</span>
                </div>
                @if($withoutAttachments->isEmpty())
                    <div class="empty">لا توجد كتب بلا مرفقات.</div>
                @else
                    @include('data-quality.partials.document-table', ['rows' => $withoutAttachments])
                @endif
            </section>

            <section class="section">
                <div class="section-header">
                    <div>
                        <h3 class="section-title">البوالص الرئيسية المكررة</h3>
                        <p class="section-desc">أرقامبوالص رئيسية مرتبطة بأكثر من كتاب.</p>
                    </div>
                    <span class="badge">{{ $duplicateMainPolicies->count() }}</span>
                </div>
                @include('data-quality.partials.duplicate-policy-table', ['rows' => $duplicateMainPolicies])
            </section>

            <section class="section">
                <div class="section-header">
                    <div>
                        <h3 class="section-title">البوالص الفرعية المكررة</h3>
                        <p class="section-desc">أرقامبوالص فرعية مرتبطة بأكثر من كتاب.</p>
                    </div>
                    <span class="badge">{{ $duplicateSubPolicies->count() }}</span>
                </div>
                @include('data-quality.partials.duplicate-policy-table', ['rows' => $duplicateSubPolicies])
            </section>

            <section class="section">
                <div class="section-header">
                    <div>
                        <h3 class="section-title">كتب بدون بوليصة رئيسية</h3>
                        <p class="section-desc">كتب لميتمإدخال رقمالبوليصة الرئيسية لها.</p>
                    </div>
                    <span class="badge">{{ $missingMainPolicy->count() }}</span>
                </div>
                @if($missingMainPolicy->isEmpty())
                    <div class="empty">لا توجد نتائج.</div>
                @else
                    @include('data-quality.partials.document-table', ['rows' => $missingMainPolicy])
                @endif
            </section>

            <section class="section">
                <div class="section-header">
                    <div>
                        <h3 class="section-title">كتب بدون بوليصة فرعية</h3>
                        <p class="section-desc">كتب لميتمإدخال رقمالبوليصة الفرعية لها.</p>
                    </div>
                    <span class="badge">{{ $missingSubPolicy->count() }}</span>
                </div>
                @if($missingSubPolicy->isEmpty())
                    <div class="empty">لا توجد نتائج.</div>
                @else
                    @include('data-quality.partials.document-table', ['rows' => $missingSubPolicy])
                @endif
            </section>

            <section class="section">
                <div class="section-header">
                    <div>
                        <h3 class="section-title">كتب في سلة المحذوفات</h3>
                        <p class="section-desc">كتب محذوفة مؤقتاً ويمكن مراجعتها من سلة المحذوفات.</p>
                    </div>
                    <span class="badge">{{ $trashedDocuments->count() }}</span>
                </div>
                @if($trashedDocuments->isEmpty())
                    <div class="empty">لا توجد كتب محذوفة حالياً.</div>
                @else
                    @include('data-quality.partials.document-table', ['rows' => $trashedDocuments])
                @endif
            </section>

            <footer class="report-footer">
                <span>{{ $systemTitle }}</span>
                <span>تمإنشاء التقرير آلياً من النظامبتاريخ {{ $generatedAt->format('Y-m-d H:i') }}</span>
            </footer>
        </article>
    </main>
</body>
</html>

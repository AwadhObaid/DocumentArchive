<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تقرير الكتب</title>
    <style>
        body {
            direction: rtl;
            text-align: right;
            font-family: dejavusans, sans-serif;
            font-size: 10pt;
            color: #111827;
            line-height: 1.55;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header h1 {
            margin: 0 0 5px;
            font-size: 22pt;
            font-weight: bold;
        }

        .subtitle {
            margin: 0;
            font-size: 10pt;
            color: #374151;
        }

        .meta {
            margin-top: 6px;
            font-size: 8.5pt;
            color: #4b5563;
        }

        .stats {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px;
            margin-bottom: 10px;
        }

        .stat-card {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px;
            width: 25%;
            vertical-align: top;
        }

        .stat-label {
            color: #4b5563;
            font-size: 8pt;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 18pt;
            font-weight: bold;
        }

        .two-cols {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 10px;
        }

        .box {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px;
            vertical-align: top;
            width: 50%;
        }

        h2 {
            font-size: 12pt;
            margin: 0 0 6px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .data-table th {
            background: #f3f4f6;
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
            font-size: 8.5pt;
        }

        .details-table {
            font-size: 8.3pt;
        }

        .details-table th:nth-child(1),
        .details-table td:nth-child(1) { width: 14%; }

        .details-table th:nth-child(2),
        .details-table td:nth-child(2) { width: 12%; }

        .details-table th:nth-child(3),
        .details-table td:nth-child(3) { width: 22%; }

        .details-table th:nth-child(4),
        .details-table td:nth-child(4) { width: 15%; }

        .details-table th:nth-child(5),
        .details-table td:nth-child(5) { width: 13%; }

        .details-table th:nth-child(6),
        .details-table td:nth-child(6) { width: 24%; }
    </style>
</head>
<body>
    <div class="header">
        <h1>التقارير</h1>
        <p class="subtitle">تقرير شام
ل للكتب والم
رفقات حسب الفلاتر الم
حددة</p>
        <div class="meta">
            <span>تاريخ التصدير: {{ now()->format('Y-m-d H:i') }}</span>
            <br>
            @if(count($filterSummary ?? []))
                <span>الفلاتر: {{ implode(' | ', $filterSummary) }}</span>
            @else
                <span>الفلاتر: كل البيانات</span>
            @endif
        </div>
    </div>

    <table class="stats">
        <tr>
            <td class="stat-card">
                <div class="stat-label">إجم
الي الكتب حسب الفلتر</div>
                <div class="stat-value">{{ number_format($stats['total_documents']) }}</div>
            </td>
            <td class="stat-card">
                <div class="stat-label">كتب هذا الشهر</div>
                <div class="stat-value">{{ number_format($stats['this_month']) }}</div>
            </td>
            <td class="stat-card">
                <div class="stat-label">إجم
الي الم
رفقات</div>
                <div class="stat-value">{{ number_format($stats['attachments_count']) }}</div>
            </td>
            <td class="stat-card">
                <div class="stat-label">كتب في سلة الم
حذوفات</div>
                <div class="stat-value">{{ number_format($stats['deleted_documents']) }}</div>
            </td>
        </tr>
    </table>

    <table class="two-cols">
        <tr>
            <td class="box">
                <h2>حسب الإدارة</h2>
                <table class="data-table">
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
            </td>
            <td class="box">
                <h2>حسب نوع الكتاب</h2>
                <table class="data-table">
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
            </td>
        </tr>
    </table>

    <h2>تفاصيل الكتب</h2>
    <table class="data-table details-table">
        <thead>
            <tr>
                <th>رقم
 الكتاب</th>
                <th>تاريخ الكتاب</th>
                <th>الم
وضوع</th>
                <th>الإدارة</th>
                <th>نوع الكتاب</th>
                <th>البوليصة</th>
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
                        رئيسية: {{ $document->main_policy_number ?? '-' }}<br>
                        فرعية: {{ $document->sub_policy_number ?? '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">لا توجد كتب م
طابقة للفلاتر الحالية.</td>
                </tr>
            @endforelse
        </tbody>
    </table>


</body>
</html>

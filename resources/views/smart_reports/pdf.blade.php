<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { direction: rtl; font-family: dejavusans, sans-serif; color: #1f2937; font-size: 11.5px; line-height: 1.75; background: #ffffff; }
        .cover { border: 1px solid #d8dee8; border-radius: 10px; padding: 18px 20px; margin-bottom: 14px; background: #f8fafc; }
        .brand { color: #64748b; font-size: 11px; margin-bottom: 4px; }
        h1 { font-size: 22px; color: #0f172a; margin: 0 0 6px; font-weight: bold; }
        .subtitle { color: #475569; margin: 0; font-size: 12px; }
        h2 { font-size: 15px; color: #0f172a; margin: 18px 0 8px; padding-bottom: 5px; border-bottom: 1px solid #dbeafe; }
        h3 { font-size: 13px; color: #1d4ed8; margin: 14px 0 6px; }
        h4 { font-size: 12px; color: #334155; margin: 12px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; }
        th, td { border: 1px solid #d8dee8; padding: 7px; text-align: right; vertical-align: top; }
        th { background: #eef6ff; color: #0f172a; font-weight: bold; }
        .meta th { width: 18%; }
        .meta td { width: 32%; }
        .kpis td { width: 33.333%; background: #ffffff; }
        .kpi-label { color: #64748b; font-size: 10.5px; margin-bottom: 3px; }
        .kpi-value { font-size: 20px; font-weight: bold; color: #0f172a; }
        .kpi-hint { color: #64748b; font-size: 10px; }
        .insights { background: #f8fafc; border: 1px solid #e5e7eb; padding: 9px 12px; border-radius: 8px; margin: 8px 0 12px; }
        .insights li { margin-bottom: 4px; }
        .smart-summary-table th:nth-child(2), .smart-summary-table td:nth-child(2) { width: 58px; text-align: center; }
        .smart-summary-table th:nth-child(3), .smart-summary-table td:nth-child(3) { width: 160px; }
        .smart-summary-bar { width: 150px; height: 8px; background: #e5e7eb; border-radius: 9px; overflow: hidden; }
        .smart-summary-bar span { display: block; height: 8px; background: #2563eb; border-radius: 9px; }
        .analysis { border: 1px solid #d8dee8; padding: 12px 14px; border-radius: 10px; background: #ffffff; }
        .analysis p { margin: 0 0 8px; }
        .analysis ul { margin: 6px 0 10px; padding-right: 18px; }
        .analysis li { margin-bottom: 5px; }
        .footer-note { color: #64748b; font-size: 10px; margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
@php
    $payload = is_array($report->payload_json ?? null) ? $report->payload_json : [];
    $summary = $payload['summary'] ?? [];
    $analytics = $payload['analytics'] ?? [];
    $charts = $analytics['charts'] ?? [];
    $kpis = $analytics['kpis'] ?? [];
    if ($kpis === []) {
        $total = (int) ($summary['documents_total'] ?? 0);
        $withAttachments = (int) ($summary['documents_with_attachments'] ?? 0);
        $withoutAttachments = (int) ($summary['documents_without_attachments'] ?? 0);
        $attachmentsTotal = (int) ($summary['attachments_total'] ?? 0);
        $completionRate = $total > 0 ? round(($withAttachments / $total) * 100, 1) . '%' : '0%';
        $kpis = [
            ['label' => 'إجمالي الكتب', 'value' => $total, 'hint' => 'عدد الكتب ضمن الفترة'],
            ['label' => 'الكتب بمرفقات', 'value' => $withAttachments, 'hint' => 'كتب تحتوي على مرفقات'],
            ['label' => 'الكتب بدون مرفقات', 'value' => $withoutAttachments, 'hint' => 'تحتاج مراجعة'],
            ['label' => 'إجمالي المرفقات', 'value' => $attachmentsTotal, 'hint' => 'مرفقات كتب الفترة'],
            ['label' => 'نسبة الاكتمال', 'value' => $completionRate, 'hint' => 'الكتب التي تحتوي على مرفقات'],
        ];
    }
    $chartSets = [
        'أكثر الشركات / الجهات' => $charts['companies'] ?? ($payload['top_companies'] ?? []),
        'توزيع أنواع العمليات' => $charts['operations'] ?? ($payload['top_operations'] ?? []),
        'حالة المرفقات' => $charts['attachment_status'] ?? [],
        'مؤشرات جودة البيانات' => $charts['quality'] ?? [],
        'أكثر مواضيع الكتب' => $charts['titles'] ?? ($payload['top_titles'] ?? []),
        'نشاط المستخدمين' => $charts['users_activity'] ?? ($payload['users_activity'] ?? []),
    ];
    $localInsights = $analytics['local_insights'] ?? [];
@endphp

    <div class="cover">
        <div class="brand">نظام أرشفة المستندات</div>
        <h1>{{ $report->title ?: 'تقرير ذكي تحليلي' }}</h1>
        <p class="subtitle">تقرير إداري تحليلي مبني على بيانات الكتب المسجلة في النظام.</p>
    </div>

    <table class="meta">
        <tr>
            <th>نوع التقرير</th>
            <td>{{ $report->report_type_name }}</td>
            <th>الفترة</th>
            <td>{{ optional($report->date_from)->format('Y-m-d') }} إلى {{ optional($report->date_to)->format('Y-m-d') }}</td>
        </tr>
        <tr>
            <th>تاريخ التوليد</th>
            <td>{{ $report->created_at?->format('Y-m-d H:i') }}</td>
            <th>مصدر البيانات</th>
            <td>قاعدة بيانات النظام</td>
        </tr>
    </table>

    <h2>المؤشرات الرئيسية</h2>
    <table class="kpis">
        @foreach(array_chunk($kpis, 3) as $row)
            <tr>
                @foreach($row as $kpi)
                    <td>
                        <div class="kpi-label">{{ $kpi['label'] ?? '' }}</div>
                        <div class="kpi-value">{{ $kpi['value'] ?? 0 }}</div>
                        <div class="kpi-hint">{{ $kpi['hint'] ?? '' }}</div>
                    </td>
                @endforeach
                @for($i = count($row); $i < 3; $i++)<td></td>@endfor
            </tr>
        @endforeach
    </table>

    @if(!empty($localInsights))
        <h2>ملاحظات تحليلية محلية</h2>
        <ul class="insights">
            @foreach($localInsights as $insight)
                <li>{{ $insight }}</li>
            @endforeach
        </ul>
    @endif

    <h2>ملخص الرسوم والمؤشرات البيانية</h2>
    @include('smart_reports.partials.chart-summary', [
        'smartChartSets' => $chartSets,
        'summaryClass' => 'smart-chart-summary-table',
        'maxRows' => 8,
    ])

    <h2>التحليل الذكي والتوصيات</h2>
    <div class="analysis">{!! \App\Support\SmartReportTextFormatter::toHtml($report->result_text) !!}</div>

    <div class="footer-note">تم إنشاء هذا التقرير من داخل نظام أرشفة المستندات. لا يتضمن هذا التقرير أي مفاتيح API أو بيانات تقنية داخلية.</div>
</body>
</html>

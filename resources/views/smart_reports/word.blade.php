<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title }}</title>
    <style>
        body { direction: rtl; font-family: Tahoma, Arial, sans-serif; line-height: 1.9; color: #111827; }
        h1 { font-size: 22px; text-align: center; color: #0f172a; }
        .meta { border: 1px solid #d1d5db; padding: 10px; margin: 14px 0; background: #f9fafb; }
        pre { white-space: pre-wrap; font-family: Tahoma, Arial, sans-serif; font-size: 14px; line-height: 1.9; }
    </style>
</head>
<body>
    <h1>{{ $report->title }}</h1>
    <div class="meta">
        <div>نوع التقرير: {{ $report->report_type_name }}</div>
        <div>الفترة: {{ optional($report->date_from)->format('Y-m-d') }} إلى {{ optional($report->date_to)->format('Y-m-d') }}</div>
        <div>تاريخ التوليد: {{ $report->created_at?->format('Y-m-d H:i') }}</div>
        <div>الموديل: {{ $report->model }}</div>
    </div>
    <pre>{{ $report->result_text }}</pre>
</body>
</html>

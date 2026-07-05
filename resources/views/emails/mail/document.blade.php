<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Tahoma, Arial, sans-serif; direction: rtl; text-align: right; line-height: 1.8; color: #111827; }
        .box { border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; margin: 14px 0; background: #f9fafb; }
        .meta { width: 100%; border-collapse: collapse; }
        .meta th, .meta td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: right; }
        .meta th { width: 160px; color: #475569; }
    </style>
</head>
<body>
    <div style="white-space:pre-wrap;">{{ $bodyText }}</div>

    @if($document)
        <div class="box">
            <strong>ملخص بيانات الكتاب</strong>
            <table class="meta">
                <tbody>
                    <tr><th>رقم الكتاب</th><td>{{ $document->reference_number }}</td></tr>
                    <tr><th>تاريخ الكتاب</th><td>{{ optional($document->reference_date)->format('d/m/Y') ?: '-' }}</td></tr>
                    <tr><th>الموضوع</th><td>{{ $document->subject ?: $document->title ?: '-' }}</td></tr>
                    <tr><th>الإدارة</th><td>{{ $document->department?->name ?? '-' }}</td></tr>
                    <tr><th>نوع الكتاب</th><td>{{ $document->documentType?->name ?? '-' }}</td></tr>
                    <tr><th>المرسل</th><td>{{ $document->sender ?: '-' }}</td></tr>
                    <tr><th>المستلم</th><td>{{ $document->receiver ?: '-' }}</td></tr>
                    <tr><th>البوليصة الرئيسية</th><td>{{ $document->main_policy_number ?: '-' }}</td></tr>
                    <tr><th>البوليصة الفرعية</th><td>{{ $document->sub_policy_number ?: '-' }}</td></tr>
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($memo))
        <div class="box">
            <strong>ملخص بيانات المذكرة</strong>
            <table class="meta">
                <tbody>
                    <tr><th>رقم المذكرة</th><td>{{ $memo->memo_number }}</td></tr>
                    <tr><th>تاريخ المذكرة</th><td>{{ optional($memo->memo_date)->format('d/m/Y') ?: '-' }}</td></tr>
                    <tr><th>الموضوع</th><td>{{ $memo->subject ?: '-' }}</td></tr>
                    <tr><th>الإدارة</th><td>{{ $memo->department?->name ?? '-' }}</td></tr>
                    <tr><th>الواردة من</th><td>{{ $memo->sender ?: '-' }}</td></tr>
                    <tr><th>المستلم</th><td>{{ $memo->receiver ?: '-' }}</td></tr>
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>

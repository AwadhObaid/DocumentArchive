<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طباعة رقم الكتاب {{ $document->reference_number }}</title>

    <style>
        @page { size: A4; margin: 0; }

        html, body {
            margin: 0;
            padding: 0;
            background: #f3f4f6;
            font-family: Arial, Tahoma, sans-serif;
            direction: ltr;
        }

        .page {
            width: 210mm;
            height: 297mm;
            position: relative;
            background: white;
            margin: 10mm auto;
            direction: ltr;
            box-shadow: 0 0 0 1px #d1d5db;
        }

        .print-box {
            position: absolute;
            top: {{ $document->print_top_mm }}mm;
            left: {{ $document->print_left_mm }}mm;
            width: 50mm;
            color: #000;
            font-size: {{ \App\Models\Setting::getValue('print_font_size_pt', 12) }}pt;
            font-weight: bold;
            direction: rtl;
        }

        .print-title {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 3mm;
        }

        .print-row {
            display: grid;
            grid-template-columns: 27mm 4mm 19mm;
            direction: ltr;
            align-items: center;
            line-height: 1.35;
            margin-bottom: 1.5mm;
        }

        .value { direction: ltr; text-align: left; font-weight: bold; }
        .separator { text-align: center; font-weight: bold; }
        .label { direction: rtl; text-align: right; font-weight: bold; }

        .screen-actions {
            position: fixed;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 8px;
            z-index: 999;
        }

        .screen-actions button,
        .screen-actions a {
            padding: 8px 12px;
            border-radius: 8px;
            border: 0;
            background: #2563eb;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 14px;
        }

        .screen-actions a { background: #6b7280; }

        @media print {
            html, body { background: white; width: 210mm; height: 297mm; }
            .page { margin: 0; box-shadow: none; width: 210mm; height: 297mm; }
            .screen-actions { display: none; }
        }
    </style>
</head>
<body>

<div class="screen-actions">
    <button onclick="window.print()">طباعة</button>
    <a href="{{ route('documents.show', $document) }}">رجوع</a>
</div>

<div class="page">
    <div class="print-box">
        <div class="print-title">{{ $document->print_title }}</div>

        <div class="print-row">
            <div class="value">{{ $document->reference_number }}</div>
            <div class="separator">:</div>
            <div class="label">رقم الكتاب</div>
        </div>

        <div class="print-row">
            <div class="value">{{ $document->reference_date->format('d/m/Y') }}</div>
            <div class="separator">:</div>
            <div class="label">تاريخ الكتاب</div>
        </div>
    </div>
</div>

</body>
</html>

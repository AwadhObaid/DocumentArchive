@php
    $doc = $document ?? null;
    $referenceNumber = data_get($doc, 'reference_number', data_get($doc, 'document_no', ''));
    $referenceDateRaw = data_get($doc, 'reference_date', data_get($doc, 'date', null));

    try {
        $referenceDate = $referenceDateRaw ? \Carbon\Carbon::parse($referenceDateRaw)->format('d/m/Y') : '';
    } catch (\Throwable $e) {
        $referenceDate = (string) $referenceDateRaw;
    }

    $setting = function (string $key, $default = null) {
        try {
            if (class_exists(\App\Models\Setting::class)) {
                return \App\Models\Setting::getValue($key, $default);
            }
        } catch (\Throwable $e) {
            return $default;
        }
        return $default;
    };

    $departmentTitle = data_get($doc, 'print_title')
        ?: $setting('print_department_title', data_get($doc, 'department.name') ?: 'الشحن والتأمين');

    $topMm = (float) (data_get($doc, 'print_top_mm') ?: $setting('print_top_mm', '53.30'));
    $leftMm = (float) (data_get($doc, 'print_left_mm') ?: $setting('print_left_mm', '30.80'));
    $fontSizePt = (float) $setting('print_font_size_pt', '12');
    $docId = data_get($doc, 'id');
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة رقم الكتاب {{ $referenceNumber }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #eef0f4;
            font-family: Tahoma, Arial, sans-serif;
            color: #000;
        }
        .da-toolbar {
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 20;
            display: flex;
            gap: 8px;
        }
        .da-btn {
            border: 0;
            border-radius: 8px;
            padding: 8px 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
            font-size: 13px;
        }
        .da-btn-print { background: #2563eb; }
        .da-btn-back { background: #6b7280; }
        .da-a4-page {
            width: 210mm;
            height: 297mm;
            margin: 10mm auto;
            background: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 0 0 1px #d1d5db;
        }
        .reference-print-block {
            position: absolute;
            top: {{ $topMm }}mm;
            left: {{ $leftMm }}mm;
            width: 58mm;
            direction: rtl;
            text-align: right;
            font-weight: 700;
            color: #000;
            font-size: {{ $fontSizePt }}pt;
            line-height: 1.35;
        }
        .reference-print-title {
            text-align: center;
            margin: 0 0 3mm;
            font-size: {{ max(8, $fontSizePt - 1) }}pt;
            font-weight: 700;
        }
        .reference-row {
            display: grid;
            grid-template-columns: 24mm 4mm 30mm;
            align-items: baseline;
            margin-bottom: 1.6mm;
            white-space: nowrap;
        }
        .reference-label { text-align: right; direction: rtl; }
        .reference-colon { text-align: center; }
        .reference-value { text-align: left; direction: ltr; }
        @media print {
            html, body { background: #fff; }
            .da-toolbar { display: none !important; }
            .da-a4-page { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body class="print-reference-page no-qr-print-page">
    <div class="da-toolbar">
        <button class="da-btn da-btn-print" onclick="window.print()">طباعة</button>
        <a class="da-btn da-btn-back" href="{{ $docId ? url('/documents/' . $docId) : url('/documents') }}">رجوع</a>
    </div>

    <main class="da-a4-page">
        <section class="reference-print-block">
            <div class="reference-print-title">{{ $departmentTitle }}</div>
            <div class="reference-row">
                <div class="reference-label">رقم الكتاب</div>
                <div class="reference-colon">:</div>
                <div class="reference-value">{{ $referenceNumber }}</div>
            </div>
            <div class="reference-row">
                <div class="reference-label">تاريخ الكتاب</div>
                <div class="reference-colon">:</div>
                <div class="reference-value">{{ $referenceDate }}</div>
            </div>
        </section>
    </main>
</body>
</html>
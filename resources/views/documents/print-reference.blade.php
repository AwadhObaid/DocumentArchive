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

    /*
     * PRINT_SETTINGS_LIVE_BINDING_V81_8
     *
     * Global print settings are the source of truth for every book.
     * Per-document values remain only as legacy fallbacks when a global
     * setting is genuinely absent.
     */
    $departmentTitle = (string) $setting(
        'print_department_title',
        data_get($doc, 'print_title')
            ?: (data_get($doc, 'department.name') ?: 'الشحن والتأمين')
    );

    $fontFamilyKey = (string) $setting('print_font_family', 'Cairo');
    $fontFamilyMap = [
        'Cairo' => '"Cairo", "Tajawal", Tahoma, Arial, sans-serif',
        'Tajawal' => '"Tajawal", "Cairo", Tahoma, Arial, sans-serif',
        'Arial' => 'Arial, Tahoma, sans-serif',
        'Tahoma' => 'Tahoma, Arial, sans-serif',
        'Traditional Arabic' => '"Traditional Arabic", "Times New Roman", Tahoma, serif',
        'Amiri' => '"Amiri", "Traditional Arabic", "Times New Roman", serif',
    ];
    $printFontFamilyCss = $fontFamilyMap[$fontFamilyKey] ?? $fontFamilyMap['Cairo'];

    $topMm = (float) $setting(
        'print_top_mm',
        data_get($doc, 'print_top_mm', '32')
    );
    $leftMm = (float) $setting(
        'print_left_mm',
        data_get($doc, 'print_left_mm', '32')
    );
    $fontSizePt = (float) $setting('print_font_size_pt', '10.2');
    $departmentFontSizePt = (float) $setting('print_department_font_size_pt', '10.8');
    $labelWidthMm = (float) $setting('print_label_width_mm', '18');
    $colonWidthMm = (float) $setting('print_colon_width_mm', '1');
    $valueWidthMm = (float) $setting('print_value_width_mm', '24');
    $columnGapMm = (float) $setting('print_column_gap_mm', '0');
    $titleGapMm = (float) $setting('print_title_gap_mm', '0.6');
    $rowGapMm = (float) $setting('print_row_gap_mm', '0.25');
    $blockWidthMm = $labelWidthMm + $colonWidthMm + $valueWidthMm + ($columnGapMm * 2);
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

        html,
        body {
            margin: 0;
            padding: 0;
            background: #eef0f4;
            color: #000;
            font-family: {!! $printFontFamilyCss !!};
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .da-toolbar {
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 20;
            display: flex;
            gap: 8px;
            direction: rtl;
        }

        .da-btn {
            border: 0;
            border-radius: 9px;
            padding: 8px 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
            font-size: 13px;
            line-height: 1;
            font-family: inherit;
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
            width: {{ $blockWidthMm }}mm;
            direction: rtl;
            text-align: right;
            color: #000;
            font-size: {{ $fontSizePt }}pt;
            font-family: {!! $printFontFamilyCss !!};
            font-weight: 700;
            line-height: 1.08;
            white-space: nowrap;
        }

        .reference-print-title {
            text-align: center;
            margin: 0 0 {{ $titleGapMm }}mm;
            font-size: {{ $departmentFontSizePt }}pt;
            font-weight: 800;
            letter-spacing: 0;
            line-height: 1.05;
        }

        .reference-row {
            display: grid;
            grid-template-columns: {{ $labelWidthMm }}mm {{ $colonWidthMm }}mm {{ $valueWidthMm }}mm;
            column-gap: {{ $columnGapMm }}mm;
            align-items: baseline;
            direction: rtl;
            margin-bottom: {{ $rowGapMm }}mm;
            white-space: nowrap;
        }

        .reference-label {
            text-align: right;
            direction: rtl;
            font-weight: 700;
        }

        .reference-colon {
            text-align: center;
            font-weight: 800;
            padding: 0;
        }

        .reference-value {
            text-align: left;
            direction: ltr;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            font-weight: 700;
            letter-spacing: 0;
        }

        @media print {
            html,
            body {
                width: 210mm;
                height: 297mm;
                background: #fff;
            }

            .da-toolbar { display: none !important; }

            .da-a4-page {
                margin: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body class="print-reference-page no-qr-print-page">
    <div class="da-toolbar">
        <button class="da-btn da-btn-print" type="button" onclick="window.print()">طباعة</button>
        <a class="da-btn da-btn-back" href="{{ $docId ? url('/documents/' . $docId) : url('/documents') }}">رجوع</a>
    </div>

    <main class="da-a4-page"
          aria-label="صفحة طباعة رقم الكتاب"
          data-print-settings-source="global"
          data-print-top-mm="{{ $topMm }}"
          data-print-left-mm="{{ $leftMm }}">
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
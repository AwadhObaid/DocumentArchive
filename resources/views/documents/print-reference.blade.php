@php
    $doc = $document ?? ($daQrDocument ?? null);
    $docId = data_get($doc, 'id');
    $referenceNumber = data_get($doc, 'reference_number', data_get($doc, 'document_no', ''));
    $departmentName = data_get($doc, 'department.name') ?: data_get($doc, 'department_name') ?: data_get($doc, 'department') ?: 'الشحن والتأمين';
    $referenceDateRaw = data_get($doc, 'reference_date', data_get($doc, 'date', null));

    try {
        $referenceDate = $referenceDateRaw ? \Carbon\Carbon::parse($referenceDateRaw)->format('d/m/Y') : '';
    } catch (\Throwable $e) {
        $referenceDate = (string) $referenceDateRaw;
    }

    $settings = [];
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('qr_print_settings')) {
            $settings = \Illuminate\Support\Facades\DB::table('qr_print_settings')->pluck('value', 'key')->toArray();
        }
    } catch (\Throwable $e) {
        $settings = [];
    }

    $setting = function (string $key, $default = null) use ($settings) {
        return array_key_exists($key, $settings) && $settings[$key] !== '' && $settings[$key] !== null
            ? $settings[$key]
            : $default;
    };

    $boolSetting = function (string $key, bool $default = true) use ($setting) {
        $value = $setting($key, $default ? '1' : '0');
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $bool === null ? ((string) $value !== '0') : $bool;
    };

    $num = function (string $key, $default) use ($setting) {
        return (float) $setting($key, $default);
    };

    $showQr = $boolSetting('qr_enabled', true);
    $showLabel = $boolSetting('qr_label_enabled', true);
    $qrX = $num('qr_x_mm', 70);
    $qrY = $num('qr_y_mm', 28);
    $qrSize = $num('qr_size_mm', 16);
    $qrPadding = $num('qr_card_padding_mm', 1);
    $qrLabelText = trim((string) $setting('qr_label_text', 'رمز الوصول الإلكتروني'));
    $qrLabelFont = $num('qr_label_font_size_mm', 2.1);
    $qrBackground = $boolSetting('qr_background_enabled', true);
    $qrBorder = $boolSetting('qr_show_border', true);

    $blockX = $num('print_block_x_mm', 106);
    $blockY = $num('print_block_y_mm', 28);
    $fontSize = $num('print_font_size_pt', 10.5);
    $departmentFontSize = $num('print_department_font_size_pt', 11);
    $lineHeight = $num('print_line_height', 1.35);
    $labelWidth = $num('print_label_width_mm', 27);
    $colonWidth = $num('print_colon_width_mm', 4);
    $valueWidth = $num('print_value_width_mm', 36);
    $blockWidth = $labelWidth + $colonWidth + $valueWidth + 4;

    $qrUrl = $docId ? url('/documents/' . $docId . '/qr.svg') : '';
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
            color: #111827;
        }
        .da-toolbar {
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 10;
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
        .da-reference-block {
            position: absolute;
            left: {{ $blockX }}mm;
            top: {{ $blockY }}mm;
            width: {{ $blockWidth }}mm;
            font-size: {{ $fontSize }}pt;
            font-weight: 700;
            line-height: {{ $lineHeight }};
            text-align: right;
            direction: rtl;
            color: #111827;
        }
        .da-dept {
            text-align: center;
            margin: 0 0 1.8mm;
            font-size: {{ $departmentFontSize }}pt;
            font-weight: 800;
            line-height: 1.25;
        }
        .da-reference-table {
            border-collapse: collapse;
            width: 100%;
        }
        .da-reference-table th,
        .da-reference-table td {
            padding: 0.6mm 0;
            border: 0;
            background: transparent;
            color: #111827;
            font-size: {{ $fontSize }}pt;
            font-weight: 800;
            line-height: {{ $lineHeight }};
            white-space: nowrap;
        }
        .da-reference-table th {
            width: {{ $labelWidth }}mm;
            text-align: right;
        }
        .da-reference-table .da-colon {
            width: {{ $colonWidth }}mm;
            text-align: center;
        }
        .da-reference-table .da-value {
            width: {{ $valueWidth }}mm;
            text-align: left;
            direction: ltr;
        }
        .da-print-qr-only {
            position: absolute;
            left: {{ $qrX }}mm;
            top: {{ $qrY }}mm;
            width: {{ $qrSize + ($qrPadding * 2) }}mm;
            min-height: {{ $qrSize + ($qrPadding * 2) + 5 }}mm;
            padding: {{ $qrPadding }}mm;
            background: {{ $qrBackground ? '#fff' : 'transparent' }};
            border: {{ $qrBorder ? '0.25mm solid #d1d5db' : '0' }};
            border-radius: 2mm;
            text-align: center;
            direction: rtl;
        }
        .da-print-qr-only img {
            display: block;
            width: {{ $qrSize }}mm;
            height: {{ $qrSize }}mm;
            margin: 0 auto;
            object-fit: contain;
        }
        .da-print-qr-label {
            margin-top: 0.8mm;
            font-size: {{ $qrLabelFont }}mm;
            color: #555;
            line-height: 1.15;
            white-space: nowrap;
            font-weight: 400;
        }
        @media print {
            html, body { background: #fff; }
            .da-toolbar { display: none !important; }
            .da-a4-page { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body class="da-print-reference-page">
    <div class="da-toolbar">
        <button class="da-btn da-btn-print" onclick="window.print()">طباعة</button>
        <a class="da-btn da-btn-back" href="{{ $docId ? url('/documents/' . $docId) : url('/documents') }}">رجوع</a>
    </div>

    <main class="da-a4-page">
        <section class="da-reference-block">
            <div class="da-dept">{{ $departmentName }}</div>
            <table class="da-reference-table">
                <tr>
                    <th>رقم الكتاب</th>
                    <td class="da-colon">:</td>
                    <td class="da-value">{{ $referenceNumber }}</td>
                </tr>
                <tr>
                    <th>تاريخ الكتاب</th>
                    <td class="da-colon">:</td>
                    <td class="da-value">{{ $referenceDate }}</td>
                </tr>
            </table>
        </section>

        @if($showQr && $qrUrl)
            <section class="da-print-qr-only" data-da-print-qr="1">
                <img src="{{ $qrUrl }}" alt="رمز الوصول الإلكتروني">
                @if($showLabel && $qrLabelText !== '')
                    <div class="da-print-qr-label">{{ $qrLabelText }}</div>
                @endif
            </section>
        @endif
    </main>
</body>
</html>
@php
    $doc = $document ?? ($daQrDocument ?? null);
    $docId = data_get($doc, 'id');
    $referenceNumber = data_get($doc, 'reference_number', data_get($doc, 'document_no', ''));
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
    } catch (\Throwable $e) { $settings = []; }

    $setting = function ($keys, $default = null) use ($settings) {
        foreach ((array) $keys as $key) {
            if (array_key_exists($key, $settings) && $settings[$key] !== '' && $settings[$key] !== null) {
                return $settings[$key];
            }
        }
        return $default;
    };

    $qrX = (float) $setting(['x', 'qr_x', 'qr_print_x', 'qr_position_x', 'position_x'], 55);
    $qrY = (float) $setting(['y', 'qr_y', 'qr_print_y', 'qr_position_y', 'position_y'], 48);
    $qrSize = (float) $setting(['size', 'qr_size', 'qr_print_size'], 16);
    $qrPadding = (float) $setting(['padding', 'qr_padding', 'qr_print_padding'], 1);
    $qrTextSize = (float) $setting(['text_size', 'qr_text_size', 'label_size'], 7);
    $showQr = filter_var($setting(['show_qr', 'qr_show', 'enabled'], true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $showQr = $showQr === null ? true : $showQr;
    $showLabel = filter_var($setting(['show_label', 'qr_show_label', 'label_enabled'], true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $showLabel = $showLabel === null ? true : $showLabel;
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
        html, body { margin: 0; padding: 0; background: #eef0f4; font-family: Tahoma, Arial, sans-serif; color: #000; }
        .da-toolbar { position: fixed; top: 10px; left: 10px; z-index: 10; display: flex; gap: 8px; }
        .da-btn { border: 0; border-radius: 8px; padding: 9px 16px; font-weight: 700; cursor: pointer; text-decoration: none; color: #fff; font-size: 14px; }
        .da-btn-print { background: #2563eb; }
        .da-btn-back { background: #6b7280; }
        .da-a4-page { width: 210mm; height: 297mm; margin: 10mm auto; background: #fff; position: relative; overflow: hidden; box-shadow: 0 0 0 1px #d1d5db; }
        .da-reference-block { position: absolute; top: 49mm; right: 42mm; min-width: 74mm; font-size: 13pt; font-weight: 700; line-height: 1.65; text-align: right; }
        .da-dept { text-align: center; margin-bottom: 2mm; font-size: 12pt; }
        .da-row { display: grid; grid-template-columns: 28mm 5mm 38mm; gap: 2mm; align-items: center; }
        .da-label { text-align: right; }
        .da-colon { text-align: center; }
        .da-value { text-align: left; direction: ltr; }
        .da-print-qr-only { position: absolute; left: {{ $qrX }}mm; top: {{ $qrY }}mm; width: {{ $qrSize + ($qrPadding * 2) }}mm; min-height: {{ $qrSize + ($qrPadding * 2) + 6 }}mm; padding: {{ $qrPadding }}mm; background: #fff; border: .25mm solid #d1d5db; border-radius: 2mm; text-align: center; }
        .da-print-qr-only img { display: block; width: {{ $qrSize }}mm; height: {{ $qrSize }}mm; margin: 0 auto; object-fit: contain; }
        .da-print-qr-label { margin-top: 1mm; font-size: {{ $qrTextSize }}pt; color: #555; line-height: 1.2; white-space: nowrap; }
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
            <div class="da-dept">الشحن والتأمين</div>
            <div class="da-row">
                <span class="da-label">رقم الكتاب</span>
                <span class="da-colon">:</span>
                <span class="da-value">{{ $referenceNumber }}</span>
            </div>
            <div class="da-row">
                <span class="da-label">تاريخ الكتاب</span>
                <span class="da-colon">:</span>
                <span class="da-value">{{ $referenceDate }}</span>
            </div>
        </section>

        @if($showQr && $qrUrl)
            <section class="da-print-qr-only" data-da-print-qr="1">
                <img src="{{ $qrUrl }}" alt="رمز الوصول الإلكتروني">
                @if($showLabel)
                    <div class="da-print-qr-label">رمز الوصول الإلكتروني</div>
                @endif
            </section>
        @endif
    </main>
</body>
</html>
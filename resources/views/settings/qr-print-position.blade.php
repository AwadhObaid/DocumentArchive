<div class="da-document-print-page da-document-print-page-marker">
{{-- QR_PRINT_SETTINGS_BIND_START --}}
@php
    try {
        $daQrSettings = \Illuminate\Support\Facades\Schema::hasTable('qr_print_settings')
            ? \Illuminate\Support\Facades\DB::table('qr_print_settings')->pluck('value', 'key')->toArray()
            : [];
    } catch (\Throwable $e) {
        $daQrSettings = [];
    }

    $daQrSetting = function (string $key, $default = null) use ($daQrSettings) {
        return array_key_exists($key, $daQrSettings) ? $daQrSettings[$key] : $default;
    };

    $daQrEnabledRaw = $daQrSetting('enabled', $daQrSetting('show_qr', '1'));
    $daQrEnabled = filter_var($daQrEnabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $daQrEnabled = $daQrEnabled === null ? ((string) $daQrEnabledRaw !== '0') : $daQrEnabled;

    $daQrShowLabelRaw = $daQrSetting('show_label', '1');
    $daQrShowLabel = filter_var($daQrShowLabelRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $daQrShowLabel = $daQrShowLabel === null ? ((string) $daQrShowLabelRaw !== '0') : $daQrShowLabel;

    $daQrX = (float) $daQrSetting('x_mm', $daQrSetting('qr_x_mm', '74'));
    $daQrY = (float) $daQrSetting('y_mm', $daQrSetting('qr_y_mm', '48'));
    $daQrSize = (float) $daQrSetting('size_mm', $daQrSetting('qr_size_mm', '18'));
    $daQrPadding = (float) $daQrSetting('padding_mm', $daQrSetting('qr_padding_mm', '1.5'));
    $daQrLabelSize = (float) $daQrSetting('label_font_size_pt', $daQrSetting('qr_label_font_size_pt', '6'));
    $daQrDocument = $document ?? $book ?? null;
@endphp
<style id="da-reference-print-qr-settings-style">
    @media screen, print {
        .print-page,
        .print-paper,
        .paper,
        .a4-page,
        .a4-sheet,
        .document-print-page,
        .document-print-paper,
        .page {
            position: relative !important;
        }

        .document-qr-card,
        .da-reference-print-qr-card,
        .document-qr-side,
        .da-reference-print-qr-side,
        .qr-print-block,
        .qr-print-box:not(.da-qr-from-settings),
        .da-qr-positioned:not(.da-qr-from-settings),
        .da-reference-print-qr-side:not(.da-qr-from-settings) {
            display: none !important;
        }

        .da-qr-from-settings {
            position: absolute !important;
            left: {{ rtrim(rtrim(number_format($daQrX, 2, '.', ''), '0'), '.') }}mm !important;
            top: {{ rtrim(rtrim(number_format($daQrY, 2, '.', ''), '0'), '.') }}mm !important;
            width: {{ rtrim(rtrim(number_format($daQrSize + ($daQrPadding * 2), 2, '.', ''), '0'), '.') }}mm !important;
            min-height: {{ rtrim(rtrim(number_format($daQrSize + ($daQrPadding * 2), 2, '.', ''), '0'), '.') }}mm !important;
            box-sizing: border-box !important;
            padding: {{ rtrim(rtrim(number_format($daQrPadding, 2, '.', ''), '0'), '.') }}mm !important;
            background: #fff !important;
            border: 0.25mm solid #d5dbe7 !important;
            border-radius: 2mm !important;
            z-index: 50 !important;
            text-align: center !important;
            direction: rtl !important;
            line-height: 1.1 !important;
            box-shadow: none !important;
        }

        .da-qr-from-settings img,
        .da-qr-from-settings svg {
            display: block !important;
            width: {{ rtrim(rtrim(number_format($daQrSize, 2, '.', ''), '0'), '.') }}mm !important;
            height: {{ rtrim(rtrim(number_format($daQrSize, 2, '.', ''), '0'), '.') }}mm !important;
            max-width: none !important;
            max-height: none !important;
            margin: 0 auto !important;
        }

        .da-qr-from-settings-label {
            display: block !important;
            margin-top: 1mm !important;
            font-size: {{ rtrim(rtrim(number_format($daQrLabelSize, 2, '.', ''), '0'), '.') }}pt !important;
            color: #4b5563 !important;
            white-space: nowrap !important;
        }
    }
</style>
{{-- QR_PRINT_SETTINGS_BIND_END --}}
@extends('layouts.app')

@section('title', 'ضبط موضع رمز QR')

@section('content')
@php
    $s = $settings;
@endphp

<style>
.qr-settings-grid {
    display: grid;
    grid-template-columns: minmax(280px, 420px) 1fr;
    gap: 18px;
    align-items: start;
}
.qr-settings-card {
    background: var(--card-bg, #111827);
    border: 1px solid rgba(148, 163, 184, .25);
    border-radius: 18px;
    padding: 18px;
    color: var(--text-color, #e5e7eb);
}
.qr-settings-card h2, .qr-settings-card h3 {
    margin: 0 0 14px;
}
.qr-field {
    margin-bottom: 13px;
}
.qr-field label {
    display: block;
    font-weight: 700;
    margin-bottom: 6px;
}
.qr-field input[type="number"],
.qr-field input[type="text"] {
    width: 100%;
    border: 1px solid rgba(148, 163, 184, .35);
    border-radius: 10px;
    padding: 10px 12px;
    background: rgba(15, 23, 42, .75);
    color: #fff;
}
.qr-field small {
    display: block;
    opacity: .75;
    margin-top: 4px;
}
.qr-checks {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.qr-checks label {
    border: 1px solid rgba(148, 163, 184, .25);
    border-radius: 12px;
    padding: 10px;
}
.qr-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.qr-preview-wrap {
    overflow: auto;
}
.qr-preview-paper {
    position: relative;
    width: 210mm;
    height: 297mm;
    max-width: 100%;
    aspect-ratio: 210 / 297;
    background: #fff;
    color: #111827;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 10px 35px rgba(0,0,0,.25);
    transform-origin: top right;
}
.qr-preview-reference {
    position: absolute;
    top: 48mm;
    right: 63mm;
    font-weight: 800;
    font-size: 14px;
    line-height: 1.8;
    direction: rtl;
}
.qr-preview-box {
    position: absolute;
    left: calc(var(--preview-x, 82) * 1mm);
    top: calc(var(--preview-y, 50) * 1mm);
    width: calc((var(--preview-size, 20) + (var(--preview-padding, 2) * 2)) * 1mm);
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: calc(var(--preview-padding, 2) * 1mm);
    text-align: center;
    box-sizing: border-box;
}
.qr-preview-code {
    width: calc(var(--preview-size, 20) * 1mm);
    height: calc(var(--preview-size, 20) * 1mm);
    margin: auto;
    background:
        linear-gradient(90deg,#111 50%,transparent 50%) 0 0 / 4px 4px,
        linear-gradient(#111 50%,transparent 50%) 0 0 / 6px 6px,
        #fff;
    image-rendering: pixelated;
}
.qr-preview-label {
    font-size: calc(var(--preview-label-size, 2.6) * 1mm);
    margin-top: 1.2mm;
    color: #64748b;
    white-space: nowrap;
}
@media (max-width: 900px) {
    .qr-settings-grid { grid-template-columns: 1fr; }
}
</style>

<div class="page-header">
{{-- QR_PRINT_SETTINGS_MARKUP_START --}}
@if($daQrEnabled && $daQrDocument)
    <div class="da-qr-from-settings">
        <img src="{{ url('/documents/' . $daQrDocument->id . '/qr.svg') }}" alt="QR Code">
        @if($daQrShowLabel)
            <span class="da-qr-from-settings-label">رمز الوصول الإلكتروني</span>
        @endif
    </div>
@endif
{{-- QR_PRINT_SETTINGS_MARKUP_END --}}

    <h1>🎯 ضبط موضع رمز QR في طباعة رقم الكتاب</h1>
    <p>استخدم القيم بالملليمتر لضبط موضع الرمز بدقة داخل ورقة A4.</p>
</div>

<div class="qr-settings-grid">
    <form class="qr-settings-card" method="POST" action="{{ route('settings.qr-print-position.update') }}">
        @csrf

        <h2>إعدادات الموضع</h2>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="qr-checks">
            <label>
                <input type="checkbox" name="qr_enabled" value="1" @checked(($s['qr_enabled'] ?? '1') === '1')>
                إظهار QR في الطباعة
            </label>
            <label>
                <input type="checkbox" name="qr_label_enabled" value="1" @checked(($s['qr_label_enabled'] ?? '1') === '1')>
                إظهار النص أسفل QR
            </label>
            <label>
                <input type="checkbox" name="qr_background_enabled" value="1" @checked(($s['qr_background_enabled'] ?? '1') === '1')>
                خلفية بيضاء للرمز
            </label>
            <label>
                <input type="checkbox" name="qr_show_border" value="1" @checked(($s['qr_show_border'] ?? '1') === '1')>
                إطار حول الرمز
            </label>
        </div>

        <div class="qr-field">
            <label>المسافة من يسار الورقة X / mm</label>
            <input class="js-qr-preview" data-var="x" type="number" step="0.5" min="0" max="210" name="qr_x_mm" value="{{ old('qr_x_mm', $s['qr_x_mm'] ?? 82) }}">
            <small>كلما زادت القيمة تحرك الرمز إلى اليمين.</small>
        </div>

        <div class="qr-field">
            <label>المسافة من أعلى الورقة Y / mm</label>
            <input class="js-qr-preview" data-var="y" type="number" step="0.5" min="0" max="297" name="qr_y_mm" value="{{ old('qr_y_mm', $s['qr_y_mm'] ?? 50) }}">
            <small>كلما زادت القيمة نزل الرمز إلى الأسفل.</small>
        </div>

        <div class="qr-field">
            <label>حجم رمز QR / mm</label>
            <input class="js-qr-preview" data-var="size" type="number" step="0.5" min="8" max="45" name="qr_size_mm" value="{{ old('qr_size_mm', $s['qr_size_mm'] ?? 20) }}">
        </div>

        <div class="qr-field">
            <label>هوامش البطاقة حول الرمز / mm</label>
            <input class="js-qr-preview" data-var="padding" type="number" step="0.5" min="0" max="8" name="qr_card_padding_mm" value="{{ old('qr_card_padding_mm', $s['qr_card_padding_mm'] ?? 2) }}">
        </div>

        <div class="qr-field">
            <label>نص أسفل الرمز</label>
            <input type="text" name="qr_label_text" value="{{ old('qr_label_text', $s['qr_label_text'] ?? 'رمز الوصول الإلكتروني') }}">
        </div>

        <div class="qr-field">
            <label>حجم نص أسفل الرمز / mm</label>
            <input class="js-qr-preview" data-var="label-size" type="number" step="0.1" min="1.5" max="6" name="qr_label_font_size_mm" value="{{ old('qr_label_font_size_mm', $s['qr_label_font_size_mm'] ?? 2.6) }}">
        </div>

        <div class="qr-actions">
            <button type="submit" class="btn btn-primary">حفظ الإعدادات</button>
            <a href="{{ \Illuminate\Support\Facades\Route::has('settings.index') ? url('/settings') : url('/settings') }}" class="btn btn-secondary">رجوع للإعدادات</a>
        </div>
    </form>

    <div class="qr-settings-card qr-preview-wrap">
        <h3>معاينة تقريبية على ورقة A4</h3>
        <div id="qrPreviewPaper" class="qr-preview-paper"
             style="--preview-x: {{ $s['qr_x_mm'] ?? 82 }}; --preview-y: {{ $s['qr_y_mm'] ?? 50 }}; --preview-size: {{ $s['qr_size_mm'] ?? 20 }}; --preview-padding: {{ $s['qr_card_padding_mm'] ?? 2 }}; --preview-label-size: {{ $s['qr_label_font_size_mm'] ?? 2.6 }};">
            <div class="qr-preview-reference">
                <div>الشحن والتأمين</div>
                <div>رقم الكتاب : 251230004</div>
                <div>تاريخ الكتاب : 28/06/2026</div>
            </div>
            <div class="qr-preview-box">
                <div class="qr-preview-code"></div>
                <div class="qr-preview-label">رمز الوصول الإلكتروني</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const paper = document.getElementById('qrPreviewPaper');
    document.querySelectorAll('.js-qr-preview').forEach(function (input) {
        const update = function () {
            paper.style.setProperty('--preview-' + input.dataset.var, input.value || 0);
        };
        input.addEventListener('input', update);
        update();
    });
})();
</script>
@endsection
<!-- DA_QR_SETTINGS_BIND_V2_START -->
@php
    $daQrDocument = $document ?? $book ?? $currentDocument ?? null;
    $daQrSettings = [
        'qr_print_show' => '1',
        'qr_print_show_label' => '1',
        'qr_print_x_mm' => '72',
        'qr_print_y_mm' => '50',
        'qr_print_size_mm' => '16',
        'qr_print_padding_mm' => '1',
        'qr_print_label_font_size_pt' => '6',
        'qr_print_label' => 'رمز الوصول الإلكتروني',
    ];

    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('qr_print_settings')) {
            $daQrRows = \Illuminate\Support\Facades\DB::table('qr_print_settings')->pluck('value', 'key')->toArray();
            if (is_array($daQrRows)) {
                $daQrSettings = array_merge($daQrSettings, $daQrRows);
            }
        }
    } catch (\Throwable $e) {
        // fallback to defaults
    }

    $daQrVisible = (string)($daQrSettings['qr_print_show'] ?? '1') !== '0';
    $daQrLabelVisible = (string)($daQrSettings['qr_print_show_label'] ?? '1') !== '0';
    $daQrUrl = $daQrDocument ? url('/documents/' . $daQrDocument->id . '/qr.svg') : null;
    $daQrX = (float)($daQrSettings['qr_print_x_mm'] ?? 72);
    $daQrY = (float)($daQrSettings['qr_print_y_mm'] ?? 50);
    $daQrSize = (float)($daQrSettings['qr_print_size_mm'] ?? 16);
    $daQrPadding = (float)($daQrSettings['qr_print_padding_mm'] ?? 1);
    $daQrLabelFont = (float)($daQrSettings['qr_print_label_font_size_pt'] ?? 6);
    $daQrLabel = trim((string)($daQrSettings['qr_print_label'] ?? 'رمز الوصول الإلكتروني'));
@endphp

@if($daQrVisible && $daQrUrl)
<style>
    .da-qr-settings-v2-box {
        position: absolute !important;
        left: {{ $daQrX }}mm !important;
        top: {{ $daQrY }}mm !important;
        width: {{ $daQrSize + ($daQrPadding * 2) }}mm !important;
        min-height: {{ $daQrSize + ($daQrPadding * 2) }}mm !important;
        padding: {{ $daQrPadding }}mm !important;
        box-sizing: border-box !important;
        background: #fff !important;
        border: 0.25mm solid #d1d5db !important;
        border-radius: 1.5mm !important;
        text-align: center !important;
        z-index: 25 !important;
        direction: rtl !important;
        overflow: visible !important;
    }
    .da-qr-settings-v2-box img {
        width: {{ $daQrSize }}mm !important;
        height: {{ $daQrSize }}mm !important;
        display: block !important;
        margin: 0 auto !important;
    }
    .da-qr-settings-v2-label {
        margin-top: 1mm !important;
        font-size: {{ $daQrLabelFont }}pt !important;
        line-height: 1.1 !important;
        color: #334155 !important;
        white-space: nowrap !important;
        font-weight: 400 !important;
    }
    @media print {
        .da-qr-settings-v2-box {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>
<div id="daQrSettingsV2Box" class="da-qr-settings-v2-box">
    <img src="{{ $daQrUrl }}" alt="QR">
    @if($daQrLabelVisible && $daQrLabel !== '')
        <div class="da-qr-settings-v2-label">{{ $daQrLabel }}</div>
    @endif
</div>
<script>
(function () {
    function mountQrBox() {
        var box = document.getElementById('daQrSettingsV2Box');
        if (!box) return;
        var page = document.querySelector('.a4-page, .print-page, .document-print-page, .paper, .print-sheet, .page, [data-print-page]');
        if (!page) {
            var candidates = Array.prototype.slice.call(document.body.children).filter(function (el) {
                var r = el.getBoundingClientRect();
                return r.width > 500 && r.height > 700;
            });
            page = candidates[0] || document.body;
        }
        page.style.position = 'relative';
        if (box.parentElement !== page) page.appendChild(box);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountQrBox);
    } else {
        mountQrBox();
    }
})();
</script>
@endif
<!-- DA_QR_SETTINGS_BIND_V2_END -->

</div>

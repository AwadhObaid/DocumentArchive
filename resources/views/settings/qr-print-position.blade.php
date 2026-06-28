@extends('layouts.app')

@section('title', 'إعدادات الباركود والطباعة')
@section('page_title', 'إعدادات الباركود والطباعة')
@section('page_subtitle', 'ضبط موضع QR وبيانات رقم الكتاب على ورقة A4')

@section('content')
@php
    $s = $settings ?? [];
    $val = fn($key, $default) => old($key, $s[$key] ?? $default);
    $checked = fn($key, $default = '1') => ((string) old($key, $s[$key] ?? $default) === '1');
@endphp

<style>
.print-settings-grid {
    display: grid;
    grid-template-columns: minmax(300px, 440px) 1fr;
    gap: 18px;
    align-items: start;
}
.print-settings-card {
    background: var(--card);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 18px;
    box-shadow: var(--shadow);
}
.print-settings-card h2,
.print-settings-card h3 {
    margin-top: 0;
}
.print-settings-section {
    border-top: 1px solid var(--border);
    padding-top: 14px;
    margin-top: 16px;
}
.print-field {
    margin-bottom: 12px;
}
.print-field label {
    display: block;
    font-weight: 800;
    margin-bottom: 6px;
}
.print-field small {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    line-height: 1.7;
}
.print-checks {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
.print-checks label {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px;
    background: rgba(148, 163, 184, .08);
}
.print-preview-wrap {
    overflow: auto;
}
.print-preview-paper {
    position: relative;
    width: 210mm;
    height: 297mm;
    max-width: 100%;
    aspect-ratio: 210 / 297;
    background: #fff;
    color: #111827;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 10px 35px rgba(0,0,0,.18);
    transform-origin: top right;
}
.print-preview-reference {
    position: absolute;
    left: calc(var(--print-block-x, 106) * 1mm);
    top: calc(var(--print-block-y, 28) * 1mm);
    width: calc((var(--print-label-width, 27) + var(--print-colon-width, 4) + var(--print-value-width, 36) + 4) * 1mm);
    font-weight: 800;
    font-size: calc(var(--print-font-size, 10.5) * 1pt);
    line-height: var(--print-line-height, 1.35);
    direction: rtl;
    text-align: right;
}
.print-preview-reference .dept {
    text-align: center;
    font-size: calc(var(--print-dept-font-size, 11) * 1pt);
    margin-bottom: 1.8mm;
}
.print-preview-reference table { width: 100%; border-collapse: collapse; }
.print-preview-reference th,
.print-preview-reference td {
    border: 0;
    padding: .6mm 0;
    background: transparent;
    color: #111827;
    font-size: inherit;
    white-space: nowrap;
}
.print-preview-reference th { width: calc(var(--print-label-width, 27) * 1mm); text-align: right; }
.print-preview-reference .colon { width: calc(var(--print-colon-width, 4) * 1mm); text-align: center; }
.print-preview-reference .value { width: calc(var(--print-value-width, 36) * 1mm); text-align: left; direction: ltr; }
.print-preview-qr {
    position: absolute;
    left: calc(var(--qr-x, 70) * 1mm);
    top: calc(var(--qr-y, 28) * 1mm);
    width: calc((var(--qr-size, 16) + (var(--qr-padding, 1) * 2)) * 1mm);
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: calc(var(--qr-padding, 1) * 1mm);
    text-align: center;
    box-sizing: border-box;
}
.print-preview-code {
    width: calc(var(--qr-size, 16) * 1mm);
    height: calc(var(--qr-size, 16) * 1mm);
    margin: auto;
    background:
        linear-gradient(90deg,#111 50%,transparent 50%) 0 0 / 4px 4px,
        linear-gradient(#111 50%,transparent 50%) 0 0 / 6px 6px,
        #fff;
    image-rendering: pixelated;
}
.print-preview-label {
    font-size: calc(var(--qr-label-size, 2.1) * 1mm);
    margin-top: 0.8mm;
    color: #64748b;
    white-space: nowrap;
}
@media (max-width: 1000px) {
    .print-settings-grid { grid-template-columns: 1fr; }
}
</style>

<div class="page-header">
    <div>
        <h1>إعدادات الباركود والطباعة</h1>
        <p>هذه القيم تتحكم في صفحة طباعة رقم الكتاب فقط.</p>
    </div>
</div>

<div class="print-settings-grid">
    <form class="print-settings-card" method="POST" action="{{ route('settings.qr-print-position.update') }}">
        @csrf

        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif

        <h2>إعدادات QR</h2>
        <div class="print-checks">
            <label><input type="checkbox" name="qr_enabled" value="1" @checked($checked('qr_enabled'))> إظهار QR</label>
            <label><input type="checkbox" name="qr_label_enabled" value="1" @checked($checked('qr_label_enabled'))> إظهار النص</label>
            <label><input type="checkbox" name="qr_background_enabled" value="1" @checked($checked('qr_background_enabled'))> خلفية بيضاء</label>
            <label><input type="checkbox" name="qr_show_border" value="1" @checked($checked('qr_show_border'))> إطار حول QR</label>
        </div>

        <div class="print-field">
            <label>QR - المسافة من يسار الورقة / mm</label>
            <input class="js-print-preview" data-var="qr-x" type="number" step="0.5" min="0" max="210" name="qr_x_mm" value="{{ $val('qr_x_mm', 70) }}">
        </div>
        <div class="print-field">
            <label>QR - المسافة من أعلى الورقة / mm</label>
            <input class="js-print-preview" data-var="qr-y" type="number" step="0.5" min="0" max="297" name="qr_y_mm" value="{{ $val('qr_y_mm', 28) }}">
        </div>
        <div class="print-field">
            <label>حجم QR / mm</label>
            <input class="js-print-preview" data-var="qr-size" type="number" step="0.5" min="8" max="45" name="qr_size_mm" value="{{ $val('qr_size_mm', 16) }}">
        </div>
        <div class="print-field">
            <label>هامش بطاقة QR / mm</label>
            <input class="js-print-preview" data-var="qr-padding" type="number" step="0.5" min="0" max="8" name="qr_card_padding_mm" value="{{ $val('qr_card_padding_mm', 1) }}">
        </div>
        <div class="print-field">
            <label>نص أسفل QR</label>
            <input type="text" name="qr_label_text" value="{{ $val('qr_label_text', 'رمز الوصول الإلكتروني') }}">
        </div>
        <div class="print-field">
            <label>حجم نص QR / mm</label>
            <input class="js-print-preview" data-var="qr-label-size" type="number" step="0.1" min="1.5" max="6" name="qr_label_font_size_mm" value="{{ $val('qr_label_font_size_mm', 2.1) }}">
        </div>

        <div class="print-settings-section">
            <h2>إعدادات بيانات رقم الكتاب</h2>
            <div class="print-field">
                <label>موضع البيانات من يسار الورقة / mm</label>
                <input class="js-print-preview" data-var="print-block-x" type="number" step="0.5" min="0" max="210" name="print_block_x_mm" value="{{ $val('print_block_x_mm', 106) }}">
                <small>زيادة الرقم تحرك بيانات رقم الكتاب إلى اليمين، وتقليله يحركها إلى اليسار.</small>
            </div>
            <div class="print-field">
                <label>موضع البيانات من أعلى الورقة / mm</label>
                <input class="js-print-preview" data-var="print-block-y" type="number" step="0.5" min="0" max="297" name="print_block_y_mm" value="{{ $val('print_block_y_mm', 28) }}">
            </div>
            <div class="print-field">
                <label>حجم خط رقم الكتاب والتاريخ / pt</label>
                <input class="js-print-preview" data-var="print-font-size" type="number" step="0.5" min="7" max="24" name="print_font_size_pt" value="{{ $val('print_font_size_pt', 10.5) }}">
            </div>
            <div class="print-field">
                <label>حجم خط اسم الإدارة / pt</label>
                <input class="js-print-preview" data-var="print-dept-font-size" type="number" step="0.5" min="7" max="24" name="print_department_font_size_pt" value="{{ $val('print_department_font_size_pt', 11) }}">
            </div>
            <div class="print-field">
                <label>المسافة بين السطور</label>
                <input class="js-print-preview" data-var="print-line-height" type="number" step="0.05" min="1" max="2.5" name="print_line_height" value="{{ $val('print_line_height', 1.35) }}">
            </div>
            <div class="print-field">
                <label>عرض خانة العنوان / mm</label>
                <input class="js-print-preview" data-var="print-label-width" type="number" step="0.5" min="15" max="60" name="print_label_width_mm" value="{{ $val('print_label_width_mm', 27) }}">
            </div>
            <div class="print-field">
                <label>عرض خانة النقطتين / mm</label>
                <input class="js-print-preview" data-var="print-colon-width" type="number" step="0.5" min="2" max="12" name="print_colon_width_mm" value="{{ $val('print_colon_width_mm', 4) }}">
            </div>
            <div class="print-field">
                <label>عرض خانة القيمة / mm</label>
                <input class="js-print-preview" data-var="print-value-width" type="number" step="0.5" min="20" max="80" name="print_value_width_mm" value="{{ $val('print_value_width_mm', 36) }}">
            </div>
        </div>

        <div class="actions" style="margin-top:14px;">
            <button type="submit" class="btn btn-primary">حفظ الإعدادات</button>
            <a href="{{ url('/settings') }}" class="btn btn-secondary">رجوع للإعدادات</a>
        </div>
    </form>

    <div class="print-settings-card print-preview-wrap">
        <h3>معاينة تقريبية على ورقة A4</h3>
        <div id="printPreviewPaper" class="print-preview-paper"
             style="--qr-x: {{ $val('qr_x_mm', 70) }}; --qr-y: {{ $val('qr_y_mm', 28) }}; --qr-size: {{ $val('qr_size_mm', 16) }}; --qr-padding: {{ $val('qr_card_padding_mm', 1) }}; --qr-label-size: {{ $val('qr_label_font_size_mm', 2.1) }}; --print-block-x: {{ $val('print_block_x_mm', 106) }}; --print-block-y: {{ $val('print_block_y_mm', 28) }}; --print-font-size: {{ $val('print_font_size_pt', 10.5) }}; --print-dept-font-size: {{ $val('print_department_font_size_pt', 11) }}; --print-line-height: {{ $val('print_line_height', 1.35) }}; --print-label-width: {{ $val('print_label_width_mm', 27) }}; --print-colon-width: {{ $val('print_colon_width_mm', 4) }}; --print-value-width: {{ $val('print_value_width_mm', 36) }};">
            <div class="print-preview-reference">
                <div class="dept">الجمرك الجوي</div>
                <table>
                    <tr><th>رقم الكتاب</th><td class="colon">:</td><td class="value">251230004</td></tr>
                    <tr><th>تاريخ الكتاب</th><td class="colon">:</td><td class="value">28/06/2026</td></tr>
                </table>
            </div>
            <div class="print-preview-qr">
                <div class="print-preview-code"></div>
                <div class="print-preview-label">رمز الوصول الإلكتروني</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const paper = document.getElementById('printPreviewPaper');
    if (!paper) return;
    document.querySelectorAll('.js-print-preview').forEach(function (input) {
        const update = function () {
            paper.style.setProperty('--' + input.dataset.var, input.value || 0);
        };
        input.addEventListener('input', update);
        update();
    });
})();
</script>
@endsection
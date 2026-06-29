@extends('layouts.app')

@section('title', 'الإعدادات')

@section('content')
    <div class="page-title">
        <h1>الإعدادات</h1>

        @if(auth()->user()?->hasPermission('documents.view'))
            <a href="{{ route('documents.index') }}" class="btn btn-secondary">
                رجوع للكتب
            </a>
        @endif
    </div>

    <div class="card">
        @if(auth()->user()?->hasPermission('settings.manage'))
            <form method="POST" action="{{ route('settings.update') }}" id="settingsPrintForm">
                @csrf

                <h2>إعدادات رقم الكتاب</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label>رقم بداية الكتاب</label>
                        <input type="number" name="reference_start_number" value="{{ old('reference_start_number', $settings['reference_start_number']) }}" required>
                        <small>مثال: 251230000. يبدأ منه النظام أول كل سنة.</small>
                    </div>
                </div>

                <hr style="margin: 25px 0; border: 0; border-top: 1px solid rgba(148,163,184,.35);">

                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;">
                    <div>
                        <h2 style="margin-bottom:6px;">إعدادات طباعة رقم الكتاب على ورقة A4</h2>
                        <p style="margin:0; color:#94a3b8;">غيّر القيم وشاهد المعاينة مباشرة قبل الحفظ.</p>
                    </div>
                    <button type="button" class="btn btn-secondary" id="printPresetCompactBtn">
                        قيم مقترحة مضغوطة
                    </button>
                </div>

                <div class="form-grid" style="margin-top:18px;">
                    <div class="form-group">
                        <label>عنوان الطباعة</label>
                        <input type="text" data-print-preview="departmentTitle" name="print_department_title" value="{{ old('print_department_title', $settings['print_department_title']) }}" required>
                    </div>

                    <div class="form-group">
                        <label>نوع الخط</label>
                        <select data-print-preview="fontFamily" name="print_font_family" required>
                            @foreach($printFontOptions as $fontKey => $fontLabel)
                                <option value="{{ $fontKey }}" @selected(old('print_font_family', $settings['print_font_family']) === $fontKey)>
                                    {{ $fontLabel }}
                                </option>
                            @endforeach
                        </select>
                        <small>سيستخدم النظام خطاً احتياطياً إذا لم يكن الخط المختار مثبتاً.</small>
                    </div>

                    <div class="form-group">
                        <label>حجم خط العنوان</label>
                        <input type="number" step="0.1" data-print-preview="departmentFontSize" name="print_department_font_size_pt" value="{{ old('print_department_font_size_pt', $settings['print_department_font_size_pt']) }}" min="6" max="30" required>
                    </div>

                    <div class="form-group">
                        <label>حجم خط الرقم والتاريخ</label>
                        <input type="number" step="0.1" data-print-preview="fontSize" name="print_font_size_pt" value="{{ old('print_font_size_pt', $settings['print_font_size_pt']) }}" min="6" max="30" required>
                    </div>

                    <div class="form-group">
                        <label>الموضع من أعلى الورقة بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="top" name="print_top_mm" value="{{ old('print_top_mm', $settings['print_top_mm']) }}" required>
                        <small>زِد الرقم لتحريك الطباعة للأسفل، وقلله لتحريكها للأعلى.</small>
                    </div>

                    <div class="form-group">
                        <label>الموضع من يسار الورقة بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="left" name="print_left_mm" value="{{ old('print_left_mm', $settings['print_left_mm']) }}" required>
                        <small>زِد الرقم لتحريك الطباعة يساراً، وقلله لتحريكها يميناً.</small>
                    </div>
                </div>

                <hr style="margin: 25px 0; border: 0; border-top: 1px solid rgba(148,163,184,.35);">

                <h2>التحكم في المسافات بين العنوان والرقم</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label>عرض خانة العنوان بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="labelWidth" name="print_label_width_mm" value="{{ old('print_label_width_mm', $settings['print_label_width_mm']) }}" min="8" max="60" required>
                        <small>مثل مساحة: رقم الكتاب / تاريخ الكتاب.</small>
                    </div>

                    <div class="form-group">
                        <label>عرض خانة النقطتين بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="colonWidth" name="print_colon_width_mm" value="{{ old('print_colon_width_mm', $settings['print_colon_width_mm']) }}" min="0.5" max="10" required>
                        <small>قللها لإزالة الفراغ حول علامة (:).</small>
                    </div>

                    <div class="form-group">
                        <label>عرض خانة الرقم والتاريخ بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="valueWidth" name="print_value_width_mm" value="{{ old('print_value_width_mm', $settings['print_value_width_mm']) }}" min="10" max="80" required>
                    </div>

                    <div class="form-group">
                        <label>المسافة الأفقية بين الخانات بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="columnGap" name="print_column_gap_mm" value="{{ old('print_column_gap_mm', $settings['print_column_gap_mm']) }}" min="0" max="8" required>
                        <small>اجعلها 0 إذا أردت إزالة الفراغات الزائدة تماماً.</small>
                    </div>

                    <div class="form-group">
                        <label>المسافة بين العنوان والبيانات بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="titleGap" name="print_title_gap_mm" value="{{ old('print_title_gap_mm', $settings['print_title_gap_mm']) }}" min="0" max="15" required>
                    </div>

                    <div class="form-group">
                        <label>المسافة بين صفوف البيانات بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="rowGap" name="print_row_gap_mm" value="{{ old('print_row_gap_mm', $settings['print_row_gap_mm']) }}" min="0" max="10" required>
                    </div>

                    <div class="form-group full">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="apply_to_existing_documents" value="1">
                            تطبيق موضع الطباعة الجديد على الكتب السابقة أيضاً
                        </label>
                        <small>هذا الخيار يطبق العنوان والموضع فقط على الكتب السابقة. أما المسافات والخطوط فهي إعدادات عامة تطبق فوراً على صفحة الطباعة.</small>
                    </div>
                </div>

                <div style="margin-top: 20px; display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-success">
                        حفظ الإعدادات
                    </button>
                </div>
            </form>
        @endif
    </div>

    <div class="card">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;">
            <div>
                <h2 style="margin-bottom:6px;">معاينة مباشرة لطباعة رقم الكتاب</h2>
                <p style="margin:0; color:#94a3b8;">المعاينة تتحدث فوراً عند تغيير القيم. للطباعة الفعلية استخدم A4 و Scale 100%.</p>
            </div>
            <div style="font-size:13px; color:#94a3b8; line-height:1.7;">
                مثال المعاينة: 251230000<br>
                التاريخ: 25/06/2026
            </div>
        </div>

        <style>
            .print-settings-preview-shell {
                overflow: auto;
                max-width: 100%;
                margin-top: 18px;
                padding: 16px;
                border: 1px solid rgba(148, 163, 184, .25);
                border-radius: 16px;
                background: rgba(15, 23, 42, .28);
            }

            .print-settings-preview-a4 {
                width: 210mm;
                height: 297mm;
                background: #fff;
                position: relative;
                border: 1px solid #d1d5db;
                box-shadow: 0 18px 45px rgba(0, 0, 0, .22);
                transform: scale(.45);
                transform-origin: top right;
                margin-bottom: -156mm;
                color: #000;
            }

            .print-settings-preview-block {
                position: absolute;
                direction: rtl;
                text-align: right;
                color: #000;
                font-weight: 700;
                white-space: nowrap;
                line-height: 1.08;
            }

            .print-settings-preview-title {
                text-align: center;
                font-weight: 800;
                line-height: 1.05;
            }

            .print-settings-preview-row {
                display: grid;
                align-items: baseline;
                direction: rtl;
                white-space: nowrap;
            }

            .print-settings-preview-label {
                text-align: right;
                direction: rtl;
                font-weight: 700;
            }

            .print-settings-preview-colon {
                text-align: center;
                font-weight: 800;
                padding: 0;
            }

            .print-settings-preview-value {
                text-align: left;
                direction: ltr;
                font-weight: 700;
                letter-spacing: 0;
            }

            @media (max-width: 900px) {
                .print-settings-preview-a4 {
                    transform: scale(.34);
                    margin-bottom: -194mm;
                }
            }
        </style>

        <div class="print-settings-preview-shell" dir="rtl">
            <div class="print-settings-preview-a4" id="printPreviewA4">
                <div class="print-settings-preview-block" id="printPreviewBlock">
                    <div class="print-settings-preview-title" id="printPreviewDepartmentTitle">
                        {{ $settings['print_department_title'] }}
                    </div>
                    <div class="print-settings-preview-row" id="printPreviewRowReference">
                        <div class="print-settings-preview-label">رقم الكتاب</div>
                        <div class="print-settings-preview-colon">:</div>
                        <div class="print-settings-preview-value">251230000</div>
                    </div>
                    <div class="print-settings-preview-row" id="printPreviewRowDate">
                        <div class="print-settings-preview-label">تاريخ الكتاب</div>
                        <div class="print-settings-preview-colon">:</div>
                        <div class="print-settings-preview-value">25/06/2026</div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const fontStacks = {
                    'Cairo': '"Cairo", "Tajawal", Tahoma, Arial, sans-serif',
                    'Tajawal': '"Tajawal", "Cairo", Tahoma, Arial, sans-serif',
                    'Arial': 'Arial, Tahoma, sans-serif',
                    'Tahoma': 'Tahoma, Arial, sans-serif',
                    'Traditional Arabic': '"Traditional Arabic", "Times New Roman", Tahoma, serif',
                    'Amiri': '"Amiri", "Traditional Arabic", "Times New Roman", serif'
                };

                const form = document.getElementById('settingsPrintForm');
                const block = document.getElementById('printPreviewBlock');
                const title = document.getElementById('printPreviewDepartmentTitle');
                const rows = [
                    document.getElementById('printPreviewRowReference'),
                    document.getElementById('printPreviewRowDate')
                ];
                const presetButton = document.getElementById('printPresetCompactBtn');

                if (!form || !block || !title || rows.some(row => !row)) {
                    return;
                }

                function inputValue(name, fallback) {
                    const input = form.querySelector('[name="' + name + '"]');
                    if (!input) return fallback;
                    return input.value === '' ? fallback : input.value;
                }

                function numberValue(name, fallback) {
                    const parsed = parseFloat(inputValue(name, fallback));
                    return Number.isFinite(parsed) ? parsed : fallback;
                }

                function updatePreview() {
                    const departmentTitle = inputValue('print_department_title', 'الشحن والتأمين');
                    const fontFamily = inputValue('print_font_family', 'Cairo');
                    const top = numberValue('print_top_mm', 32);
                    const left = numberValue('print_left_mm', 32);
                    const fontSize = numberValue('print_font_size_pt', 10.2);
                    const departmentFontSize = numberValue('print_department_font_size_pt', 10.8);
                    const labelWidth = numberValue('print_label_width_mm', 18);
                    const colonWidth = numberValue('print_colon_width_mm', 1);
                    const valueWidth = numberValue('print_value_width_mm', 24);
                    const columnGap = numberValue('print_column_gap_mm', 0);
                    const titleGap = numberValue('print_title_gap_mm', 0.6);
                    const rowGap = numberValue('print_row_gap_mm', 0.25);
                    const fontStack = fontStacks[fontFamily] || fontStacks.Cairo;
                    const blockWidth = labelWidth + colonWidth + valueWidth + (columnGap * 2);

                    block.style.top = top + 'mm';
                    block.style.left = left + 'mm';
                    block.style.width = blockWidth + 'mm';
                    block.style.fontSize = fontSize + 'pt';
                    block.style.fontFamily = fontStack;
                    title.textContent = departmentTitle;
                    title.style.fontSize = departmentFontSize + 'pt';
                    title.style.margin = '0 0 ' + titleGap + 'mm';

                    rows.forEach(function (row, index) {
                        row.style.gridTemplateColumns = labelWidth + 'mm ' + colonWidth + 'mm ' + valueWidth + 'mm';
                        row.style.columnGap = columnGap + 'mm';
                        row.style.marginBottom = index === 0 ? rowGap + 'mm' : '0';
                    });
                }

                form.querySelectorAll('[data-print-preview]').forEach(function (input) {
                    input.addEventListener('input', updatePreview);
                    input.addEventListener('change', updatePreview);
                });

                if (presetButton) {
                    presetButton.addEventListener('click', function () {
                        const preset = {
                            print_top_mm: '32',
                            print_left_mm: '32',
                            print_font_size_pt: '10.2',
                            print_department_font_size_pt: '10.8',
                            print_label_width_mm: '18',
                            print_colon_width_mm: '1',
                            print_value_width_mm: '24',
                            print_column_gap_mm: '0',
                            print_title_gap_mm: '0.6',
                            print_row_gap_mm: '0.25'
                        };

                        Object.keys(preset).forEach(function (name) {
                            const input = form.querySelector('[name="' + name + '"]');
                            if (input) input.value = preset[name];
                        });

                        updatePreview();
                    });
                }

                updatePreview();
            });
        </script>
    </div>
@endsection
@extends('layouts.app')

@section('title', 'إعدادات النظام')
@section('page_title', 'إعدادات النظام العامة')
@section('page_subtitle', 'إدارة اسم النظام، الترقيم السنوي، وإعدادات طباعة رقم الكتاب')

@section('content')
    {{-- settings-polish:start --}}
    <style>
        .settings-polish-hero {
            display: grid;
            grid-template-columns: 1.4fr .9fr;
            gap: 16px;
            align-items: stretch;
            margin-bottom: 16px;
        }

        .settings-polish-card {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(15, 23, 42, .86), rgba(30, 41, 59, .76));
            padding: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .16);
        }

        .settings-polish-card h2,
        .settings-polish-card h3 {
            margin-top: 0;
        }

        .settings-polish-muted {
            color: #94a3b8;
            line-height: 1.8;
        }

        .settings-polish-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .settings-polish-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(59, 130, 246, .12);
            color: #bfdbfe;
            border: 1px solid rgba(96, 165, 250, .24);
            font-size: 13px;
            white-space: nowrap;
        }

        .settings-section-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .settings-section-header h2 {
            margin-bottom: 4px;
        }

        .settings-section-header p {
            margin: 0;
            color: #94a3b8;
            line-height: 1.7;
        }

        .settings-small-note {
            display: block;
            color: #94a3b8;
            line-height: 1.7;
            margin-top: 6px;
        }

        .settings-warning-box {
            border: 1px solid rgba(245, 158, 11, .28);
            background: rgba(245, 158, 11, .09);
            color: #fde68a;
            border-radius: 14px;
            padding: 12px 14px;
            line-height: 1.8;
            margin-top: 12px;
        }

        .settings-form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
            position: sticky;
            bottom: 0;
            z-index: 4;
            padding: 12px 0 0;
            background: linear-gradient(to top, rgba(15, 23, 42, .96), rgba(15, 23, 42, .72), transparent);
        }

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

        @media (max-width: 1100px) {
            .settings-polish-hero {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .print-settings-preview-a4 {
                transform: scale(.34);
                margin-bottom: -194mm;
            }
        }
    </style>

    <div class="page-title">
        <div>
            <h1>إعدادات النظام</h1>
            <p style="margin:6px 0 0; color:#94a3b8; line-height:1.7;">تحكم في هوية النظام، بداية الترقيم السنوي، الأمان، وطريقة طباعة رقم الكتاب.</p>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @if(auth()->user()?->hasPermission('dashboard.view'))
                <a href="{{ route('dashboard') }}" class="btn btn-secondary">لوحة التحكم</a>
            @endif

            @if(auth()->user()?->hasPermission('documents.view'))
                <a href="{{ route('documents.index') }}" class="btn btn-secondary">الكتب</a>
            @endif
        </div>
    </div>

    <div class="settings-polish-hero">
        <div class="settings-polish-card">
            <h2>نظرة عامة</h2>
            <p class="settings-polish-muted">هذه الصفحة مخصصة للإعدادات العامة المؤثرة على واجهة النظام، ترقيم الكتب، وطباعة رقم الكتاب على ورقة A4. تغيير رقم البداية لا يعيد ترقيم الكتب السابقة ولا يغيّر عداد سنة بدأت فعلياً.</p>
            <div class="settings-polish-badges">
                <span class="settings-polish-badge">🔢 بداية الترقيم: {{ $printSummary['reference_start'] }}</span>
                <span class="settings-polish-badge">🖨️ موضع الطباعة: {{ $printSummary['position'] }}</span>
                <span class="settings-polish-badge">🔤 الخط: {{ $printSummary['font'] }}</span>
                <span class="settings-polish-badge">🔒 الخروج التلقائي: {{ $printSummary['auto_logout'] }}</span>
                <span class="settings-polish-badge">🔎 بحث PDF/OCR: {{ $printSummary['pdf_search'] }}</span>
                <span class="settings-polish-badge">💬 الدردشة الداخلية: {{ $printSummary['internal_chat'] }}</span>
            </div>
        </div>

        <div class="settings-polish-card">
            <h3>تنبيه مهم</h3>
            <p class="settings-polish-muted">الرقم الظاهر في صفحة إضافة كتاب هو رقم متوقع فقط. الرقم النهائي يتم حجزه عند الحفظ حتى لا يحدث تكرار بين المستخدمين.</p>
        </div>
    </div>

    <div class="card">
        @if(auth()->user()?->hasPermission('settings.manage'))
            <form method="POST" action="{{ route('settings.update') }}" id="settingsPrintForm" autocomplete="off">
                @csrf

                <div class="settings-section-header">
                    <div>
                        <h2>الإعدادات العامة</h2>
                        <p>هذه القيم تظهر في الشريط الجانبي وأعلى صفحات النظام.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>اسم النظام المختصر</label>
                        <input type="text" name="system_name" value="{{ old('system_name', $settings['system_name']) }}" maxlength="80" required>
                        <small class="settings-small-note">مثال: الأرشيف الإلكتروني.</small>
                    </div>

                    <div class="form-group">
                        <label>اسم القسم أو الإدارة</label>
                        <input type="text" name="system_department_name" value="{{ old('system_department_name', $settings['system_department_name']) }}" maxlength="120" required>
                        <small class="settings-small-note">يظهر أسفل اسم النظام في القائمة الجانبية.</small>
                    </div>

                    <div class="form-group">
                        <label>أيقونة النظام</label>
                        <input type="text" name="system_brand_icon" value="{{ old('system_brand_icon', $settings['system_brand_icon']) }}" maxlength="16" required>
                        <small class="settings-small-note">يمكن استخدام رمز بسيط مثل 🗂️ أو 📁. هذا يخص شعار القائمة، أما أيقونة تبويب المتصفح فتم تثبيتها كأيقونة أرشفة.</small>
                    </div>

                    <div class="form-group full">
                        <label>العنوان الرئيسي أعلى الصفحات</label>
                        <input type="text" name="system_full_title" value="{{ old('system_full_title', $settings['system_full_title']) }}" maxlength="180" required>
                    </div>

                    <div class="form-group full">
                        <label>الوصف المختصر أعلى الصفحات</label>
                        <input type="text" name="system_tagline" value="{{ old('system_tagline', $settings['system_tagline']) }}" maxlength="255">
                    </div>
                </div>

                <hr style="margin: 25px 0; border: 0; border-top: 1px solid rgba(148,163,184,.35);">

                <div class="settings-section-header">
                    <div>
                        <h2>إعدادات الأمان والجلسات</h2>
                        <p>حدد مدة الخمول التي بعدها يسجل النظام خروج المستخدم تلقائياً، مع تنبيه قبل انتهاء الجلسة.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group full">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="auto_logout_enabled" value="1" @checked(old('auto_logout_enabled', $settings['auto_logout_enabled']) == '1')>
                            تفعيل تسجيل الخروج التلقائي عند عدم النشاط
                        </label>
                        <small class="settings-small-note">عند التفعيل، يتم تسجيل الخروج تلقائياً إذا بقي المستخدم دون حركة أو طلبات للنظام خلال المدة المحددة.</small>
                    </div>

                    <div class="form-group">
                        <label>مدة الخمول قبل تسجيل الخروج بالدقائق</label>
                        <input type="number" name="auto_logout_minutes" value="{{ old('auto_logout_minutes', $settings['auto_logout_minutes']) }}" min="1" max="1440" required>
                        <small class="settings-small-note">مثال: 30 دقيقة. الحد الأعلى 1440 دقيقة.</small>
                    </div>

                    <div class="form-group">
                        <label>التنبيه قبل الخروج بالثواني</label>
                        <input type="number" name="auto_logout_warning_seconds" value="{{ old('auto_logout_warning_seconds', $settings['auto_logout_warning_seconds']) }}" min="10" max="600" required>
                        <small class="settings-small-note">مثال: 60 ثانية. يجب أن تكون أقل من مدة الخمول.</small>
                    </div>

                    <div class="form-group">
                        <label>تحديث إشعارات نسخة الهاتف لايت كل</label>
                        <input type="number" name="lite_notification_poll_seconds" value="{{ old('lite_notification_poll_seconds', $settings['lite_notification_poll_seconds']) }}" min="10" max="300" required>
                        <small class="settings-small-note">بالـثواني. مثال: 30. تستخدمها نسخة الهاتف للمعاينة والتنبيهات.</small>
                    </div>
                </div>

                <div class="settings-warning-box">
                    الخروج التلقائي يعمل من الخادم والواجهة معاً: حتى لو بقيت الصفحة مفتوحة، سيظهر تنبيه قبل الخروج، ثم يتم إنهاء الجلسة عند انتهاء المدة.
                </div>

                <hr style="margin: 25px 0; border: 0; border-top: 1px solid rgba(148,163,184,.35);">

                <div class="settings-section-header">
                    <div>
                        <h2>إعدادات الدردشة الداخلية العائمة</h2>
                        <p>تحكم في نافذة الدردشة السريعة بين مستخدمي النظام. هذه الدردشة للتنسيق الداخلي السريع ولا تعتبر اعتماداً رسمياً للمستندات.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group full">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="internal_chat_enabled" value="1" @checked(old('internal_chat_enabled', $settings['internal_chat_enabled']) == '1')>
                            تفعيل نافذة الدردشة الداخلية العائمة
                        </label>
                        <small class="settings-small-note">عند التفعيل تظهر أيقونة دردشة صغيرة أسفل الشاشة للمستخدمين الذين لديهم صلاحية الدردشة.</small>
                    </div>

                    <div class="form-group">
                        <label>تحديث الدردشة كل</label>
                        <input type="number" name="internal_chat_poll_seconds" value="{{ old('internal_chat_poll_seconds', $settings['internal_chat_poll_seconds']) }}" min="3" max="120" required>
                        <small class="settings-small-note">بالـثواني. القيمة المقترحة 5 ثوانٍ على الشبكة الداخلية.</small>
                    </div>
                </div>

                <div class="settings-warning-box">
                    الدردشة الداخلية تحفظ الرسائل في قاعدة البيانات وتظهر للمستخدم المستلم بعد التحديث التلقائي. للمراسلات الرسمية المرتبطة بالكتب والمذكرات استخدم صفحة المراسلات الداخلية.
                </div>

                <hr style="margin: 25px 0; border: 0; border-top: 1px solid rgba(148,163,184,.35);">

                <div class="settings-section-header">
                    <div>
                        <h2>إعدادات بحث PDF و OCR</h2>
                        <p>حدد مسارات أدوات استخراج النصوص. اترك القيم كما هي إذا كانت الأدوات مضافة إلى PATH في Windows.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>مسار pdftotext</label>
                        <input type="text" name="pdf_search_pdftotext_path" value="{{ old('pdf_search_pdftotext_path', $settings['pdf_search_pdftotext_path']) }}" maxlength="500" placeholder="pdftotext">
                        <small class="settings-small-note">يستخدم لاستخراج النص من PDF النصي. مثال: C:\Tools\poppler\Library\bin\pdftotext.exe</small>
                    </div>

                    <div class="form-group">
                        <label>مسار pdftoppm</label>
                        <input type="text" name="pdf_search_pdftoppm_path" value="{{ old('pdf_search_pdftoppm_path', $settings['pdf_search_pdftoppm_path']) }}" maxlength="500" placeholder="pdftoppm">
                        <small class="settings-small-note">يستخدم لتحويل PDF السكانر إلى صور قبل OCR.</small>
                    </div>

                    <div class="form-group">
                        <label>مسار Tesseract</label>
                        <input type="text" name="pdf_search_tesseract_path" value="{{ old('pdf_search_tesseract_path', $settings['pdf_search_tesseract_path']) }}" maxlength="500" placeholder="tesseract">
                        <small class="settings-small-note">مثال: C:\Program Files\Tesseract-OCR\tesseract.exe</small>
                    </div>

                    <div class="form-group">
                        <label>لغات OCR</label>
                        <input type="text" name="pdf_search_ocr_languages" value="{{ old('pdf_search_ocr_languages', $settings['pdf_search_ocr_languages']) }}" maxlength="80" required>
                        <small class="settings-small-note">للعربية والإنجليزية استخدم: ara+eng.</small>
                    </div>

                    <div class="form-group">
                        <label>حد صفحات OCR لكل ملف</label>
                        <input type="number" name="pdf_search_pages_limit" value="{{ old('pdf_search_pages_limit', $settings['pdf_search_pages_limit']) }}" min="1" max="200" required>
                        <small class="settings-small-note">لحماية الأداء. ابدأ بـ 20 صفحة ثم ارفعها عند الحاجة.</small>
                    </div>

                    <div class="form-group full">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="pdf_search_enable_ocr" value="1" @checked(old('pdf_search_enable_ocr', $settings['pdf_search_enable_ocr']) == '1')>
                            تفعيل OCR عند الفهرسة
                        </label>
                        <small class="settings-small-note">إذا كان غير مفعل سيكتفي النظام باستخراج النص من PDF النصي، ويعلّم ملفات السكانر بأنها تحتاج OCR.</small>
                    </div>
                </div>

                <div class="settings-warning-box">
                    فهرسة OCR قد تستغرق وقتاً مع الملفات الكبيرة. يفضّل تشغيلها على دفعات صغيرة من صفحة بحث PDF/OCR أو من أمر Artisan.
                </div>

                <hr style="margin: 25px 0; border: 0; border-top: 1px solid rgba(148,163,184,.35);">

                <div class="settings-section-header">
                    <div>
                        <h2>إعدادات رقم الكتاب</h2>
                        <p>تحديد بداية الترقيم لكل سنة ميلادية.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>رقم بداية الكتاب</label>
                        <input type="number" name="reference_start_number" value="{{ old('reference_start_number', $settings['reference_start_number']) }}" min="1" max="999999999999" required>
                        <small class="settings-small-note">مثال: 251230000. يبدأ منه النظام أول كل سنة جديدة.</small>
                    </div>
                </div>

                <div class="settings-warning-box">
                    تغيير رقم البداية لا يعيد ترقيم الكتب السابقة، ولا يغيّر عداد سنة تم إصدار كتب فيها. هذا السلوك مقصود لحماية الأرقام الرسمية من التكرار أو التبديل.
                </div>

                <hr style="margin: 25px 0; border: 0; border-top: 1px solid rgba(148,163,184,.35);">

                <div class="settings-section-header">
                    <div>
                        <h2>إعدادات طباعة رقم الكتاب على ورقة A4</h2>
                        <p>غيّر القيم وشاهد المعاينة مباشرة قبل الحفظ.</p>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-secondary" id="printPresetCompactBtn">قيم مقترحة مضغوطة</button>
                        <button type="button" class="btn btn-secondary" id="printPresetDefaultBtn">القيم الافتراضية</button>
                    </div>
                </div>

                <div class="form-grid">
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
                        <small class="settings-small-note">سيستخدم النظام خطاً احتياطياً إذا لم يكن الخط المختار مثبتاً.</small>
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
                        <input type="number" step="0.1" data-print-preview="top" name="print_top_mm" value="{{ old('print_top_mm', $settings['print_top_mm']) }}" min="0" max="297" required>
                        <small class="settings-small-note">زِد الرقم لتحريك الطباعة للأسفل، وقلله لتحريكها للأعلى.</small>
                    </div>

                    <div class="form-group">
                        <label>الموضع من يسار الورقة بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="left" name="print_left_mm" value="{{ old('print_left_mm', $settings['print_left_mm']) }}" min="0" max="210" required>
                        <small class="settings-small-note">زِد الرقم لتحريك الطباعة يساراً، وقلله لتحريكها يميناً.</small>
                    </div>
                </div>

                <hr style="margin: 25px 0; border: 0; border-top: 1px solid rgba(148,163,184,.35);">

                <div class="settings-section-header">
                    <div>
                        <h2>التحكم في المسافات بين العنوان والرقم</h2>
                        <p>اضبط عرض الخانات والمسافات الدقيقة حتى تطابق النموذج الورقي.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>عرض خانة العنوان بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="labelWidth" name="print_label_width_mm" value="{{ old('print_label_width_mm', $settings['print_label_width_mm']) }}" min="8" max="60" required>
                        <small class="settings-small-note">مثل مساحة: رقم الكتاب / تاريخ الكتاب.</small>
                    </div>

                    <div class="form-group">
                        <label>عرض خانة النقطتين بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="colonWidth" name="print_colon_width_mm" value="{{ old('print_colon_width_mm', $settings['print_colon_width_mm']) }}" min="0.5" max="10" required>
                        <small class="settings-small-note">قللها لإزالة الفراغ حول علامة (:).</small>
                    </div>

                    <div class="form-group">
                        <label>عرض خانة الرقم والتاريخ بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="valueWidth" name="print_value_width_mm" value="{{ old('print_value_width_mm', $settings['print_value_width_mm']) }}" min="10" max="80" required>
                    </div>

                    <div class="form-group">
                        <label>المسافة الأفقية بين الخانات بالملليمتر</label>
                        <input type="number" step="0.1" data-print-preview="columnGap" name="print_column_gap_mm" value="{{ old('print_column_gap_mm', $settings['print_column_gap_mm']) }}" min="0" max="8" required>
                        <small class="settings-small-note">اجعلها 0 إذا أردت إزالة الفراغات الزائدة.</small>
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
                            تطبيق عنوان وموضع الطباعة الجديد على الكتب السابقة أيضاً
                        </label>
                        <small class="settings-small-note">هذا الخيار يطبق العنوان والموضع فقط على الكتب السابقة. أما المسافات والخطوط فهي إعدادات عامة تطبق فوراً على صفحة الطباعة.</small>
                    </div>
                </div>

                <div class="settings-form-actions">
                    <button type="submit" class="btn btn-success">حفظ الإعدادات</button>
                </div>
            </form>
        @else
            <div class="alert-error">ليست لديك صلاحية إدارة الإعدادات.</div>
        @endif
    </div>

    <div class="card">
        <div class="settings-section-header">
            <div>
                <h2>معاينة مباشرة لطباعة رقم الكتاب</h2>
                <p>المعاينة تتحدث فوراً عند تغيير القيم. للطباعة الفعلية استخدم A4 و Scale 100%.</p>
            </div>
            <div style="font-size:13px; color:#94a3b8; line-height:1.7;">
                مثال المعاينة: 251230000<br>
                التاريخ: 25/06/2026
            </div>
        </div>

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
                const compactButton = document.getElementById('printPresetCompactBtn');
                const defaultButton = document.getElementById('printPresetDefaultBtn');

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

                function setValues(values) {
                    Object.keys(values).forEach(function (name) {
                        const input = form.querySelector('[name="' + name + '"]');
                        if (input) input.value = values[name];
                    });
                    updatePreview();
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

                if (compactButton) {
                    compactButton.addEventListener('click', function () {
                        setValues({
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
                        });
                    });
                }

                if (defaultButton) {
                    defaultButton.addEventListener('click', function () {
                        setValues({
                            print_top_mm: '53.3',
                            print_left_mm: '30.8',
                            print_font_size_pt: '12',
                            print_department_font_size_pt: '12',
                            print_label_width_mm: '22',
                            print_colon_width_mm: '2',
                            print_value_width_mm: '32',
                            print_column_gap_mm: '1',
                            print_title_gap_mm: '1.2',
                            print_row_gap_mm: '0.8'
                        });
                    });
                }

                updatePreview();
            });
        </script>
    </div>
    {{-- settings-polish:end --}}
@endsection
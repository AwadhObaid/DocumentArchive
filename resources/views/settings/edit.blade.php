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


        .book-storage-path-picker-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            align-items: center;
        }

        .book-storage-path-picker-row input {
            min-width: 0;
        }

        .book-storage-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(2, 6, 23, .72);
            backdrop-filter: blur(6px);
        }

        .book-storage-modal-backdrop.is-open {
            display: flex;
        }

        .book-storage-modal-card {
            width: min(980px, 96vw);
            max-height: 88vh;
            overflow: hidden;
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            border-radius: 22px;
            background: #0f172a;
            border: 1px solid rgba(148, 163, 184, .28);
            box-shadow: 0 30px 90px rgba(0, 0, 0, .45);
            color: #e5e7eb;
        }

        html[data-theme="light"] .book-storage-modal-card {
            background: #ffffff;
            color: #0f172a;
            border-color: rgba(15, 23, 42, .12);
        }

        .book-storage-modal-header,
        .book-storage-modal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 16px 18px;
            border-bottom: 1px solid rgba(148, 163, 184, .22);
        }

        .book-storage-modal-footer {
            border-bottom: 0;
            border-top: 1px solid rgba(148, 163, 184, .22);
            flex-wrap: wrap;
        }

        .book-storage-modal-header h3 {
            margin: 0;
            font-size: 18px;
        }

        .book-storage-browser-toolbar {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            align-items: center;
            padding: 14px 18px;
            border-bottom: 1px solid rgba(148, 163, 184, .22);
        }

        .book-storage-current-path {
            direction: ltr;
            text-align: left;
            border: 1px solid rgba(148, 163, 184, .24);
            background: rgba(15, 23, 42, .36);
            color: inherit;
            border-radius: 12px;
            padding: 10px 12px;
            min-height: 42px;
            overflow: auto;
            white-space: nowrap;
            font-family: Consolas, "Courier New", monospace;
        }

        html[data-theme="light"] .book-storage-current-path {
            background: #f8fafc;
        }

        .book-storage-modal-body {
            min-height: 360px;
            overflow: auto;
            padding: 14px 18px 18px;
        }

        .book-storage-roots,
        .book-storage-folder-list {
            display: grid;
            gap: 8px;
        }

        .book-storage-roots {
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            margin-bottom: 14px;
        }

        .book-storage-root-btn,
        .book-storage-folder-row {
            width: 100%;
            text-align: start;
            cursor: pointer;
            border: 1px solid rgba(148, 163, 184, .24);
            background: rgba(30, 41, 59, .54);
            color: inherit;
            border-radius: 14px;
            padding: 10px 12px;
            transition: .15s ease;
        }

        html[data-theme="light"] .book-storage-root-btn,
        html[data-theme="light"] .book-storage-folder-row {
            background: #f8fafc;
        }

        .book-storage-root-btn:hover,
        .book-storage-folder-row:hover {
            border-color: rgba(59, 130, 246, .62);
            transform: translateY(-1px);
        }

        .book-storage-root-btn strong,
        .book-storage-folder-row strong {
            display: block;
            margin-bottom: 4px;
        }

        .book-storage-root-btn span,
        .book-storage-folder-row span {
            display: block;
            direction: ltr;
            text-align: left;
            color: #94a3b8;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .book-storage-modal-alert {
            display: none;
            margin: 0 18px 12px;
            padding: 10px 12px;
            border-radius: 12px;
            line-height: 1.7;
            border: 1px solid rgba(245, 158, 11, .32);
            background: rgba(245, 158, 11, .10);
            color: #fde68a;
        }

        .book-storage-modal-alert.is-visible {
            display: block;
        }

        .book-storage-create-row {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .book-storage-create-row input {
            min-width: 230px;
        }

        @media (max-width: 760px) {
            .book-storage-path-picker-row,
            .book-storage-browser-toolbar {
                grid-template-columns: 1fr;
            }

            .book-storage-modal-card {
                max-height: 94vh;
            }

            .book-storage-modal-footer {
                align-items: stretch;
            }
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



        .internal-chat-admin-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .internal-chat-admin-card {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 16px;
            padding: 14px;
            background: rgba(15, 23, 42, .28);
        }

        .internal-chat-admin-card h3 {
            margin: 0 0 8px;
            font-size: 16px;
        }

        .internal-chat-admin-card p {
            color: #94a3b8;
            line-height: 1.8;
            margin: 0 0 12px;
        }

        .internal-chat-admin-danger {
            border-color: rgba(239, 68, 68, .38);
            background: rgba(127, 29, 29, .18);
        }

        .internal-chat-admin-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .internal-chat-admin-actions input[type="file"],
        .internal-chat-admin-actions input[type="text"] {
            min-width: 230px;
            flex: 1;
        }



        .internal-chat-admin-alert {
            border-radius: 14px;
            padding: 12px 14px;
            margin: 0 0 14px;
            line-height: 1.8;
            border: 1px solid transparent;
        }

        .internal-chat-admin-alert.is-error {
            background: rgba(239, 68, 68, .10);
            border-color: rgba(239, 68, 68, .30);
            color: #fecaca;
        }

        .internal-chat-admin-alert.is-success {
            background: rgba(34, 197, 94, .10);
            border-color: rgba(34, 197, 94, .30);
            color: #bbf7d0;
        }

        html[data-theme="light"] .internal-chat-admin-alert.is-error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        html[data-theme="light"] .internal-chat-admin-alert.is-success {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }

        .internal-chat-admin-field-error {
            width: 100%;
            color: #fecaca;
            font-size: 13px;
            line-height: 1.7;
            margin-top: 4px;
            display: none;
        }

        .internal-chat-admin-field-error.is-visible {
            display: block;
        }

        html[data-theme="light"] .internal-chat-admin-field-error {
            color: #b91c1c;
        }

        .da-settings-confirm-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, .62);
            backdrop-filter: blur(8px);
        }

        .da-settings-confirm-backdrop.is-open {
            display: flex;
        }

        .da-settings-confirm-card {
            width: min(520px, 100%);
            border-radius: 22px;
            border: 1px solid rgba(148, 163, 184, .28);
            background: #0f172a;
            color: #e5e7eb;
            box-shadow: 0 24px 80px rgba(0, 0, 0, .35);
            padding: 18px;
        }

        html[data-theme="light"] .da-settings-confirm-card {
            background: #ffffff;
            color: #111827;
            border-color: #e5e7eb;
        }

        .da-settings-confirm-icon {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            background: rgba(59, 130, 246, .14);
            color: #93c5fd;
            margin-bottom: 10px;
            font-size: 22px;
        }

        .da-settings-confirm-card.is-danger .da-settings-confirm-icon {
            background: rgba(239, 68, 68, .14);
            color: #fecaca;
        }

        .da-settings-confirm-card h3 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .da-settings-confirm-card p {
            margin: 0;
            line-height: 1.9;
            color: #cbd5e1;
        }

        html[data-theme="light"] .da-settings-confirm-card p {
            color: #475569;
        }

        .da-settings-confirm-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-start;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        @media (max-width: 1100px) {
            .settings-polish-hero {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .internal-chat-admin-grid {
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
                <span class="settings-polish-badge">📁 مسار مرفقات الكتب: {{ $printSummary['book_attachment_storage'] }}</span>
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

                    <div class="form-group full">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="internal_chat_sound_enabled" value="1" @checked(old('internal_chat_sound_enabled', $settings['internal_chat_sound_enabled'] ?? '1') == '1')>
                            تفعيل التنبيه الصوتي عند وصول رسالة دردشة جديدة
                        </label>
                        <small class="settings-small-note">يعمل الصوت بعد أول تفاعل من المستخدم مع الصفحة، ويمكن كتمه مؤقتاً من زر الصوت داخل نافذة الدردشة.</small>
                    </div>

                    <div class="form-group">
                        <label>مستوى صوت تنبيه الدردشة</label>
                        @php($internalChatSoundVolume = (int) old('internal_chat_sound_volume', $settings['internal_chat_sound_volume'] ?? 85))
                        <div style="display:flex; gap:10px; align-items:center;">
                            <input
                                type="range"
                                min="0"
                                max="100"
                                value="{{ $internalChatSoundVolume }}"
                                style="flex:1;"
                                oninput="document.getElementById('internal_chat_sound_volume_number').value = this.value"
                            >
                            <input
                                id="internal_chat_sound_volume_number"
                                type="number"
                                name="internal_chat_sound_volume"
                                value="{{ $internalChatSoundVolume }}"
                                min="0"
                                max="100"
                                required
                                style="width:90px;"
                                oninput="this.parentElement.querySelector('input[type=range]').value = this.value"
                            >
                            <span>%</span>
                        </div>
                        <small class="settings-small-note">ارفع القيمة إذا كانت نغمة التنبيه غير مسموعة. القيمة المقترحة 85% إلى 100%.</small>
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
                        <h2>إعدادات حفظ مرفقات الكتب</h2>
                        <p>حدد المجلد الرئيسي الذي تُحفظ داخله مرفقات الكتب المصنفة. ينطبق هذا الإعداد على المرفقات الجديدة فقط، ولا ينقل المرفقات القديمة تلقائياً.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group full">
                        <label>المسار الافتراضي لمرفقات الكتب</label>
                        <div class="book-storage-path-picker-row">
                            <input
                                type="text"
                                name="book_attachment_storage_root"
                                id="bookAttachmentStorageRootInput"
                                value="{{ old('book_attachment_storage_root', $settings['book_attachment_storage_root'] ?? '') }}"
                                maxlength="1000"
                                dir="ltr"
                                placeholder="D:\DocumentArchiveFiles"
                            >
                            <button type="button" class="btn btn-secondary" id="bookAttachmentStorageBrowseBtn">اختيار المسار</button>
                            <button type="button" class="btn btn-secondary" id="bookAttachmentStorageDefaultBtn" data-default-path="{{ storage_path('app/private') }}">المسار الافتراضي</button>
                        </div>
                        @error('book_attachment_storage_root')
                            <small class="settings-small-note" style="color:#fecaca;">{{ $message }}</small>
                        @enderror
                        <small class="settings-small-note">
                            زر اختيار المسار يستعرض مجلدات جهاز السيرفر وليس جهاز المستخدم البعيد. في بيئة Laragon المحلية يكون هو نفس جهازك.
                        </small>
                        <small class="settings-small-note">
                            اتركه فارغاً لاستخدام المسار الافتراضي داخل المشروع: <span dir="ltr">{{ storage_path('app/private') }}</span>
                        </small>
                        <small class="settings-small-note">
                            عند اختيار مسار خارجي مثل <span dir="ltr">D:\DocumentArchiveFiles</span> سيحفظ النظام المرفقات الجديدة بهذا الشكل:
                            <span dir="ltr">D:\DocumentArchiveFiles\Books\DHL EXPRESS\إفراج جمركي 2026\251230010</span>
                        </small>
                    </div>
                </div>

                <div class="settings-warning-box">
                    تنبيه مهم: يجب أن يكون المسار كاملاً ومتاحاً للكتابة من جهاز السيرفر. لا تستخدم مجلد public ولا مجلد داخل سطح المكتب لمستخدم مختلف، حتى لا تتعطل المعاينة أو التنزيل.
                </div>

                <hr style="margin: 25px 0; border: 0; border-top: 1px solid rgba(148,163,184,.35);">

                {{-- smart-attachment-browser-v94-1-settings:start --}}
                <div class="settings-section-header">
                    <div>
                        <h2>البحث الذكي عن ملفات الكتب</h2>
                        <p>حدد مسارات ملفات الصادر والوارد على جهاز السيرفر أو مجلدات الشبكة المشتركة. عند تعديل الكتاب يمكن البحث برقم الكتاب واختيار الملف دون فتح نافذة Windows التقليدية.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group full">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input
                                type="checkbox"
                                name="smart_attachment_browser_enabled"
                                value="1"
                                @checked(old('smart_attachment_browser_enabled', $settings['smart_attachment_browser_enabled'] ?? '1') == '1')
                            >
                            تفعيل نافذة البحث الذكي عن المرفقات
                        </label>
                        <small class="settings-small-note">يبقى زر «اختيار من الجهاز» متاحاً دائماً كحل احتياطي للملفات الموجودة محلياً على جهاز المستخدم.</small>
                    </div>

                    <div class="form-group full">
                        <label>مسار ملفات الصادر</label>
                        <input
                            type="text"
                            name="smart_attachment_outgoing_path"
                            value="{{ old('smart_attachment_outgoing_path', $settings['smart_attachment_outgoing_path'] ?? '') }}"
                            maxlength="1500"
                            dir="ltr"
                            placeholder="\\192.168.1.202\ارشيف {year}\الصادر - {year}"
                        >
                        @error('smart_attachment_outgoing_path')
                            <small class="settings-small-note" style="color:#fecaca;">{{ $message }}</small>
                        @enderror
                        <small class="settings-small-note">مثال: <span dir="ltr">\\192.168.1.202\ارشيف {year}\الصادر - {year}</span></small>
                    </div>

                    <div class="form-group full">
                        <label>مسار ملفات الوارد</label>
                        <input
                            type="text"
                            name="smart_attachment_incoming_path"
                            value="{{ old('smart_attachment_incoming_path', $settings['smart_attachment_incoming_path'] ?? '') }}"
                            maxlength="1500"
                            dir="ltr"
                            placeholder="\\192.168.1.202\ارشيف {year}\الوارد - {year}"
                        >
                        @error('smart_attachment_incoming_path')
                            <small class="settings-small-note" style="color:#fecaca;">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group full">
                        <label>مسار عام إضافي — اختياري</label>
                        <input
                            type="text"
                            name="smart_attachment_general_path"
                            value="{{ old('smart_attachment_general_path', $settings['smart_attachment_general_path'] ?? '') }}"
                            maxlength="1500"
                            dir="ltr"
                            placeholder="D:\Archive\Shared"
                        >
                        @error('smart_attachment_general_path')
                            <small class="settings-small-note" style="color:#fecaca;">{{ $message }}</small>
                        @enderror
                        <small class="settings-small-note">يمكن أن يكون مساراً محلياً على السيرفر أو مسار UNC مشتركاً.</small>
                    </div>

                    <div class="form-group">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input
                                type="checkbox"
                                name="smart_attachment_recursive"
                                value="1"
                                @checked(old('smart_attachment_recursive', $settings['smart_attachment_recursive'] ?? '1') == '1')
                            >
                            البحث داخل المجلدات الفرعية
                        </label>
                        <small class="settings-small-note">يفيد عند تقسيم الأرشيف إلى مجلدات حسب الشهر أو الشركة.</small>
                    </div>

                    <div class="form-group">
                        <label>الحد الأعلى للنتائج</label>
                        <input
                            type="number"
                            name="smart_attachment_result_limit"
                            value="{{ old('smart_attachment_result_limit', $settings['smart_attachment_result_limit'] ?? 80) }}"
                            min="10"
                            max="200"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>مهلة البحث بالثواني</label>
                        <input
                            type="number"
                            name="smart_attachment_timeout_seconds"
                            value="{{ old('smart_attachment_timeout_seconds', $settings['smart_attachment_timeout_seconds'] ?? 8) }}"
                            min="2"
                            max="30"
                            required
                        >
                        <small class="settings-small-note">تمنع استمرار البحث طويلاً عند انقطاع مجلد الشبكة.</small>
                    </div>

                    <div class="form-group">
                        <label>الحد الأقصى لحجم الملف MB</label>
                        <input
                            type="number"
                            name="smart_attachment_max_file_mb"
                            value="{{ old('smart_attachment_max_file_mb', $settings['smart_attachment_max_file_mb'] ?? 20) }}"
                            min="1"
                            max="100"
                            required
                        >
                    </div>
                </div>

                <div class="settings-warning-box">
                    <strong>مهم:</strong>
                    يجب أن يستطيع حساب Windows الذي يشغل Apache وPHP قراءة المسار. أقراص الشبكة المرتبطة بحرف مثل
                    <span dir="ltr">Z:\</span>
                    قد لا تظهر للخدمة؛ استخدم مسار UNC مثل
                    <span dir="ltr">\\SERVER\Share</span>.
                    المسارات المحلية على جهاز المستخدم لا يمكن للمتصفح فحصها تلقائياً، ولذلك يستخدم زر «اختيار من الجهاز».
                </div>
                {{-- smart-attachment-browser-v94-1-settings:end --}}

                {{-- smart-reports-settings-v65:start --}}
                <div class="settings-section-header">
                    <div>
                        <h2>إعدادات التقارير الذكية Gemini</h2>
                        <p>فعّل توليد التقارير الذكية عبر Gemini API. يتم حفظ مفتاح API مشفراً ولا يظهر بعد الحفظ.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="smart_reports_enabled" value="1" @checked(old('smart_reports_enabled', $settings['smart_reports_enabled'] ?? '0') == '1')>
                            تفعيل التقارير الذكية
                        </label>
                        <small class="settings-small-note">عند التعطيل تبقى صفحة التقارير ظاهرة لكن لن يتم توليد تقارير جديدة.</small>
                    </div>

                    <div class="form-group">
                        <label>موديل Gemini</label>
                        <select name="smart_reports_gemini_model" required>
                            @php($geminiModel = old('smart_reports_gemini_model', $settings['smart_reports_gemini_model'] ?? 'gemini-3.5-flash'))
                            <option value="gemini-3.5-flash" @selected($geminiModel === 'gemini-3.5-flash')>gemini-3.5-flash</option>
                            <option value="gemini-2.5-flash" @selected($geminiModel === 'gemini-2.5-flash')>gemini-2.5-flash</option>
                            <option value="gemini-2.0-flash" @selected($geminiModel === 'gemini-2.0-flash')>gemini-2.0-flash</option>
                        </select>
                        <small class="settings-small-note">ابدأ بالموديل الافتراضي. إذا رفضته خدمة Gemini غيّره حسب الموديلات المتاحة في حسابك.</small>
                    </div>

                    <div class="form-group full">
                        <label>Gemini API Key</label>
                        <input
                            type="password"
                            name="smart_reports_gemini_api_key"
                            value=""
                            maxlength="1000"
                            dir="ltr"
                            autocomplete="new-password"
                            placeholder="{{ ($settings['smart_reports_gemini_api_key_configured'] ?? '0') === '1' ? 'مفتاح محفوظ حالياً - اتركه فارغاً للإبقاء عليه' : 'ضع Gemini API Key هنا' }}"
                        >
                        @error('smart_reports_gemini_api_key')
                            <small class="settings-small-note" style="color:#fecaca;">{{ $message }}</small>
                        @enderror
                        <small class="settings-small-note">
                            لا ترسل المفتاح في المحادثات أو البريد. اترك الحقل فارغاً إذا كان المفتاح محفوظاً ولا تريد تغييره.
                        </small>
                    </div>

                    <div class="form-group full">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="smart_reports_include_titles" value="1" @checked(old('smart_reports_include_titles', $settings['smart_reports_include_titles'] ?? '0') == '1')>
                            السماح بإرسال عناوين ومواضيع عينة من الكتب إلى Gemini
                        </label>
                        <small class="settings-small-note">
                            الأفضل ترك هذا الخيار غير مفعل للبيانات الحساسة. عند التعطيل يرسل النظام ملخصات إحصائية فقط بدون مرفقات.
                        </small>
                    </div>
                </div>

                <div class="settings-warning-box">
                    تنبيه: التقارير الذكية ترسل ملخص البيانات إلى خدمة Gemini على الإنترنت. لا يتم إرسال المرفقات، ويمكن منع إرسال عناوين الكتب من الخيار أعلاه.
                </div>
                {{-- smart-reports-settings-v65:end --}}

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

                    {{-- print-settings-live-binding-v81-8:start --}}
                    <div class="form-group full">
                        <div class="settings-warning-box" style="margin-top:0;">
                            <strong>تطبيق مباشر على جميع الكتب:</strong>
                            بعد حفظ الإعدادات ستستخدم صفحة طباعة رقم الكتاب القيم الجديدة فورًا
                            للكتب السابقة والجديدة، دون الحاجة إلى تعديل سجلات الكتب أو تحديد خيار إضافي.
                        </div>
                    </div>
                    {{-- print-settings-live-binding-v81-8:end --}}
                </div>

                <div class="settings-form-actions">
                    <button type="submit" class="btn btn-success">حفظ الإعدادات</button>
                </div>
            </form>


            <div class="book-storage-modal-backdrop" id="bookStoragePathModal" aria-hidden="true">
                <div class="book-storage-modal-card" role="dialog" aria-modal="true" aria-labelledby="bookStoragePathTitle">
                    <div class="book-storage-modal-header">
                        <div>
                            <h3 id="bookStoragePathTitle">اختيار مسار مرفقات الكتب</h3>
                            <small class="settings-small-note">اختر مجلداً موجوداً على جهاز السيرفر، أو أنشئ مجلداً جديداً ثم استخدمه.</small>
                        </div>
                        <button type="button" class="btn btn-secondary" data-book-storage-close>إغلاق</button>
                    </div>

                    <div class="book-storage-browser-toolbar">
                        <div class="book-storage-current-path" id="bookStorageCurrentPath">...</div>
                        <button type="button" class="btn btn-secondary" id="bookStorageParentBtn">رجوع للمجلد السابق</button>
                        <button type="button" class="btn btn-secondary" id="bookStorageRefreshBtn">تحديث</button>
                    </div>

                    <div class="book-storage-modal-alert" id="bookStorageModalAlert"></div>

                    <div class="book-storage-modal-body">
                        <div class="book-storage-roots" id="bookStorageRoots"></div>
                        <div class="book-storage-folder-list" id="bookStorageFolderList"></div>
                    </div>

                    <div class="book-storage-modal-footer">
                        <div class="book-storage-create-row">
                            <input type="text" id="bookStorageNewFolderName" placeholder="اسم مجلد جديد مثل: DocumentArchiveFiles" maxlength="120">
                            <button type="button" class="btn btn-secondary" id="bookStorageCreateFolderBtn">إنشاء مجلد هنا</button>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button type="button" class="btn btn-primary" id="bookStorageUsePathBtn">استخدام هذا المسار</button>
                            <button type="button" class="btn btn-secondary" data-book-storage-close>إلغاء</button>
                        </div>
                    </div>
                </div>
            </div>


            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const input = document.getElementById('bookAttachmentStorageRootInput');
                    const browseBtn = document.getElementById('bookAttachmentStorageBrowseBtn');
                    const defaultBtn = document.getElementById('bookAttachmentStorageDefaultBtn');
                    const modal = document.getElementById('bookStoragePathModal');
                    const rootsBox = document.getElementById('bookStorageRoots');
                    const folderList = document.getElementById('bookStorageFolderList');
                    const currentPathBox = document.getElementById('bookStorageCurrentPath');
                    const alertBox = document.getElementById('bookStorageModalAlert');
                    const parentBtn = document.getElementById('bookStorageParentBtn');
                    const refreshBtn = document.getElementById('bookStorageRefreshBtn');
                    const usePathBtn = document.getElementById('bookStorageUsePathBtn');
                    const createFolderBtn = document.getElementById('bookStorageCreateFolderBtn');
                    const newFolderInput = document.getElementById('bookStorageNewFolderName');

                    if (!input || !browseBtn || !modal) {
                        return;
                    }

                    const urls = {
                        roots: @json(route('settings.book-attachment-storage.roots')),
                        directories: @json(route('settings.book-attachment-storage.directories')),
                        create: @json(route('settings.book-attachment-storage.directories.create'))
                    };
                    const csrf = @json(csrf_token());
                    let currentPath = input.value || defaultBtn?.dataset.defaultPath || '';
                    let parentPath = null;

                    function showAlert(message) {
                        alertBox.textContent = message || '';
                        alertBox.classList.toggle('is-visible', Boolean(message));
                    }

                    function setLoading(message) {
                        folderList.innerHTML = '<div class="settings-small-note">' + (message || 'جاري التحميل...') + '</div>';
                    }

                    function openModal() {
                        modal.classList.add('is-open');
                        modal.setAttribute('aria-hidden', 'false');
                        document.body.style.overflow = 'hidden';
                        currentPath = input.value || defaultBtn?.dataset.defaultPath || currentPath;
                        loadRoots();
                        loadDirectories(currentPath);
                    }

                    function closeModal() {
                        modal.classList.remove('is-open');
                        modal.setAttribute('aria-hidden', 'true');
                        document.body.style.overflow = '';
                        showAlert('');
                    }

                    async function requestJson(url, options = {}) {
                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                ...(options.headers || {})
                            },
                            ...options
                        });

                        const data = await response.json().catch(() => ({}));

                        if (!response.ok || data.ok === false) {
                            throw new Error(data.message || 'تعذر تنفيذ العملية.');
                        }

                        return data;
                    }

                    async function loadRoots() {
                        try {
                            const data = await requestJson(urls.roots);
                            rootsBox.innerHTML = '';
                            (data.roots || []).forEach(function (root) {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'book-storage-root-btn';
                                btn.innerHTML = '<strong>🗂️ ' + escapeHtml(root.name || 'مسار') + (root.writable ? ' <small>قابل للكتابة</small>' : ' <small>قراءة فقط</small>') + '</strong><span>' + escapeHtml(root.path || '') + '</span>';
                                btn.addEventListener('click', function () {
                                    loadDirectories(root.path);
                                });
                                rootsBox.appendChild(btn);
                            });
                        } catch (error) {
                            showAlert(error.message);
                        }
                    }

                    async function loadDirectories(path) {
                        showAlert('');
                        currentPathBox.textContent = path || '';
                        setLoading('جاري استعراض المجلدات...');

                        try {
                            const url = urls.directories + '?path=' + encodeURIComponent(path || '');
                            const data = await requestJson(url);
                            currentPath = data.path || path || '';
                            parentPath = data.parent || null;
                            currentPathBox.textContent = currentPath;
                            parentBtn.disabled = !parentPath;
                            folderList.innerHTML = '';

                            if (!data.writable) {
                                showAlert('تنبيه: هذا المجلد غير قابل للكتابة حالياً. يمكنك استعراضه، لكن يفضل اختيار مجلد قابل للكتابة لحفظ المرفقات.');
                            }

                            if (!data.directories || data.directories.length === 0) {
                                folderList.innerHTML = '<div class="settings-small-note">لا توجد مجلدات فرعية داخل هذا المسار.</div>';
                                return;
                            }

                            data.directories.forEach(function (folder) {
                                const row = document.createElement('button');
                                row.type = 'button';
                                row.className = 'book-storage-folder-row';
                                row.innerHTML = '<strong>📁 ' + escapeHtml(folder.name || '') + (folder.writable ? ' <small>قابل للكتابة</small>' : '') + '</strong><span>' + escapeHtml(folder.path || '') + '</span>';
                                row.addEventListener('click', function () {
                                    loadDirectories(folder.path);
                                });
                                folderList.appendChild(row);
                            });
                        } catch (error) {
                            folderList.innerHTML = '';
                            showAlert(error.message);
                        }
                    }

                    async function createFolder() {
                        const name = (newFolderInput.value || '').trim();
                        if (!name) {
                            showAlert('اكتب اسم المجلد الجديد أولاً.');
                            newFolderInput.focus();
                            return;
                        }

                        try {
                            const data = await requestJson(urls.create, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf
                                },
                                body: JSON.stringify({
                                    path: currentPath,
                                    name: name
                                })
                            });
                            newFolderInput.value = '';
                            showAlert(data.message || 'تم إنشاء المجلد.');
                            await loadDirectories(data.path || currentPath);
                        } catch (error) {
                            showAlert(error.message);
                        }
                    }

                    function escapeHtml(value) {
                        return String(value ?? '')
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                    }

                    browseBtn.addEventListener('click', openModal);

                    defaultBtn?.addEventListener('click', function () {
                        input.value = defaultBtn.dataset.defaultPath || '';
                    });

                    modal.querySelectorAll('[data-book-storage-close]').forEach(function (btn) {
                        btn.addEventListener('click', closeModal);
                    });

                    modal.addEventListener('click', function (event) {
                        if (event.target === modal) {
                            closeModal();
                        }
                    });

                    parentBtn.addEventListener('click', function () {
                        if (parentPath) {
                            loadDirectories(parentPath);
                        }
                    });

                    refreshBtn.addEventListener('click', function () {
                        loadDirectories(currentPath);
                    });

                    usePathBtn.addEventListener('click', function () {
                        input.value = currentPath || '';
                        closeModal();
                    });

                    createFolderBtn.addEventListener('click', createFolder);
                    newFolderInput.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            createFolder();
                        }
                    });

                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                            closeModal();
                        }
                    });
                });
            </script>
        @else
            <div class="alert-error">ليست لديك صلاحية إدارة الإعدادات.</div>
        @endif
    </div>



    @if(auth()->user()?->hasPermission('internal_chat.backup') || auth()->user()?->hasPermission('internal_chat.restore_backup') || auth()->user()?->hasPermission('internal_chat.restore_deleted') || auth()->user()?->hasPermission('internal_chat.force_delete'))
        <div class="card" id="internal-chat-admin-management">
            <div class="settings-section-header">
                <div>
                    <h2>إدارة الدردشة الداخلية</h2>
                    <p>أدوات إدارية للنسخ الاحتياطي، الاستعادة، واسترجاع أو تفريغ الدردشات المحذوفة. هذه الإجراءات مخصصة للمدير فقط وتُسجل في سجل النشاط.</p>
                </div>
            </div>

            @if(session('internal_chat_admin_error') || $errors->has('confirmation') || $errors->has('backup_file'))
                <div class="internal-chat-admin-alert is-error">
                    {{ session('internal_chat_admin_error') ?: ($errors->first('confirmation') ?: $errors->first('backup_file')) }}
                </div>
            @endif

            @if(session('internal_chat_admin_success'))
                <div class="internal-chat-admin-alert is-success">
                    {{ session('internal_chat_admin_success') }}
                </div>
            @endif

            <div class="internal-chat-admin-grid">
                @if(auth()->user()?->hasPermission('internal_chat.backup'))
                    <div class="internal-chat-admin-card">
                        <h3>نسخة احتياطية للدردشات</h3>
                        <p>ينشئ ملف JSON يحتوي على المحادثات، المشاركين، الرسائل، ومعلومات المرفقات.</p>
                        <form method="POST" action="{{ route('settings.internal-chat.backup') }}" class="internal-chat-admin-actions">
                            @csrf
                            <button type="submit" class="btn btn-primary">إنشاء وتحميل نسخة احتياطية</button>
                        </form>
                    </div>
                @endif

                @if(auth()->user()?->hasPermission('internal_chat.restore_backup'))
                    <div class="internal-chat-admin-card">
                        <h3>استعادة نسخة احتياطية</h3>
                        <p>اختر ملف النسخة الذي تم إنشاؤه من النظام. سيتم تحديث أو إضافة السجلات الموجودة في الملف.</p>
                        <form method="POST" action="{{ route('settings.internal-chat.restore-backup') }}" enctype="multipart/form-data" class="internal-chat-admin-actions" data-da-confirm-form data-da-confirm-title="استعادة نسخة الدردشة" data-da-confirm-message="هل تريد استعادة نسخة الدردشة المحددة؟ يفضل إنشاء نسخة احتياطية حديثة قبل الاستعادة." data-da-confirm-icon="♻️">
                            @csrf
                            <input type="file" name="backup_file" accept=".json,application/json" required>
                            <button type="submit" class="btn btn-secondary">استعادة النسخة</button>
                        </form>
                    </div>
                @endif

                @if(auth()->user()?->hasPermission('internal_chat.restore_deleted'))
                    <div class="internal-chat-admin-card">
                        <h3>استعادة الدردشات المحذوفة ظاهريًا</h3>
                        <p>يعيد المحادثات التي تم مسحها أو أرشفتها ظاهريًا إلى القوائم الرئيسية للمستخدمين.</p>
                        <form method="POST" action="{{ route('settings.internal-chat.restore-deleted') }}" class="internal-chat-admin-actions" data-da-confirm-form data-da-confirm-title="استعادة الدردشات المحذوفة" data-da-confirm-message="سيتم استعادة الدردشات المحذوفة أو المؤرشفة ظاهريًا وإعادتها للقوائم الرئيسية. هل تريد المتابعة؟" data-da-confirm-icon="↩️">
                            @csrf
                            <button type="submit" class="btn btn-success">استعادة المحذوف ظاهريًا</button>
                        </form>
                    </div>
                @endif

                @if(auth()->user()?->hasPermission('internal_chat.force_delete'))
                    <div class="internal-chat-admin-card internal-chat-admin-danger">
                        <h3>تفريغ الدردشات المحذوفة نهائيًا</h3>
                        <p>يحذف نهائيًا المحادثات التي أصبحت محذوفة/ممسوحة عند جميع المشاركين فقط. سيتم إنشاء نسخة احتياطية تلقائية قبل التفريغ.</p>
                        <form method="POST" action="{{ route('settings.internal-chat.purge-deleted') }}" class="internal-chat-admin-actions" data-da-confirm-form data-da-confirm-title="تأكيد التفريغ النهائي" data-da-confirm-message="تحذير: سيتم حذف الدردشات المؤهلة نهائيًا بعد إنشاء نسخة احتياطية تلقائية. هذا الإجراء لا يمكن التراجع عنه إلا من نسخة احتياطية." data-da-confirm-danger="1" data-da-confirm-icon="⚠️">
                            @csrf
                            <input type="text" name="confirmation" placeholder="اكتب: حذف نهائي" autocomplete="off" required data-da-required-confirmation="حذف نهائي" value="{{ old('confirmation') }}">
                            <span class="internal-chat-admin-field-error" data-da-confirmation-error>عبارة التأكيد غير صحيحة. اكتب العبارة كما هي: حذف نهائي</span>
                            <button type="submit" class="btn btn-danger">تفريغ نهائي</button>
                        </form>
                    </div>
                @endif
            </div>

            <div class="settings-warning-box">
                للحماية، التفريغ النهائي لا يمس الدردشات النشطة، ويعمل فقط على المحادثات التي تم حذفها/مسحها من جميع المشاركين. الاستعادة من النسخة يجب استخدامها بحذر وبعد التأكد من الملف.
            </div>
        </div>

        <div class="da-settings-confirm-backdrop" id="daSettingsConfirmModal" aria-hidden="true">
            <div class="da-settings-confirm-card" role="dialog" aria-modal="true" aria-labelledby="daSettingsConfirmTitle" aria-describedby="daSettingsConfirmMessage">
                <div class="da-settings-confirm-icon" data-da-confirm-modal-icon>⚠️</div>
                <h3 id="daSettingsConfirmTitle">تأكيد الإجراء</h3>
                <p id="daSettingsConfirmMessage">هل تريد المتابعة ؟</p>
                <div class="da-settings-confirm-actions">
                    <button type="button" class="btn btn-primary" data-da-confirm-accept>تأكيد وتنفيذ</button>
                    <button type="button" class="btn btn-secondary" data-da-confirm-cancel>إلغاء</button>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('daSettingsConfirmModal');
                if (!modal) return;

                const card = modal.querySelector('.da-settings-confirm-card');
                const title = modal.querySelector('#daSettingsConfirmTitle');
                const message = modal.querySelector('#daSettingsConfirmMessage');
                const icon = modal.querySelector('[data-da-confirm-modal-icon]');
                const accept = modal.querySelector('[data-da-confirm-accept]');
                const cancel = modal.querySelector('[data-da-confirm-cancel]');
                let pendingForm = null;

                function closeModal() {
                    pendingForm = null;
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                }

                function openModal(form) {
                    pendingForm = form;
                    title.textContent = form.dataset.daConfirmTitle || 'تأكيد الإجراء';
                    message.textContent = form.dataset.daConfirmMessage || 'هل تريد المتابعة؟';
                    icon.textContent = form.dataset.daConfirmIcon || '⚠️';
                    card.classList.toggle('is-danger', form.dataset.daConfirmDanger === '1');
                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                    cancel.focus();
                }

                function showConfirmationError(input, text) {
                    const error = input.closest('form')?.querySelector('[data-da-confirmation-error]');
                    if (error) {
                        error.textContent = text;
                        error.classList.add('is-visible');
                    }
                    input.setAttribute('aria-invalid', 'true');
                    input.focus();
                }

                function clearConfirmationError(input) {
                    const error = input.closest('form')?.querySelector('[data-da-confirmation-error]');
                    if (error) error.classList.remove('is-visible');
                    input.removeAttribute('aria-invalid');
                }

                document.querySelectorAll('[data-da-required-confirmation]').forEach(function (input) {
                    input.addEventListener('input', function () {
                        clearConfirmationError(input);
                    });
                });

                document.addEventListener('submit', function (event) {
                    const form = event.target.closest('form[data-da-confirm-form]');
                    if (!form) return;

                    if (form.dataset.daConfirmed === '1') {
                        delete form.dataset.daConfirmed;
                        return;
                    }

                    const confirmationInput = form.querySelector('[data-da-required-confirmation]');
                    if (confirmationInput) {
                        const expected = (confirmationInput.dataset.daRequiredConfirmation || '').trim();
                        const actual = (confirmationInput.value || '').replace(/\s+/g, ' ').trim();
                        if (actual !== expected) {
                            event.preventDefault();
                            showConfirmationError(confirmationInput, 'عبارة التأكيد غير صحيحة. اكتب العبارة كما هي: ' + expected);
                            return;
                        }
                    }

                    event.preventDefault();
                    openModal(form);
                });

                accept.addEventListener('click', function () {
                    if (!pendingForm) return;
                    const form = pendingForm;
                    closeModal();
                    form.dataset.daConfirmed = '1';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });

                cancel.addEventListener('click', closeModal);
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) closeModal();
                });
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                        closeModal();
                    }
                });
            });
        </script>
    @endif

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
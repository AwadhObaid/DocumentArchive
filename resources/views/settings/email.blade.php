@extends('layouts.app')

@section('title', 'إعدادات البريد الإلكتروني')
@section('page_title', 'إعدادات البريد الإلكتروني')
@section('page_subtitle', 'إدارة اتصال SMTP واختبار الإرسال دون تعديل ملف .env')

@section('content')
    <style>
        .email-settings-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(300px, .8fr);
            gap: 18px;
            align-items: start;
        }

        .email-settings-status-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .email-settings-status {
            padding: 14px;
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, .22);
            background: rgba(15, 23, 42, .42);
        }

        .email-settings-status strong,
        .email-settings-status span {
            display: block;
        }

        .email-settings-status span {
            margin-top: 6px;
            color: #94a3b8;
            line-height: 1.7;
        }

        .email-settings-password-state {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .email-settings-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid rgba(96, 165, 250, .25);
            background: rgba(59, 130, 246, .12);
            color: #bfdbfe;
            font-size: 12px;
        }

        .email-settings-chip.is-success {
            border-color: rgba(34, 197, 94, .28);
            background: rgba(34, 197, 94, .12);
            color: #bbf7d0;
        }

        .email-settings-chip.is-danger {
            border-color: rgba(239, 68, 68, .28);
            background: rgba(239, 68, 68, .12);
            color: #fecaca;
        }

        .email-settings-presets {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 10px 0 16px;
        }

        .email-settings-note {
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(245, 158, 11, .26);
            background: rgba(245, 158, 11, .08);
            color: #fde68a;
            line-height: 1.8;
        }

        .email-settings-test-result {
            padding: 12px 14px;
            border-radius: 14px;
            line-height: 1.8;
            margin-bottom: 14px;
            border: 1px solid rgba(148, 163, 184, .22);
        }

        .email-settings-test-result.is-success {
            border-color: rgba(34, 197, 94, .28);
            background: rgba(34, 197, 94, .10);
            color: #bbf7d0;
        }

        .email-settings-test-result.is-failed {
            border-color: rgba(239, 68, 68, .28);
            background: rgba(239, 68, 68, .10);
            color: #fecaca;
        }

        @media (max-width: 1000px) {
            .email-settings-grid,
            .email-settings-status-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-title">
        <div>
            <h1>إعدادات البريد الإلكتروني</h1>
            <p style="margin:6px 0 0; color:#94a3b8; line-height:1.7;">
                احفظ بيانات SMTP مشفرة داخل قاعدة البيانات واختبر الإرسال من واجهة النظام.
            </p>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('settings.edit') }}" class="btn btn-secondary">العودة إلى إعدادات النظام</a>
            @if(auth()->user()?->hasPermission('emails.view'))
                <a href="{{ route('emails.index') }}" class="btn btn-secondary">سجل البريد</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert-error">
            <strong>يرجى تصحيح الأخطاء التالية:</strong>
            <ul style="margin:8px 0 0; padding-right:20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="email-settings-status-grid">
        <div class="email-settings-status">
            <strong>حالة البريد</strong>
            <span>{{ (string) $settings['email_enabled'] === '1' ? 'مفعل للإرسال عبر SMTP' : 'معطل من إعدادات النظام' }}</span>
        </div>
        <div class="email-settings-status">
            <strong>مصدر الإعدادات الحالي</strong>
            <span>{{ $settings['source_label'] }}</span>
        </div>
        <div class="email-settings-status">
            <strong>كلمة المرور</strong>
            <span>{{ $settings['password_source_label'] }}</span>
        </div>
    </div>

    <div class="email-settings-grid">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 style="margin:0;">اتصال SMTP</h2>
                    <p style="margin:6px 0 0; color:#94a3b8; line-height:1.7;">تطبق هذه القيم مباشرة عند إرسال البريد دون الحاجة إلى تعديل أو تنظيف إعدادات .env.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('settings.email.update') }}" autocomplete="off" data-confirm="سيتم حفظ إعدادات البريد وتطبيقها على عمليات الإرسال التالية. هل تريد المتابعة؟" data-confirm-title="حفظ إعدادات البريد" data-confirm-yes="نعم، حفظ الإعدادات" data-confirm-no="مراجعة القيم">
                @csrf

                <div class="form-grid">
                    <div class="form-group full">
                        <label style="display:flex; gap:8px; align-items:center;">
                            <input type="checkbox" name="email_enabled" value="1" @checked(old('email_enabled', $settings['email_enabled']) == '1')>
                            تفعيل إرسال البريد الإلكتروني عبر SMTP
                        </label>
                    </div>

                    <div class="form-group full">
                        <label>إعدادات جاهزة</label>
                        <div class="email-settings-presets">
                            <button type="button" class="btn btn-secondary" data-email-preset="gmail">Gmail</button>
                            <button type="button" class="btn btn-secondary" data-email-preset="microsoft">Microsoft 365</button>
                        </div>
                    </div>

                    <div class="form-group full">
                        <label>خادم SMTP</label>
                        <input id="emailSmtpHost" type="text" name="email_smtp_host" value="{{ old('email_smtp_host', $settings['email_smtp_host']) }}" maxlength="255" dir="ltr" placeholder="smtp.gmail.com">
                    </div>

                    <div class="form-group">
                        <label>المنفذ</label>
                        <input id="emailSmtpPort" type="number" name="email_smtp_port" value="{{ old('email_smtp_port', $settings['email_smtp_port']) }}" min="1" max="65535" dir="ltr">
                    </div>

                    <div class="form-group">
                        <label>نوع الحماية</label>
                        <select id="emailSmtpSecurity" name="email_smtp_security">
                            <option value="tls" @selected(old('email_smtp_security', $settings['email_smtp_security']) === 'tls')>TLS / STARTTLS</option>
                            <option value="ssl" @selected(old('email_smtp_security', $settings['email_smtp_security']) === 'ssl')>SSL / SMTPS</option>
                            <option value="none" @selected(old('email_smtp_security', $settings['email_smtp_security']) === 'none')>تلقائي / بدون فرض حماية</option>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label>اسم مستخدم SMTP</label>
                        <input type="email" name="email_smtp_username" value="{{ old('email_smtp_username', $settings['email_smtp_username']) }}" maxlength="255" dir="ltr" autocomplete="username" placeholder="sender@gmail.com">
                    </div>

                    <div class="form-group full">
                        <label>كلمة مرور SMTP أو كلمة مرور التطبيق</label>
                        <input type="password" name="email_smtp_password" value="" maxlength="1000" dir="ltr" autocomplete="new-password" placeholder="اتركها فارغة للاحتفاظ بالقيمة الحالية">
                        <div class="email-settings-password-state">
                            <span class="email-settings-chip {{ $settings['password_configured'] ? 'is-success' : 'is-danger' }}">
                                {{ $settings['password_configured'] ? '🔐 كلمة المرور متاحة' : '⚠️ لا توجد كلمة مرور' }}
                            </span>
                            <span class="email-settings-chip">{{ $settings['password_source_label'] }}</span>
                        </div>
                        <small style="display:block; margin-top:8px; color:#94a3b8; line-height:1.7;">لا تُعرض كلمة المرور بعد حفظها. كلمات مرور تطبيق Google المكونة من أربع مجموعات سيتم تنظيف مسافاتها تلقائياً قبل التشفير.</small>
                    </div>

                    @if($settings['password_source'] === 'environment')
                        <div class="form-group full">
                            <label style="display:flex; gap:8px; align-items:center;">
                                <input type="checkbox" name="email_import_env_password" value="1">
                                نقل كلمة المرور الحالية من ملف .env إلى قاعدة البيانات وتشفيرها
                            </label>
                            <small style="display:block; margin-top:6px; color:#94a3b8;">بعد نجاح النقل يمكن إزالة بيانات SMTP الحساسة من .env والإبقاء عليه كإعداد احتياطي فقط.</small>
                        </div>
                    @endif

                    @if($settings['password_configured'])
                        <div class="form-group full">
                            <label style="display:flex; gap:8px; align-items:center; color:#fecaca;">
                                <input type="checkbox" name="email_clear_password" value="1">
                                حذف كلمة المرور المحفوظة من قاعدة البيانات
                            </label>
                        </div>
                    @endif

                    <div class="form-group full">
                        <label>عنوان البريد المرسل</label>
                        <input type="email" name="email_from_address" value="{{ old('email_from_address', $settings['email_from_address']) }}" maxlength="255" dir="ltr" placeholder="sender@gmail.com">
                    </div>

                    <div class="form-group">
                        <label>اسم المرسل الظاهر</label>
                        <input type="text" name="email_from_name" value="{{ old('email_from_name', $settings['email_from_name']) }}" maxlength="255">
                    </div>

                    <div class="form-group">
                        <label>مهلة الاتصال بالثواني</label>
                        <input type="number" name="email_timeout" value="{{ old('email_timeout', $settings['email_timeout']) }}" min="5" max="120">
                    </div>
                </div>

                <div class="email-settings-note" style="margin-top:16px;">
                    في Gmail استخدم كلمة مرور تطبيق، وليس كلمة مرور الحساب العادية. يفضل أن يطابق عنوان المرسل اسم مستخدم SMTP.
                </div>

                <div class="settings-form-actions" style="margin-top:16px;">
                    <button type="submit" class="btn btn-success" data-loading-text="جارٍ حفظ إعدادات البريد...">حفظ إعدادات البريد</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 style="margin:0;">اختبار الإرسال</h2>
                    <p style="margin:6px 0 0; color:#94a3b8; line-height:1.7;">احفظ الإعدادات أولاً، ثم أرسل رسالة اختبار فعلية.</p>
                </div>
            </div>

            @if($settings['last_test_status'] !== '')
                <div class="email-settings-test-result {{ $settings['last_test_status'] === 'success' ? 'is-success' : 'is-failed' }}">
                    <strong>{{ $settings['last_test_status'] === 'success' ? 'آخر اختبار ناجح' : 'آخر اختبار فاشل' }}</strong>
                    @if($settings['last_test_at'] !== '')
                        <div style="margin-top:5px;">{{ $settings['last_test_at'] }}</div>
                    @endif
                    @if($settings['last_test_message'] !== '')
                        <div style="margin-top:5px;">{{ $settings['last_test_message'] }}</div>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('settings.email.test') }}" data-confirm="سيتم إرسال رسالة اختبار حقيقية باستخدام الإعدادات المحفوظة. هل تريد المتابعة؟" data-confirm-title="اختبار البريد الإلكتروني" data-confirm-yes="نعم، إرسال الاختبار" data-confirm-no="إلغاء">
                @csrf
                <div class="form-group">
                    <label>البريد المستلم للاختبار</label>
                    <input type="email" name="test_email" value="{{ old('test_email', auth()->user()?->email) }}" maxlength="255" dir="ltr" required>
                </div>

                <button type="submit" class="btn btn-primary" data-loading-text="جارٍ اختبار البريد الإلكتروني...">إرسال رسالة اختبار</button>
            </form>

            <hr style="margin:22px 0; border:0; border-top:1px solid rgba(148,163,184,.22);">

            <div style="line-height:1.9; color:#cbd5e1;">
                <strong>القيم الفعلية الحالية</strong>
                <div style="margin-top:8px; direction:ltr; text-align:left; font-family:Consolas,monospace;">
                    {{ $settings['email_smtp_host'] }}:{{ $settings['email_smtp_port'] }}
                </div>
                <div style="margin-top:5px; direction:ltr; text-align:left;">{{ $settings['email_from_address'] ?: 'No sender address' }}</div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const host = document.getElementById('emailSmtpHost');
            const port = document.getElementById('emailSmtpPort');
            const security = document.getElementById('emailSmtpSecurity');

            document.querySelectorAll('[data-email-preset]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const preset = button.dataset.emailPreset;

                    if (preset === 'gmail') {
                        host.value = 'smtp.gmail.com';
                        port.value = '587';
                        security.value = 'tls';
                    }

                    if (preset === 'microsoft') {
                        host.value = 'smtp.office365.com';
                        port.value = '587';
                        security.value = 'tls';
                    }
                });
            });
        });
    </script>
@endsection

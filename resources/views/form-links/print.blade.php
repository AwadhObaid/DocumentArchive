<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>فتح للطباعة - {{ $formLink->title }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #eef3f8;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --border: #d8e0ea;
            --text: #111827;
            --muted: #64748b;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #16a34a;
            --legacy: #111827;
            --warning-bg: #fff8e6;
            --warning-border: #f6d88b;
            --warning-text: #8a4b08;
            --danger-bg: #fff1f2;
            --danger-border: #fecdd3;
            --danger-text: #9f1239;
            --shadow: 0 18px 45px rgba(15, 23, 42, .08);
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            padding: 24px;
            line-height: 1.75;
        }

        .page {
            width: min(1120px, 100%);
            margin: 0 auto;
            display: grid;
            gap: 16px;
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px;
            box-shadow: var(--shadow);
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 18px;
            align-items: center;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 12px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        h1, h2, h3, p { margin-top: 0; }
        h1 { margin-bottom: 8px; font-size: 28px; line-height: 1.35; letter-spacing: -.02em; }
        h2 { margin-bottom: 14px; font-size: 20px; line-height: 1.45; }
        h3 { margin-bottom: 8px; font-size: 16px; }
        p { margin-bottom: 0; }
        .muted { color: var(--muted); }
        .small { font-size: 13px; }

        .url-box {
            direction: ltr;
            unicode-bidi: isolate;
            text-align: left;
            overflow-wrap: anywhere;
            margin-top: 10px;
            padding: 9px 11px;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            background: var(--surface-soft);
            color: var(--muted);
            font-size: 13px;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
            min-width: 270px;
        }

        .btn {
            border: 1px solid var(--border);
            background: #ffffff;
            color: var(--text);
            text-decoration: none;
            border-radius: 12px;
            padding: 10px 14px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            white-space: nowrap;
        }

        .btn:hover { filter: brightness(.98); }
        .btn-primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
        .btn-success { background: var(--success); border-color: var(--success); color: #fff; }
        .btn-legacy { background: var(--legacy); border-color: var(--legacy); color: #fff; }

        .alert {
            border-radius: 16px;
            padding: 14px 16px;
            border: 1px solid var(--danger-border);
            background: var(--danger-bg);
            color: var(--danger-text);
            font-weight: 700;
        }

        .option-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .option-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 900;
            flex: 0 0 auto;
        }

        .step-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 10px;
        }

        .step-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid rgba(148, 163, 184, .35);
            border-radius: 14px;
            background: var(--surface-soft);
            min-height: 54px;
        }

        .step-no {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #e0edff;
            color: #1d4ed8;
            font-weight: 900;
            flex: 0 0 30px;
            margin-top: 1px;
        }

        .step-text { min-width: 0; flex: 1 1 auto; }
        .nowrap { white-space: nowrap; }
        .ltr {
            direction: ltr;
            unicode-bidi: isolate;
            display: inline-block;
            white-space: nowrap;
            font-family: Consolas, "Courier New", monospace;
            font-size: .95em;
        }

        .settings-wrap { overflow: hidden; border: 1px solid var(--border); border-radius: 16px; }
        .settings { width: 100%; border-collapse: collapse; background: #fff; }
        .settings th, .settings td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            text-align: right;
            vertical-align: top;
        }
        .settings tr:last-child th,
        .settings tr:last-child td { border-bottom: 0; }
        .settings th { width: 34%; background: #f8fafc; font-size: 15px; }
        .settings td { color: #334155; }

        .note {
            border: 1px solid var(--warning-border);
            background: var(--warning-bg);
            color: var(--warning-text);
            border-radius: 16px;
            padding: 15px 16px;
            font-weight: 700;
        }

        .footer-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        @media (max-width: 850px) {
            body { padding: 14px; }
            .hero { grid-template-columns: 1fr; }
            .actions { min-width: 0; }
            .option-grid { grid-template-columns: 1fr; }
            h1 { font-size: 23px; }
        }

        @media (max-width: 560px) {
            .panel { padding: 14px; border-radius: 14px; }
            .settings th, .settings td { display: block; width: 100%; }
            .settings th { border-bottom: 0; padding-bottom: 4px; }
            .settings td { padding-top: 4px; }
            .footer-actions, .actions { display: grid; }
            .btn { width: 100%; }
        }

        @page { size: A4 portrait; margin: 12mm; }
        @media print {
            body { background: #fff; padding: 0; color: #000; }
            .page { width: 100%; max-width: none; gap: 10px; }
            .panel { box-shadow: none; border-color: #9ca3af; border-radius: 10px; padding: 12px; break-inside: avoid; page-break-inside: avoid; }
            .screen-only, .actions, .footer-actions { display: none !important; }
            .option-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            h1 { font-size: 20px; }
            h2 { font-size: 16px; }
            .step-list li { padding: 8px 10px; min-height: 0; }
            .settings th, .settings td { padding: 8px 10px; }
            .alert, .note { padding: 10px 12px; }
        }
    </style>
</head>
<body>
    @php
        $sourceUrl = $sourceUrl ?? trim((string) $formLink->url);
        $recommendedSettings = $recommendedSettings ?? [
            'Paper / الورق' => 'A4',
            'Layout / الاتجاه' => 'Portrait / عمودي',
            'Margins / الهوامش' => 'Minimum / الحد الأدنى',
            'Scale / التحجيم' => '92% ثم 90% عند الحاجة',
            'Background graphics / رسومات الخلفية' => 'مفعّل حتى يظهر الختم والخلفيات',
            'Headers and footers / الرؤوس والتذييلات' => 'غير مفعّل',
        ];
        $legacyPreviewUrl = 'docarchive-print://preview?url=' . rawurlencode($sourceUrl) . '&title=' . rawurlencode($formLink->title);
    @endphp

    <main class="page">
        <section class="panel hero">
            <div>
                <div class="eyebrow">🖨️ صفحة فتح النموذج للطباعة</div>
                <h1>فتح النموذج بدون تغيير هيكله الأصلي</h1>
                <p class="muted">{{ $formLink->title }}</p>
                <div class="url-box">{{ $sourceUrl }}</div>
            </div>
            <div class="actions screen-only" aria-label="إجراءات فتح النموذج">
                <a href="{{ route('form-links.index') }}" class="btn">↩ رجوع لإدارة النماذج</a>
                <a href="{{ $sourceUrl }}" class="btn btn-success" target="_blank" rel="noopener noreferrer">فتح النموذج الأصلي</a>
                <a href="{{ $legacyPreviewUrl }}" class="btn btn-legacy">فتح ببرنامج المعاينة القديمة</a>
            </div>
        </section>

        <section class="alert">
            تم إيقاف أي طريقة تعيد بناء صفحة وزارة الدفاع داخل النظام، لأن ذلك قد يغيّر الجداول والصور والختم وعدد الصفحات.
            استخدم أحد الخيارين التاليين للحفاظ على النموذج كما هو.
        </section>

        <section class="option-grid">
            <article class="panel">
                <div class="option-title">
                    <h2>الخيار الأول: الطباعة من النموذج الأصلي</h2>
                    <span class="badge">1</span>
                </div>
                <ol class="step-list">
                    <li><span class="step-no">1</span><span class="step-text">اضغط زر <strong class="nowrap">فتح النموذج الأصلي</strong>.</span></li>
                    <li><span class="step-no">2</span><span class="step-text">عبّئ النموذج داخل صفحة المصدر الأصلي نفسها.</span></li>
                    <li><span class="step-no">3</span><span class="step-text">بعد الانتهاء من التعبئة اضغط <strong class="ltr">Ctrl + P</strong> من نفس التبويب.</span></li>
                    <li><span class="step-no">4</span><span class="step-text">راجع المعاينة ثم استخدم إعدادات الطباعة المقترحة بالأسفل.</span></li>
                </ol>
            </article>

            <article class="panel">
                <div class="option-title">
                    <h2>الخيار الثاني: برنامج المعاينة القديمة</h2>
                    <span class="badge">2</span>
                </div>
                <ol class="step-list">
                    <li><span class="step-no">1</span><span class="step-text">تأكد من بناء وتثبيت برنامج <strong class="ltr">DocArchivePrintPreview</strong> على جهاز ويندوز.</span></li>
                    <li><span class="step-no">2</span><span class="step-text">اضغط زر <strong class="nowrap">فتح ببرنامج المعاينة القديمة</strong>.</span></li>
                    <li><span class="step-no">3</span><span class="step-text">وافق على رسالة المتصفح التي تطلب فتح تطبيق خارجي.</span></li>
                    <li><span class="step-no">4</span><span class="step-text">عبّئ النموذج داخل نافذة البرنامج ثم اضغط <strong class="nowrap">معاينة الطباعة القديمة</strong>.</span></li>
                </ol>
            </article>
        </section>

        <section class="panel">
            <h2>إعدادات الطباعة المقترحة في Chrome / Edge</h2>
            <div class="settings-wrap">
                <table class="settings">
                    <tbody>
                        @foreach($recommendedSettings as $label => $value)
                            <tr>
                                <th>{{ $label }}</th>
                                <td>{{ $value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small muted" style="margin-top: 10px;">
                إذا ظهر النموذج في 3 صفحات، اجعل التحجيم 90%. إذا اختفى جزء من الحدود، ارجع إلى 92% أو اجعل الهوامش Minimum بدلاً من None.
            </p>
        </section>

        <section class="note">
            ملاحظة مهمة: لا يمكن للنظام قراءة البيانات التي تكتبها في تبويب موقع خارجي أو نقلها إلى تبويب آخر بسبب حماية المتصفح.
            لذلك يجب أن تكون التعبئة والطباعة في نفس صفحة النموذج الأصلي، أو داخل برنامج المعاينة القديمة نفسه.
        </section>

        <section class="footer-actions screen-only">
            <a href="{{ $sourceUrl }}" class="btn btn-success" target="_blank" rel="noopener noreferrer">فتح النموذج الأصلي</a>
            <a href="{{ $legacyPreviewUrl }}" class="btn btn-legacy">فتح ببرنامج المعاينة القديمة</a>
            <a href="{{ route('form-links.index') }}" class="btn">رجوع لإدارة النماذج</a>
        </section>
    </main>
</body>
</html>

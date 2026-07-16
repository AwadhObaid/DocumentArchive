@extends('layouts.app')

@section('title', 'التقارير الذكية')
@section('page_title', 'التقارير الذكية')
@section('page_subtitle', 'لوحة مؤشرات ورسوم بيانية وتحليل إداري من بيانات الكتب داخل النظام')

@section('content')
<link rel="stylesheet" href="{{ asset('css/smart-reports-v65.css') }}?v=65">
<link rel="stylesheet" href="{{ asset('css/smart-reports-v66.css') }}?v=66">
<link rel="stylesheet" href="{{ asset('css/smart-reports-v67-print.css') }}?v=67">
<link rel="stylesheet" href="{{ asset('css/smart-reports-v68-print-page-fix.css') }}?v=68">
<link rel="stylesheet" href="{{ asset('css/smart-reports-v69-final.css') }}?v=69">

<div class="smart-reports-page smart-reports-page-v66" dir="rtl">
    @if(session('success'))
        <div class="smart-alert smart-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="smart-alert smart-alert-danger">
            <strong>يرجى تصحيح الأخطاء التالية:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="smart-hero smart-hero-v66 smart-print-hide">
        <div>
            <span class="smart-eyebrow">تقرير تحليلي</span>
            <h2>لوحة التقارير الذكية والتحليل البياني</h2>
            <p>
                تجمع هذه الصفحة بين تحليل محلي دقيق من قاعدة البيانات ورسوم بيانية داخل النظام، ثم يصوغ النظام تقريرًا إداريًا عربيًا مع توصيات عملية قابلة للطباعة والتصدير.
                لا يتم إرسال ملفات المرفقات نفسها.
            </p>
        </div>
        <div class="smart-status-card">
            <div class="smart-status-row">
                <span>الحالة</span>
                <strong class="{{ $settings['enabled'] && $settings['api_key_configured'] ? 'ok' : 'bad' }}">
                    {{ $settings['enabled'] && $settings['api_key_configured'] ? 'جاهز للتحليل' : 'غير مكتمل الإعداد' }}
                </strong>
            </div>
            <div class="smart-status-row">
                <span>الموديل</span>
                <strong dir="ltr">{{ $settings['model'] }}</strong>
            </div>
            <div class="smart-status-row">
                <span>إرسال العناوين</span>
                <strong>{{ $settings['include_titles'] ? 'مفعل' : 'غير مفعل' }}</strong>
            </div>
            <div class="smart-status-row">
                <span>الرسوم البيانية</span>
                <strong class="ok">محلية داخل النظام</strong>
            </div>
        </div>
    </div>

    @unless($settings['enabled'] && $settings['api_key_configured'])
        <div class="smart-alert smart-alert-warning">
            التقارير الذكية تحتاج تفعيلها وإدخال Gemini API Key من صفحة
            <a href="{{ route('settings.edit') }}">الإعدادات</a>.
            لا ترسل مفتاح API في المحادثات أو عبر البريد.
        </div>
    @endunless

    <div class="smart-card smart-filter-card smart-print-hide">
        <div class="smart-card-title">
            <span>معايير البحث والتحليل</span>
            <form method="POST" action="{{ route('smart-reports.test-gemini') }}">
                @csrf
                <button type="submit" class="smart-btn smart-btn-secondary">اختبار اتصال Gemini</button>
            </form>
        </div>

        <form method="POST" action="{{ route('smart-reports.generate') }}" class="smart-form">
            @csrf

            <div class="smart-grid">
                <div class="smart-field">
                    <label>من تاريخ</label>
                    <input type="date" name="date_from" value="{{ old('date_from', $defaults['date_from']) }}" required>
                </div>

                <div class="smart-field">
                    <label>إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ old('date_to', $defaults['date_to']) }}" required>
                </div>

                <div class="smart-field">
                    <label>نوع التقرير</label>
                    <select name="report_type" required>
                        @foreach($reportTypes as $key => $label)
                            <option value="{{ $key }}" @selected(old('report_type', $defaults['report_type']) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="smart-field">
                <label>طلب التحليل أو تعليمات إضافية</label>
                <textarea name="user_prompt" rows="5" maxlength="2500" placeholder="مثال: ركّز على الشركات الأعلى تكراراً، واذكر توصيات لتحسين جودة المرفقات.">{{ old('user_prompt') }}</textarea>
                <small>اختياري. سيولّد النظام الأرقام والرسوم محليًا، ثم يرسل ملخصًا إحصائيًا إلى Gemini لصياغة التحليل.</small>
            </div>

            <div class="smart-actions">
                <button type="submit" class="smart-btn smart-btn-primary" @disabled(!($settings['enabled'] && $settings['api_key_configured']))>
                    توليد التقرير الذكي والتحليل البياني
                </button>
                <a href="{{ route('settings.edit') }}" class="smart-btn smart-btn-secondary">إعدادات Gemini</a>
            </div>
        </form>
    </div>

    @if($lastReport)
        @php
            $payload = is_array($lastReport->payload_json ?? null) ? $lastReport->payload_json : [];
            $summary = $payload['summary'] ?? [];
            $analytics = $payload['analytics'] ?? [];
            $charts = $analytics['charts'] ?? [];
            $kpis = $analytics['kpis'] ?? [];
            if ($kpis === []) {
                $total = (int) ($summary['documents_total'] ?? 0);
                $withAttachments = (int) ($summary['documents_with_attachments'] ?? 0);
                $withoutAttachments = (int) ($summary['documents_without_attachments'] ?? 0);
                $attachmentsTotal = (int) ($summary['attachments_total'] ?? 0);
                $completionRate = $total > 0 ? round(($withAttachments / $total) * 100, 1) . '%' : '0%';
                $kpis = [
                    ['label' => 'إجمالي الكتب', 'value' => $total, 'hint' => 'عدد الكتب ضمن الفترة'],
                    ['label' => 'الكتب بمرفقات', 'value' => $withAttachments, 'hint' => 'كتب تحتوي على مرفقات'],
                    ['label' => 'الكتب بدون مرفقات', 'value' => $withoutAttachments, 'hint' => 'تحتاج مراجعة'],
                    ['label' => 'إجمالي المرفقات', 'value' => $attachmentsTotal, 'hint' => 'مرفقات كتب الفترة'],
                    ['label' => 'نسبة الاكتمال', 'value' => $completionRate, 'hint' => 'الكتب التي تحتوي على مرفقات'],
                ];
            }
            $chartPayload = [
                'companies' => $charts['companies'] ?? ($payload['top_companies'] ?? []),
                'operations' => $charts['operations'] ?? ($payload['top_operations'] ?? []),
                'titles' => $charts['titles'] ?? ($payload['top_titles'] ?? []),
                'daily' => $charts['daily'] ?? ($payload['by_day'] ?? []),
                'attachment_status' => $charts['attachment_status'] ?? [
                    ['label' => 'بمرفقات', 'total' => (int) ($summary['documents_with_attachments'] ?? 0)],
                    ['label' => 'بدون مرفقات', 'total' => (int) ($summary['documents_without_attachments'] ?? 0)],
                ],
                'quality' => $charts['quality'] ?? [],
                'workflow' => $charts['workflow'] ?? ($payload['by_workflow_status'] ?? []),
                'priority' => $charts['priority'] ?? ($payload['by_priority'] ?? []),
                'attachment_types' => $charts['attachment_types'] ?? ($payload['attachment_types'] ?? []),
                'users_activity' => $charts['users_activity'] ?? ($payload['users_activity'] ?? []),
            ];
            $localInsights = $analytics['local_insights'] ?? [];
        @endphp

        <div class="smart-card smart-result-card smart-result-card-v66 smart-official-report-v69" id="smartPrintableReport">
            <div class="smart-card-title">
                <span>{{ $lastReport->title ?: 'نتيجة التقرير الذكي' }}</span>
                <div class="smart-actions compact smart-screen-only">
                    @if($lastReport->status === 'completed')
                        <button type="button" class="smart-btn smart-btn-secondary" data-copy-smart-report>نسخ النتيجة</button>
                        <button type="button" class="smart-btn smart-btn-secondary" data-smart-print>طباعة التقرير</button>
                        <a class="smart-btn smart-btn-secondary" href="{{ route('smart-reports.export-word', $lastReport) }}">تصدير Word</a>
                        <a class="smart-btn smart-btn-secondary" href="{{ route('smart-reports.export-pdf', $lastReport) }}">تصدير PDF</a>
                    @endif
                </div>
            </div>

            <div class="smart-meta smart-screen-only">
                <span>الحالة: {{ $lastReport->status_name }}</span>
                <span>الفترة: {{ optional($lastReport->date_from)->format('Y-m-d') }} إلى {{ optional($lastReport->date_to)->format('Y-m-d') }}</span>
                <span>نوع التقرير: {{ $lastReport->report_type_name }}</span>
            </div>

            <div class="smart-print-header">
                <div class="smart-print-brand">نظام أرشفة المستندات</div>
                <h1>{{ $lastReport->title ?: 'تقرير ذكي تحليلي' }}</h1>
                <p>تقرير إداري تحليلي مبني على بيانات الكتب المسجلة في النظام.</p>
                <table>
                    <tr>
                        <th>نوع التقرير</th>
                        <td>{{ $lastReport->report_type_name }}</td>
                        <th>الفترة</th>
                        <td>{{ optional($lastReport->date_from)->format('Y-m-d') }} إلى {{ optional($lastReport->date_to)->format('Y-m-d') }}</td>
                    </tr>
                    <tr>
                        <th>تاريخ التوليد</th>
                        <td>{{ $lastReport->created_at?->format('Y-m-d H:i') }}</td>
                        <th>مصدر البيانات</th>
                        <td>قاعدة بيانات النظام</td>
                    </tr>
                </table>
            </div>

            @if($lastReport->status === 'failed')
                <div class="smart-alert smart-alert-danger">{{ $lastReport->error_message }}</div>
            @else
                <section class="smart-dashboard-section" aria-label="المؤشرات الرئيسية">
                    <div class="smart-section-heading">
                        <h3>المؤشرات الرئيسية</h3>
                        <span>ملخص رقمي سريع للفترة المحددة</span>
                    </div>

                    <div class="smart-kpi-grid">
                        @foreach($kpis as $kpi)
                            <div class="smart-kpi-card">
                                <span>{{ $kpi['label'] ?? '' }}</span>
                                <strong>{{ $kpi['value'] ?? 0 }}</strong>
                                <small>{{ $kpi['hint'] ?? '' }}</small>
                            </div>
                        @endforeach
                    </div>
                </section>

                @if(!empty($localInsights))
                    <section class="smart-local-insights">
                        <div class="smart-section-heading">
                            <h3>ملاحظات تحليلية محلية</h3>
                            <span>هذه الملاحظات محسوبة داخل النظام قبل إرسال الملخص إلى Gemini</span>
                        </div>
                        <div class="smart-insight-list">
                            @foreach($localInsights as $insight)
                                <div class="smart-insight-item">{{ $insight }}</div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="smart-charts-section" aria-label="الرسوم البيانية">
                    <div class="smart-section-heading">
                        <h3>الرسوم البيانية</h3>
                        <span>تُرسم محليًا من بيانات النظام ولا تعتمد على اتصال خارجي</span>
                    </div>

                    <script type="application/json" id="smartReportChartData">@json($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>

                    <div class="smart-chart-grid">
                        <div class="smart-chart-card smart-chart-card-wide">
                            <div class="smart-chart-title">حركة الكتب حسب التاريخ</div>
                            <canvas data-smart-chart="daily" data-chart-type="line" height="220"></canvas>
                        </div>

                        <div class="smart-chart-card">
                            <div class="smart-chart-title">أكثر الشركات / الجهات</div>
                            <canvas data-smart-chart="companies" data-chart-type="horizontal-bar" height="260"></canvas>
                        </div>

                        <div class="smart-chart-card">
                            <div class="smart-chart-title">توزيع أنواع العمليات</div>
                            <canvas data-smart-chart="operations" data-chart-type="donut" height="260"></canvas>
                        </div>

                        <div class="smart-chart-card">
                            <div class="smart-chart-title">حالة المرفقات</div>
                            <canvas data-smart-chart="attachment_status" data-chart-type="donut" height="240"></canvas>
                        </div>

                        <div class="smart-chart-card">
                            <div class="smart-chart-title">مؤشرات جودة البيانات</div>
                            <canvas data-smart-chart="quality" data-chart-type="bar" height="240"></canvas>
                        </div>

                        <div class="smart-chart-card">
                            <div class="smart-chart-title">أكثر مواضيع الكتب</div>
                            <canvas data-smart-chart="titles" data-chart-type="horizontal-bar" height="280"></canvas>
                        </div>

                        <div class="smart-chart-card">
                            <div class="smart-chart-title">نشاط المستخدمين</div>
                            <canvas data-smart-chart="users_activity" data-chart-type="bar" height="240"></canvas>
                        </div>
                    </div>

                    @php
                        $printChartSets = [
                            'أكثر الشركات / الجهات' => $chartPayload['companies'] ?? [],
                            'توزيع أنواع العمليات' => $chartPayload['operations'] ?? [],
                            'حالة المرفقات' => $chartPayload['attachment_status'] ?? [],
                            'مؤشرات جودة البيانات' => $chartPayload['quality'] ?? [],
                            'أكثر مواضيع الكتب' => $chartPayload['titles'] ?? [],
                            'نشاط المستخدمين' => $chartPayload['users_activity'] ?? [],
                        ];
                    @endphp

                    <div class="smart-print-only">
                        <h3 class="smart-print-section-title">ملخص الرسوم والمؤشرات البيانية</h3>
                        @include('smart_reports.partials.chart-summary', [
                            'smartChartSets' => $printChartSets,
                            'summaryClass' => 'smart-print-chart-summary',
                            'maxRows' => 6,
                        ])
                    </div>
                </section>

                <section class="smart-ai-analysis-section">
                    <div class="smart-section-heading">
                        <h3>التحليل الذكي والتوصيات</h3>
                        <span>صياغة إدارية مبنية على المؤشرات والرسوم أعلاه</span>
                    </div>
                    <div class="smart-report-output smart-report-output-v66 smart-report-clean" id="smartReportOutput">{!! \App\Support\SmartReportTextFormatter::toHtml($lastReport->result_text) !!}</div>
                </section>
            @endif
        </div>
    @endif

    <div class="smart-card smart-print-hide">
        <div class="smart-card-title">
            <span>آخر التقارير الذكية</span>
        </div>

        <div class="smart-table-wrap">
            <table class="smart-table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>نوع التقرير</th>
                        <th>الفترة</th>
                        <th>الحالة</th>
                        <th>المستخدم</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentReports as $report)
                        <tr>
                            <td>{{ $report->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $report->report_type_name }}</td>
                            <td>{{ optional($report->date_from)->format('Y-m-d') }} → {{ optional($report->date_to)->format('Y-m-d') }}</td>
                            <td>
                                <span class="smart-badge smart-badge-{{ $report->status }}">{{ $report->status_name }}</span>
                            </td>
                            <td>{{ $report->user?->name ?? 'غير محدد' }}</td>
                            <td>
                                <a href="{{ route('smart-reports.index', ['run' => $report->id]) }}">عرض</a>
                                @if($report->status === 'completed')
                                    <span> | </span>
                                    <a href="{{ route('smart-reports.export-word', $report) }}">Word</a>
                                    <span> | </span>
                                    <a href="{{ route('smart-reports.export-pdf', $report) }}">PDF</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="smart-empty">لا توجد تقارير ذكية محفوظة حتى الآن.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="{{ asset('js/smart-reports-v66.js') }}?v=66" defer></script>
<script src="{{ asset('js/smart-reports-v68-print-page-fix.js') }}?v=68" defer></script>
<script src="{{ asset('js/smart-reports-v69-final.js') }}?v=69" defer></script>
@endsection

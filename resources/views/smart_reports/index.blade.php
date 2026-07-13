@extends('layouts.app')

@section('title', 'التقارير الذكية')
@section('page_title', 'التقارير الذكية')
@section('page_subtitle', 'توليد تقارير تحليلية عبر Gemini API من بيانات الكتب داخل النظام')

@section('content')
<link rel="stylesheet" href="{{ asset('css/smart-reports-v65.css') }}?v=65">

<div class="smart-reports-page" dir="rtl">
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

    <div class="smart-hero">
        <div>
            <h2>نموذج توليد التقارير بواسطة Gemini</h2>
            <p>
                تعتمد هذه الصفحة على ملخصات إحصائية من الكتب والمرفقات، ثم ترسل الملخص إلى Gemini لصياغة تقرير إداري عربي.
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
        </div>
    </div>

    @unless($settings['enabled'] && $settings['api_key_configured'])
        <div class="smart-alert smart-alert-warning">
            التقارير الذكية تحتاج تفعيلها وإدخال Gemini API Key من صفحة
            <a href="{{ route('settings.edit') }}">الإعدادات</a>.
            لا ترسل مفتاح API في المحادثات أو عبر البريد.
        </div>
    @endunless

    <div class="smart-card">
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
                <small>اختياري. سيستخدم النظام البيانات الإحصائية أولاً، ثم يضيف هذه التعليمات إلى الطلب.</small>
            </div>

            <div class="smart-actions">
                <button type="submit" class="smart-btn smart-btn-primary" @disabled(!($settings['enabled'] && $settings['api_key_configured']))>
                    توليد التقرير الذكي
                </button>
                <a href="{{ route('settings.edit') }}" class="smart-btn smart-btn-secondary">إعدادات Gemini</a>
            </div>
        </form>
    </div>

    @if($lastReport)
        <div class="smart-card smart-result-card">
            <div class="smart-card-title">
                <span>{{ $lastReport->title ?: 'نتيجة التقرير الذكي' }}</span>
                <div class="smart-actions compact">
                    @if($lastReport->status === 'completed')
                        <button type="button" class="smart-btn smart-btn-secondary" data-copy-smart-report>نسخ النتيجة</button>
                        <a class="smart-btn smart-btn-secondary" href="{{ route('smart-reports.export-word', $lastReport) }}">تصدير Word</a>
                        <a class="smart-btn smart-btn-secondary" href="{{ route('smart-reports.export-pdf', $lastReport) }}">تصدير PDF</a>
                    @endif
                </div>
            </div>

            <div class="smart-meta">
                <span>الحالة: {{ $lastReport->status_name }}</span>
                <span>الفترة: {{ optional($lastReport->date_from)->format('Y-m-d') }} إلى {{ optional($lastReport->date_to)->format('Y-m-d') }}</span>
                <span>الموديل: <b dir="ltr">{{ $lastReport->model }}</b></span>
            </div>

            @if($lastReport->status === 'failed')
                <div class="smart-alert smart-alert-danger">{{ $lastReport->error_message }}</div>
            @else
                <pre class="smart-report-output" id="smartReportOutput">{{ $lastReport->result_text }}</pre>
            @endif
        </div>
    @endif

    <div class="smart-card">
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

<script src="{{ asset('js/smart-reports-v65.js') }}?v=65" defer></script>
@endsection

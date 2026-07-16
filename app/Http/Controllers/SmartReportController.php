<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Setting;
use App\Models\SmartReportRun;
use App\Services\ActivityLogger;
use App\Services\GeminiSmartReportService;
use App\Support\SmartReportTextFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mpdf\Mpdf;
use Throwable;

class SmartReportController extends Controller
{
    public static function reportTypes(): array
    {
        return [
            'period_summary' => 'تقرير شامل للفترة',
            'companies' => 'تحليل الكتب حسب الشركات / الجهات',
            'subjects' => 'تحليل مواضيع الكتب',
            'attachments' => 'تحليل مرفقات الكتب',
            'missing_attachments' => 'تحليل الكتب بدون مرفقات',
            'workflow' => 'تحليل حالة الاعتماد والأرشفة',
            'users_activity' => 'تحليل نشاط الإدخال',
            'customs_export' => 'تحليل الإفراج الجمركي والتصدير',
        ];
    }

    public function index(Request $request)
    {
        $today = now()->toDateString();

        $defaults = [
            'date_from' => now()->subMonth()->toDateString(),
            'date_to' => $today,
            'report_type' => 'period_summary',
        ];

        $settings = [
            'enabled' => (string) Setting::getValue('smart_reports_enabled', '0') === '1',
            'api_key_configured' => (string) Setting::getValue('smart_reports_gemini_api_key', '') !== '',
            'model' => Setting::getString('smart_reports_gemini_model', 'gemini-3.5-flash'),
            'include_titles' => (string) Setting::getValue('smart_reports_include_titles', '0') === '1',
        ];

        $lastReport = null;
        if ($request->filled('run')) {
            $lastReport = SmartReportRun::query()
                ->whereKey($request->integer('run'))
                ->first();
        }

        $recentReports = SmartReportRun::query()
            ->with('user')
            ->latest()
            ->limit(10)
            ->get();

        return view('smart_reports.index', [
            'reportTypes' => self::reportTypes(),
            'defaults' => $defaults,
            'settings' => $settings,
            'lastReport' => $lastReport,
            'recentReports' => $recentReports,
        ]);
    }

    public function generate(Request $request, GeminiSmartReportService $gemini)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'report_type' => ['required', 'string', 'max:80'],
            'user_prompt' => ['nullable', 'string', 'max:2500'],
        ], [
            'date_from.required' => 'تاريخ البداية مطلوب.',
            'date_to.required' => 'تاريخ النهاية مطلوب.',
            'date_to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية أو مساوياً له.',
            'report_type.required' => 'نوع التقرير مطلوب.',
            'user_prompt.max' => 'تعليمات التحليل طويلة جداً.',
        ]);

        if (! array_key_exists($validated['report_type'], self::reportTypes())) {
            return back()->withErrors(['report_type' => 'نوع التقرير غير مدعوم.'])->withInput();
        }

        $from = Carbon::parse($validated['date_from'])->startOfDay();
        $to = Carbon::parse($validated['date_to'])->endOfDay();

        $payload = $this->buildReportPayload($from, $to, $validated['report_type']);
        $prompt = $this->buildPrompt($payload, (string) ($validated['user_prompt'] ?? ''));

        $run = SmartReportRun::create([
            'user_id' => Auth::id(),
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'report_type' => $validated['report_type'],
            'title' => self::reportTypes()[$validated['report_type']] . ' - ' . $from->format('Y-m-d') . ' إلى ' . $to->format('Y-m-d'),
            'status' => 'processing',
            'model' => $gemini->model(),
            'prompt' => $prompt,
            'payload_json' => $payload,
        ]);

        try {
            $result = $gemini->generate($prompt);

            $run->update([
                'status' => 'completed',
                'model' => $result['model'],
                'result_text' => SmartReportTextFormatter::clean($result['text']),
                'error_message' => null,
            ]);

            ActivityLogger::log('smart_report.generated', 'تم توليد تقرير ذكي عبر Gemini مع لوحة مؤشرات ورسوم بيانية.', $run, [
                'report_type' => $validated['report_type'],
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'model' => $result['model'],
            ]);

            return redirect()
                ->route('smart-reports.index', ['run' => $run->id])
                ->with('success', 'تم توليد التقرير الذكي بنجاح. تم تجهيز لوحة المؤشرات والرسوم البيانية.');
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            ActivityLogger::log('smart_report.failed', 'فشل توليد تقرير ذكي عبر Gemini.', $run, [
                'report_type' => $validated['report_type'],
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('smart-reports.index', ['run' => $run->id])
                ->withErrors(['gemini' => $exception->getMessage()])
                ->withInput();
        }
    }

    public function show(SmartReportRun $smartReportRun)
    {
        return redirect()->route('smart-reports.index', ['run' => $smartReportRun->id]);
    }

    public function testGemini(GeminiSmartReportService $gemini)
    {
        try {
            $answer = $gemini->testConnection();

            return back()->with('success', 'تم اختبار الاتصال بـ Gemini بنجاح. الرد: ' . mb_substr($answer, 0, 120));
        } catch (Throwable $exception) {
            return back()->withErrors(['gemini' => $exception->getMessage()]);
        }
    }

    public function exportWord(SmartReportRun $smartReportRun)
    {
        abort_unless($smartReportRun->status === 'completed', 404);

        $html = view('smart_reports.word', ['report' => $smartReportRun])->render();
        $fileName = 'smart-report-' . $smartReportRun->id . '.doc';

        return response($html)
            ->header('Content-Type', 'application/msword; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    public function exportPdf(SmartReportRun $smartReportRun)
    {
        abort_unless($smartReportRun->status === 'completed', 404);

        $html = view('smart_reports.pdf', ['report' => $smartReportRun])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'margin_top' => 10,
            'margin_right' => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
        ]);

        $mpdf->SetTitle($smartReportRun->title ?: 'تقرير ذكي تحليلي');
        $mpdf->SetAuthor('DocumentArchive');
        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="smart-report-' . $smartReportRun->id . '.pdf"');
    }

    private function buildReportPayload(Carbon $from, Carbon $to, string $reportType): array
    {
        $base = Document::query()
            ->whereBetween('reference_date', [$from->toDateString(), $to->toDateString()]);

        $total = (clone $base)->count();

        $attachmentsQuery = DocumentAttachment::query()
            ->whereHas('document', function ($query) use ($from, $to) {
                $query->whereBetween('reference_date', [$from->toDateString(), $to->toDateString()]);
            });

        $attachmentCount = (clone $attachmentsQuery)->count();
        $withoutAttachments = (clone $base)->doesntHave('attachments')->count();
        $withAttachments = max(0, $total - $withoutAttachments);
        $completionRate = $total > 0 ? round(($withAttachments / $total) * 100, 1) : 0.0;
        $averageAttachments = $total > 0 ? round($attachmentCount / $total, 2) : 0.0;

        $topCompanies = $this->groupDocuments($base, 'attachment_company_name', 12);
        $topOperations = $this->groupDocuments($base, 'attachment_category_name', 12);
        $topTitles = $this->groupDocuments($base, 'title', 12);
        $byStatus = $this->groupDocuments($base, 'status', 8);
        $byWorkflow = $this->groupDocuments($base, 'workflow_status', 8);
        $byPriority = $this->groupDocuments($base, 'priority', 8);
        $byDay = $this->documentsByDay($base);
        $attachmentTypes = $this->attachmentTypes($attachmentsQuery);
        $usersActivity = $this->usersActivity($base);

        $missingMainPolicy = (clone $base)->where(function ($query) {
            $query->whereNull('main_policy_number')->orWhere('main_policy_number', '');
        })->count();

        $missingCompany = (clone $base)->where(function ($query) {
            $query->whereNull('attachment_company_name')->orWhere('attachment_company_name', '');
        })->count();

        $missingOperation = (clone $base)->where(function ($query) {
            $query->whereNull('attachment_category_name')->orWhere('attachment_category_name', '');
        })->count();

        $dataQuality = [
            'missing_main_policy_number' => $missingMainPolicy,
            'missing_company_classification' => $missingCompany,
            'missing_operation_classification' => $missingOperation,
            'without_attachments' => $withoutAttachments,
        ];

        $payload = [
            'meta' => [
                'system' => 'DocumentArchive',
                'language' => 'ar',
                'report_type' => $reportType,
                'report_type_name' => self::reportTypes()[$reportType] ?? $reportType,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'privacy_note' => 'تم إرسال ملخص إحصائي إلى Gemini. لا يتم إرسال المرفقات.',
            ],
            'summary' => [
                'documents_total' => $total,
                'attachments_total' => $attachmentCount,
                'documents_without_attachments' => $withoutAttachments,
                'documents_with_attachments' => $withAttachments,
                'completion_rate' => $completionRate,
                'average_attachments_per_document' => $averageAttachments,
            ],
            'analytics' => [
                'kpis' => [
                    ['label' => 'إجمالي الكتب', 'value' => $total, 'hint' => 'عدد الكتب ضمن الفترة المحددة'],
                    ['label' => 'الكتب بمرفقات', 'value' => $withAttachments, 'hint' => 'كتب تحتوي على مرفق واحد على الأقل'],
                    ['label' => 'الكتب بدون مرفقات', 'value' => $withoutAttachments, 'hint' => 'تحتاج مراجعة قبل إغلاق الفترة'],
                    ['label' => 'إجمالي المرفقات', 'value' => $attachmentCount, 'hint' => 'عدد المرفقات المرتبطة بكتب الفترة'],
                    ['label' => 'نسبة الاكتمال', 'value' => $completionRate . '%', 'hint' => 'نسبة الكتب التي تحتوي على مرفقات'],
                    ['label' => 'متوسط المرفقات', 'value' => $averageAttachments, 'hint' => 'متوسط عدد المرفقات لكل كتاب'],
                ],
                'charts' => [
                    'companies' => $topCompanies,
                    'operations' => $topOperations,
                    'titles' => $topTitles,
                    'daily' => $byDay,
                    'attachment_status' => [
                        ['label' => 'بمرفقات', 'total' => $withAttachments],
                        ['label' => 'بدون مرفقات', 'total' => $withoutAttachments],
                    ],
                    'quality' => [
                        ['label' => 'بدون بوليصة رئيسية', 'total' => $missingMainPolicy],
                        ['label' => 'بدون شركة/جهة', 'total' => $missingCompany],
                        ['label' => 'بدون نوع عملية', 'total' => $missingOperation],
                        ['label' => 'بدون مرفقات', 'total' => $withoutAttachments],
                    ],
                    'workflow' => $byWorkflow,
                    'priority' => $byPriority,
                    'attachment_types' => $attachmentTypes,
                    'users_activity' => $usersActivity,
                ],
                'local_insights' => [],
            ],
            'top_companies' => $topCompanies,
            'top_operations' => $topOperations,
            'top_titles' => $topTitles,
            'by_status' => $byStatus,
            'by_workflow_status' => $byWorkflow,
            'by_priority' => $byPriority,
            'by_day' => $byDay,
            'attachment_types' => $attachmentTypes,
            'data_quality' => $dataQuality,
            'users_activity' => $usersActivity,
            'sample_records' => $this->sampleRecords($base),
        ];

        $payload['analytics']['local_insights'] = $this->buildLocalInsights($payload);

        return $this->filterPayloadForReportType($payload, $reportType);
    }

    private function buildPrompt(array $payload, string $userPrompt = ''): string
    {
        $instructions = [
            'اكتب تقريراً إدارياً احترافياً باللغة العربية بناءً على بيانات JSON التالية.',
            'لا تخترع أي رقم غير موجود في البيانات.',
            'لا تستخدم Markdown إطلاقاً. ممنوع استخدام رموز مثل # أو ** أو --- أو backticks أو الجداول النصية.',
            'اكتب العناوين كنص عربي مباشر فقط، مثل: أولاً: الملخص التنفيذي.',
            'لا تكتب داخل التقرير كلمات مثل: الحالة، الموديل، الإصدار المرئي، Gemini API، أو تفاصيل تقنية.',
            'رتب التقرير بهذه العناوين: الملخص التنفيذي، المؤشرات الرئيسية، قراءة الرسوم البيانية، المخاطر والملاحظات، التوصيات العملية.',
            'استخدم فقرات قصيرة ونقاط واضحة بلغة إدارية رسمية.',
            'اربط التحليل ببيئة قسم الشحن والتأمين، وركّز على الشركات، أنواع العمليات، المرفقات، وجودة البيانات.',
            'لا تذكر اسم الموديل أو رقم الإصدار أو تفاصيل تقنية داخل نص التقرير النهائي.',
            'إذا وجدت كتباً بدون مرفقات أو نقصاً في التصنيف فاذكرها كتوصيات جودة بيانات.',
        ];

        if (trim($userPrompt) !== '') {
            $instructions[] = 'تعليمات إضافية من المستخدم: ' . trim($userPrompt);
        }

        return implode("\n", $instructions)
            . "\n\nبيانات التقرير والتحليلات المحلية:\n"
            . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function groupDocuments($baseQuery, string $column, int $limit = 10): array
    {
        try {
            return (clone $baseQuery)
                ->selectRaw("COALESCE(NULLIF({$column}, ''), 'غير محدد') as label, COUNT(*) as total")
                ->groupBy('label')
                ->orderByDesc('total')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => ['label' => (string) $row->label, 'total' => (int) $row->total])
                ->values()
                ->all();
        } catch (Throwable $exception) {
            return [];
        }
    }

    private function documentsByDay($baseQuery): array
    {
        return (clone $baseQuery)
            ->selectRaw('DATE(reference_date) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->limit(120)
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->day, 'day' => (string) $row->day, 'total' => (int) $row->total])
            ->values()
            ->all();
    }

    private function attachmentTypes($attachmentsQuery): array
    {
        return (clone $attachmentsQuery)
            ->selectRaw("COALESCE(NULLIF(attachment_type, ''), 'غير محدد') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->label, 'total' => (int) $row->total])
            ->values()
            ->all();
    }

    private function usersActivity($baseQuery): array
    {
        return (clone $baseQuery)
            ->leftJoin('users', 'documents.created_by', '=', 'users.id')
            ->selectRaw("COALESCE(users.name, 'غير محدد') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->label, 'total' => (int) $row->total])
            ->values()
            ->all();
    }

    private function sampleRecords($baseQuery): array
    {
        $includeTitles = (string) Setting::getValue('smart_reports_include_titles', '0') === '1';

        $rows = (clone $baseQuery)
            ->latest('reference_date')
            ->limit(15)
            ->get([
                'reference_number',
                'reference_date',
                'title',
                'subject',
                'attachment_company_name',
                'attachment_category_name',
                'workflow_status',
                'priority',
            ]);

        return $rows->map(function (Document $document) use ($includeTitles) {
            $row = [
                'reference_number' => $document->reference_number,
                'reference_date' => optional($document->reference_date)->format('Y-m-d'),
                'company' => $document->attachment_company_name ?: 'غير محدد',
                'operation' => $document->attachment_category_name ?: 'غير محدد',
                'workflow_status' => $document->workflow_status ?: 'draft',
                'priority' => $document->priority ?: 'normal',
            ];

            if ($includeTitles) {
                $row['title'] = $document->title;
                $row['subject'] = mb_substr((string) $document->subject, 0, 180);
            }

            return $row;
        })->values()->all();
    }

    private function buildLocalInsights(array $payload): array
    {
        $summary = $payload['summary'] ?? [];
        $quality = $payload['data_quality'] ?? [];
        $topCompanies = $payload['top_companies'] ?? [];
        $topOperations = $payload['top_operations'] ?? [];
        $insights = [];

        $total = (int) ($summary['documents_total'] ?? 0);
        $withoutAttachments = (int) ($summary['documents_without_attachments'] ?? 0);
        $completionRate = (float) ($summary['completion_rate'] ?? 0);

        if ($total === 0) {
            return ['لا توجد كتب ضمن الفترة المحددة، لذلك لا توجد مؤشرات كافية للتحليل.'];
        }

        if ($topCompanies !== []) {
            $first = $topCompanies[0];
            $insights[] = 'أكثر شركة/جهة تكراراً هي ' . $first['label'] . ' بعدد ' . $first['total'] . ' كتاب.';
        }

        if ($topOperations !== []) {
            $first = $topOperations[0];
            $insights[] = 'أكثر نوع عملية تكراراً هو ' . $first['label'] . ' بعدد ' . $first['total'] . ' كتاب.';
        }

        $insights[] = 'نسبة اكتمال المرفقات في الفترة هي ' . $completionRate . '%.';

        if ($withoutAttachments > 0) {
            $insights[] = 'يوجد ' . $withoutAttachments . ' كتاب بدون مرفقات ويحتاج إلى مراجعة.';
        }

        if ((int) ($quality['missing_company_classification'] ?? 0) > 0 || (int) ($quality['missing_operation_classification'] ?? 0) > 0) {
            $insights[] = 'توجد سجلات تحتاج استكمال تصنيف الشركة أو نوع العملية لتحسين دقة التقارير.';
        }

        return $insights;
    }

    private function filterPayloadForReportType(array $payload, string $reportType): array
    {
        return match ($reportType) {
            'companies' => array_intersect_key($payload, array_flip(['meta', 'summary', 'analytics', 'top_companies', 'by_day', 'sample_records'])),
            'subjects' => array_intersect_key($payload, array_flip(['meta', 'summary', 'analytics', 'top_titles', 'top_operations', 'by_day', 'sample_records'])),
            'attachments' => array_intersect_key($payload, array_flip(['meta', 'summary', 'analytics', 'attachment_types', 'data_quality', 'top_companies', 'sample_records'])),
            'missing_attachments' => array_intersect_key($payload, array_flip(['meta', 'summary', 'analytics', 'data_quality', 'top_companies', 'top_operations', 'sample_records'])),
            'workflow' => array_intersect_key($payload, array_flip(['meta', 'summary', 'analytics', 'by_workflow_status', 'by_status', 'by_priority', 'sample_records'])),
            'users_activity' => array_intersect_key($payload, array_flip(['meta', 'summary', 'analytics', 'users_activity', 'by_day'])),
            'customs_export' => array_intersect_key($payload, array_flip(['meta', 'summary', 'analytics', 'top_companies', 'top_operations', 'top_titles', 'data_quality', 'sample_records'])),
            default => $payload,
        };
    }
}

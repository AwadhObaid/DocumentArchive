<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Setting;
use App\Models\SmartReportRun;
use App\Services\ActivityLogger;
use App\Services\GeminiSmartReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
                'result_text' => $result['text'],
                'error_message' => null,
            ]);

            ActivityLogger::log('smart_report.generated', 'تم توليد تقرير ذكي عبر Gemini.', $run, [
                'report_type' => $validated['report_type'],
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'model' => $result['model'],
            ]);

            return redirect()
                ->route('smart-reports.index', ['run' => $run->id])
                ->with('success', 'تم توليد التقرير الذكي بنجاح.');
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
            'margin_top' => 14,
            'margin_right' => 14,
            'margin_bottom' => 14,
            'margin_left' => 14,
        ]);

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
                'documents_with_attachments' => max(0, $total - $withoutAttachments),
            ],
            'top_companies' => $this->groupDocuments($base, 'attachment_company_name'),
            'top_operations' => $this->groupDocuments($base, 'attachment_category_name'),
            'top_titles' => $this->groupDocuments($base, 'title'),
            'by_status' => $this->groupDocuments($base, 'status'),
            'by_workflow_status' => $this->groupDocuments($base, 'workflow_status'),
            'by_priority' => $this->groupDocuments($base, 'priority'),
            'by_day' => $this->documentsByDay($base),
            'attachment_types' => $this->attachmentTypes($attachmentsQuery),
            'data_quality' => [
                'missing_main_policy_number' => (clone $base)->where(function ($query) {
                    $query->whereNull('main_policy_number')->orWhere('main_policy_number', '');
                })->count(),
                'missing_company_classification' => (clone $base)->where(function ($query) {
                    $query->whereNull('attachment_company_name')->orWhere('attachment_company_name', '');
                })->count(),
                'missing_operation_classification' => (clone $base)->where(function ($query) {
                    $query->whereNull('attachment_category_name')->orWhere('attachment_category_name', '');
                })->count(),
                'without_attachments' => $withoutAttachments,
            ],
            'users_activity' => $this->usersActivity($base),
            'sample_records' => $this->sampleRecords($base),
        ];

        return $this->filterPayloadForReportType($payload, $reportType);
    }

    private function buildPrompt(array $payload, string $userPrompt = ''): string
    {
        $instructions = [
            'اكتب تقريراً إدارياً ذكياً باللغة العربية بناءً على بيانات JSON التالية.',
            'لا تخترع أي رقم غير موجود في البيانات.',
            'ركّز على المؤشرات المهمة والتوصيات العملية لقسم الشحن والتأمين.',
            'استخدم تنسيقاً واضحاً بعناوين فرعية ونقاط مختصرة.',
            'إذا وجدت كتباً بدون مرفقات أو نقصاً في التصنيف فاذكرها كتوصيات جودة بيانات.',
        ];

        if (trim($userPrompt) !== '') {
            $instructions[] = 'تعليمات إضافية من المستخدم: ' . trim($userPrompt);
        }

        return implode("\n", $instructions)
            . "\n\nبيانات التقرير:\n"
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
                ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
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
            ->limit(90)
            ->get()
            ->map(fn ($row) => ['day' => (string) $row->day, 'total' => (int) $row->total])
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
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
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
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
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

    private function filterPayloadForReportType(array $payload, string $reportType): array
    {
        return match ($reportType) {
            'companies' => array_intersect_key($payload, array_flip(['meta', 'summary', 'top_companies', 'by_day', 'sample_records'])),
            'subjects' => array_intersect_key($payload, array_flip(['meta', 'summary', 'top_titles', 'top_operations', 'by_day', 'sample_records'])),
            'attachments' => array_intersect_key($payload, array_flip(['meta', 'summary', 'attachment_types', 'data_quality', 'top_companies', 'sample_records'])),
            'missing_attachments' => array_intersect_key($payload, array_flip(['meta', 'summary', 'data_quality', 'top_companies', 'top_operations', 'sample_records'])),
            'workflow' => array_intersect_key($payload, array_flip(['meta', 'summary', 'by_workflow_status', 'by_status', 'by_priority', 'sample_records'])),
            'users_activity' => array_intersect_key($payload, array_flip(['meta', 'summary', 'users_activity', 'by_day'])),
            'customs_export' => array_intersect_key($payload, array_flip(['meta', 'summary', 'top_companies', 'top_operations', 'top_titles', 'data_quality', 'sample_records'])),
            default => $payload,
        };
    }
}

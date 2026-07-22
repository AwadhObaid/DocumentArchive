<?php

namespace App\Http\Controllers;

use App\Models\LegacyMemoImportItem;
use App\Models\LegacyMemoImportRun;
use App\Models\Setting;
use App\Services\LegacyMemoImportExecutionService;
use App\Services\LegacyMemoInventoryService;
use App\Services\MemoNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class LegacyMemoImportController extends Controller
{
    public function index()
    {
        $this->authorizeImport();

        $runs = LegacyMemoImportRun::query()
            ->with('creator')
            ->latest()
            ->limit(20)
            ->get();

        return view('legacy_memo_import.index', [
            'runs' => $runs,
            'defaultSourceRoot' => Setting::getString(
                'legacy_memo_import_source_root'
            ),
            'nextMemo' => MemoNumberGenerator::preview(),
        ]);
    }

    public function scan(
        Request $request,
        LegacyMemoInventoryService $service
    ) {
        $this->authorizeImport();

        $validated = $request->validate([
            'source_root' => ['required', 'string', 'max:2000'],
            'recursive' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50000'],
        ], [
            'source_root.required' => 'أدخل مسار مجلد المذكرات على Server-1.',
            'limit.max' => 'الحد الأعلى لكل عملية فحص هو 50000 ملف.',
        ]);

        $sourceRoot = (string) $validated['source_root'];

        Setting::setValue(
            'legacy_memo_import_source_root',
            $sourceRoot,
            'imports',
            'text',
            'آخر مسار استُخدم لفحص المذكرات القديمة على Server-1.'
        );

        try {
            $run = $service->scan(
                $sourceRoot,
                $request->boolean('recursive', true),
                (int) ($validated['limit'] ?? 10000)
            );
        } catch (Throwable $exception) {
            return back()
                ->withErrors(['source_root' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('memo-legacy-import.show', $run)
            ->with(
                'success',
                'اكتمل الفحص التجريبي. لم تُنشأ مذكرات ولم تُنسخ أو تُحذف ملفات.'
            );
    }

    public function show(
        Request $request,
        LegacyMemoImportRun $run
    ) {
        $this->authorizeImport();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', 'all')),
        ];

        $allowedStatuses = [
            'ready',
            'needs_review',
            'duplicate_system',
            'duplicate_scan',
            'unreadable',
            'unsupported',
            'failed',
            'imported',
            'import_failed',
        ];

        if ($filters['status'] !== 'all'
            && ! in_array($filters['status'], $allowedStatuses, true)) {
            $filters['status'] = 'all';
        }

        $query = $run->items()
            ->with(['memo', 'proposedDepartment'])
            ->orderBy('id');

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['q'] !== '') {
            $term = $filters['q'];

            $query->where(function ($builder) use ($term) {
                $builder->where('original_name', 'like', '%' . $term . '%')
                    ->orWhere('relative_path', 'like', '%' . $term . '%')
                    ->orWhere('proposed_subject', 'like', '%' . $term . '%')
                    ->orWhere('proposed_memo_number', 'like', '%' . $term . '%')
                    ->orWhere('sha256', 'like', '%' . $term . '%');
            });
        }

        $items = $query->paginate(100)->withQueryString();

        return view('legacy_memo_import.show', [
            'run' => $run,
            'items' => $items,
            'filters' => $filters,
            'statusOptions' => LegacyMemoImportItem::query()
                ->where('run_id', $run->id)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
        ]);
    }

    public function import(
        Request $request,
        LegacyMemoImportRun $run,
        LegacyMemoImportExecutionService $service
    ) {
        $this->authorizeImport();

        abort_unless(
            in_array(
                $run->status,
                ['completed', 'completed_with_errors'],
                true
            ),
            422,
            'لا يمكن الاستيراد قبل اكتمال فحص المجلد بنجاح.'
        );

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*' => [
                'required',
                'integer',
                'distinct',
                'exists:legacy_memo_import_items,id',
            ],
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['nullable', 'string', 'max:5000'],
            'dates' => ['nullable', 'array'],
            'dates.*' => ['nullable', 'date'],
            'confirmation_text' => [
                'required',
                'string',
                Rule::in(['استيراد المذكرات']),
            ],
        ], [
            'items.required' => 'حدد مذكرة واحدة على الأقل للاستيراد.',
            'items.min' => 'حدد مذكرة واحدة على الأقل للاستيراد.',
            'items.max' => 'الحد الأعلى لكل دفعة استيراد هو 500 مذكرة.',
            'subjects.*.max' => 'موضوع المذكرة طويل جدًا.',
            'dates.*.date' => 'أحد تواريخ المذكرات غير صالح.',
            'confirmation_text.in'
                => 'اكتب عبارة «استيراد المذكرات» كما هي لتأكيد العملية.',
        ]);

        $selectedIds = array_values(array_unique(array_map(
            'intval',
            $validated['items']
        )));

        $matchingCount = $run->items()
            ->whereIn('id', $selectedIds)
            ->count();

        if ($matchingCount !== count($selectedIds)) {
            return back()->withErrors([
                'items' => 'تتضمن القائمة عنصرًا لا يتبع عملية الفحص الحالية.',
            ])->withInput();
        }

        $overrides = [];

        foreach ($selectedIds as $itemId) {
            $overrides[$itemId] = [
                'subject' => trim((string) $request->input(
                    'subjects.' . $itemId,
                    ''
                )),
                'memo_date' => trim((string) $request->input(
                    'dates.' . $itemId,
                    ''
                )),
            ];
        }

        $result = $service->importSelected(
            $run,
            $selectedIds,
            $overrides,
            (int) auth()->id()
        );

        $message = sprintf(
            'اكتملت دفعة الاستيراد: نجح %d، تخطى النظام %d، وفشل %d.',
            $result['imported'],
            $result['skipped'],
            $result['failed']
        );

        if ($result['failed'] > 0) {
            return redirect()
                ->route('memo-legacy-import.show', [
                    'run' => $run,
                    'status' => 'import_failed',
                ])
                ->with('warning', $message);
        }

        return redirect()
            ->route('memo-legacy-import.show', $run)
            ->with('success', $message);
    }

    private function authorizeImport(): void
    {
        $user = auth()->user();

        $allowed = $user
            && method_exists($user, 'hasPermission')
            && (
                $user->hasPermission('legacy_import.manage')
                || $user->hasPermission('settings.manage')
            );

        abort_unless(
            $allowed,
            403,
            'غير مصرح لك بفحص أو استيراد المذكرات القديمة.'
        );
    }
}

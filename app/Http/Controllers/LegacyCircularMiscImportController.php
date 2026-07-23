<?php

namespace App\Http\Controllers;

use App\Models\ArchiveCategory;
use App\Models\LegacyCircularMiscImportItem;
use App\Models\LegacyCircularMiscImportRun;
use App\Services\ActivityLogger;
use App\Services\LegacyCircularMiscImportExecutionService;
use App\Services\LegacyCircularMiscInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LegacyCircularMiscImportController extends Controller
{
    public function index()
    {
        $this->authorizeImport();

        return view('legacy_circular_misc_import.index', [
            'runs' => LegacyCircularMiscImportRun::with('creator')
                ->latest('id')
                ->paginate(15),
            'circularCategories' => $this->categories('circular'),
            'miscCategories' => $this->categories('misc_book'),
        ]);
    }

    public function store(
        Request $request,
        LegacyCircularMiscInventoryService $service
    ) {
        $this->authorizeImport();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'sources' => ['required', 'array', 'min:1', 'max:20'],
            'sources.*.source_path' => ['required', 'string', 'max:2000'],
            'sources.*.target_module' => [
                'required',
                Rule::in(['circular', 'misc_book']),
            ],
            'sources.*.category_id' => [
                'nullable',
                'integer',
                'exists:archive_categories,id',
            ],
            'sources.*.recursive' => ['nullable', 'boolean'],
            'sources.*.default_direction' => [
                'nullable',
                Rule::in(['incoming', 'outgoing', 'internal']),
            ],
            'sources.*.default_nature' => [
                'nullable',
                Rule::in(['military', 'civil', 'unspecified']),
            ],
            'sources.*.default_entity' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $run = DB::transaction(function () use ($validated) {
            $run = LegacyCircularMiscImportRun::create([
                'name' => $validated['name']
                    ?: 'فحص ' . now()->format('Y-m-d H:i'),
                'status' => 'scanning',
                'import_status' => 'not_started',
                'created_by' => Auth::id(),
                'started_at' => now(),
            ]);

            foreach ($validated['sources'] as $index => $row) {
                $categoryId = $row['category_id'] ?? null;

                if ($categoryId) {
                    $category = ArchiveCategory::find($categoryId);

                    if (! $category
                        || $category->module !== $row['target_module']) {
                        abort(
                            422,
                            'التصنيف المحدد لا يتبع الوحدة المختارة.'
                        );
                    }
                }

                $run->sources()->create([
                    'source_path' => trim($row['source_path']),
                    'target_module' => $row['target_module'],
                    'category_id' => $categoryId,
                    'recursive' => (bool) ($row['recursive'] ?? false),
                    'default_direction' => $row['target_module'] === 'misc_book'
                        ? ($row['default_direction'] ?? 'incoming')
                        : null,
                    'default_nature' => $row['target_module'] === 'misc_book'
                        ? ($row['default_nature'] ?? 'unspecified')
                        : null,
                    'default_entity' => $row['default_entity'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            return $run;
        });

        try {
            $service->scan($run);

            ActivityLogger::log(
                'legacy_circular_misc_import.scanned',
                'تم فحص مصادر التعاميم والكتب المتفرقة القديمة',
                $run,
                [
                    'run_id' => $run->id,
                    'total_files' => $run->fresh()->total_files,
                ]
            );
        } catch (\Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            report($exception);

            return redirect()
                ->route('legacy-circular-misc-import.show', $run)
                ->with(
                    'error',
                    'تعذر إكمال الفحص: ' . $exception->getMessage()
                );
        }

        return redirect()
            ->route('legacy-circular-misc-import.show', $run)
            ->with(
                'success',
                'اكتمل الفحص التجريبي. راجع البيانات ثم نفّذ الاستيراد الفعلي.'
            );
    }

    public function show(
        Request $request,
        LegacyCircularMiscImportRun $legacyCircularMiscImportRun
    ) {
        $this->authorizeImport();

        $filters = [
            'status' => (string) $request->input('status', 'all'),
            'target_module' => (string) $request->input(
                'target_module',
                'all'
            ),
            'source_id' => $request->input('source_id'),
            'q' => trim((string) $request->input('q', '')),
        ];

        $allowedStatuses = [
            'ready',
            'needs_review',
            'existing',
            'duplicate',
            'unreadable',
            'unsupported',
            'imported',
            'import_failed',
        ];

        if ($filters['status'] !== 'all'
            && ! in_array(
                $filters['status'],
                $allowedStatuses,
                true
            )) {
            $filters['status'] = 'all';
        }

        if (! in_array(
            $filters['target_module'],
            ['all', 'circular', 'misc_book'],
            true
        )) {
            $filters['target_module'] = 'all';
        }

        $query = $legacyCircularMiscImportRun->items()->with([
            'source',
            'category.parent',
            'duplicateOf',
            'circular',
            'miscBook',
        ]);

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['target_module'] !== 'all') {
            $query->where('target_module', $filters['target_module']);
        }

        if ($filters['source_id']) {
            $query->where('source_id', $filters['source_id']);
        }

        if ($filters['q'] !== '') {
            $q = $filters['q'];

            $query->where(function ($builder) use ($q) {
                $builder->where('file_name', 'like', "%{$q}%")
                    ->orWhere('source_path', 'like', "%{$q}%")
                    ->orWhere('proposed_subject', 'like', "%{$q}%")
                    ->orWhere('proposed_number', 'like', "%{$q}%")
                    ->orWhere('proposed_original_number', 'like', "%{$q}%")
                    ->orWhere('sha256', 'like', "%{$q}%");
            });
        }

        return view('legacy_circular_misc_import.show', [
            'run' => $legacyCircularMiscImportRun->load([
                'sources.category.parent',
                'creator',
                'importer',
            ]),
            'items' => $query
                ->orderBy('id')
                ->paginate(50)
                ->withQueryString(),
            'filters' => $filters,
            'circularCategories' => $this->categories('circular'),
            'miscCategories' => $this->categories('misc_book'),
            'statusOptions' => LegacyCircularMiscImportItem::query()
                ->where('run_id', $legacyCircularMiscImportRun->id)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
        ]);
    }

    public function import(
        Request $request,
        LegacyCircularMiscImportRun $legacyCircularMiscImportRun,
        LegacyCircularMiscImportExecutionService $service
    ) {
        $this->authorizeImport();

        abort_unless(
            $legacyCircularMiscImportRun->status === 'completed',
            422,
            'لا يمكن الاستيراد قبل اكتمال الفحص بنجاح.'
        );

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*' => [
                'required',
                'integer',
                'distinct',
                'exists:legacy_circular_misc_import_items,id',
            ],
            'dates' => ['nullable', 'array'],
            'dates.*' => ['nullable', 'date'],
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['nullable', 'string', 'max:5000'],
            'original_numbers' => ['nullable', 'array'],
            'original_numbers.*' => ['nullable', 'string', 'max:255'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'nullable',
                'integer',
                'exists:archive_categories,id',
            ],
            'entities' => ['nullable', 'array'],
            'entities.*' => ['nullable', 'string', 'max:255'],
            'directions' => ['nullable', 'array'],
            'directions.*' => [
                'nullable',
                Rule::in(['incoming', 'outgoing', 'internal']),
            ],
            'natures' => ['nullable', 'array'],
            'natures.*' => [
                'nullable',
                Rule::in(['military', 'civil', 'unspecified']),
            ],
            'senders' => ['nullable', 'array'],
            'senders.*' => ['nullable', 'string', 'max:255'],
            'receivers' => ['nullable', 'array'],
            'receivers.*' => ['nullable', 'string', 'max:255'],
            'employee_names' => ['nullable', 'array'],
            'employee_names.*' => ['nullable', 'string', 'max:255'],
            'authority_names' => ['nullable', 'array'],
            'authority_names.*' => ['nullable', 'string', 'max:255'],
            'confirmation_text' => [
                'required',
                'string',
                Rule::in(['استيراد التعاميم والمتفرقات']),
            ],
        ], [
            'items.required' => 'حدد ملفًا واحدًا على الأقل للاستيراد.',
            'items.min' => 'حدد ملفًا واحدًا على الأقل للاستيراد.',
            'items.max' => 'الحد الأعلى لكل دفعة هو 500 ملف.',
            'dates.*.date' => 'أحد التواريخ غير صالح.',
            'subjects.*.max' => 'أحد الموضوعات طويل جدًا.',
            'confirmation_text.in' => 'اكتب عبارة «استيراد التعاميم والمتفرقات» كما هي.',
        ]);

        $selectedIds = array_values(array_unique(array_map(
            'intval',
            $validated['items']
        )));

        $matchingCount = $legacyCircularMiscImportRun->items()
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
                'date' => trim((string) $request->input(
                    'dates.' . $itemId,
                    ''
                )),
                'subject' => trim((string) $request->input(
                    'subjects.' . $itemId,
                    ''
                )),
                'original_number' => trim((string) $request->input(
                    'original_numbers.' . $itemId,
                    ''
                )),
                'category_id' => $request->input(
                    'category_ids.' . $itemId
                ),
                'entity' => trim((string) $request->input(
                    'entities.' . $itemId,
                    ''
                )),
                'direction' => trim((string) $request->input(
                    'directions.' . $itemId,
                    ''
                )),
                'nature' => trim((string) $request->input(
                    'natures.' . $itemId,
                    ''
                )),
                'sender' => trim((string) $request->input(
                    'senders.' . $itemId,
                    ''
                )),
                'receiver' => trim((string) $request->input(
                    'receivers.' . $itemId,
                    ''
                )),
                'employee_name' => trim((string) $request->input(
                    'employee_names.' . $itemId,
                    ''
                )),
                'authority_name' => trim((string) $request->input(
                    'authority_names.' . $itemId,
                    ''
                )),
            ];
        }

        $result = $service->importSelected(
            $legacyCircularMiscImportRun,
            $selectedIds,
            $overrides,
            (int) Auth::id()
        );

        $message = sprintf(
            'اكتملت الدفعة: نجح %d، تم تجاوز %d، وفشل %d.',
            $result['imported'],
            $result['skipped'],
            $result['failed']
        );

        if ($result['failed'] > 0) {
            return redirect()
                ->route('legacy-circular-misc-import.show', [
                    'legacyCircularMiscImportRun'
                        => $legacyCircularMiscImportRun,
                    'status' => 'import_failed',
                ])
                ->with('warning', $message);
        }

        return redirect()
            ->route(
                'legacy-circular-misc-import.show',
                $legacyCircularMiscImportRun
            )
            ->with('success', $message);
    }

    public function destroy(
        LegacyCircularMiscImportRun $legacyCircularMiscImportRun
    ) {
        $this->authorizeImport();

        if ($legacyCircularMiscImportRun->items()
            ->where('status', 'imported')
            ->exists()) {
            return back()->with(
                'warning',
                'لا يمكن حذف تقرير يحتوي سجلات مستوردة؛ فهو جزء من سجل التدقيق.'
            );
        }

        ActivityLogger::log(
            'legacy_circular_misc_import.deleted',
            'تم حذف تقرير فحص التعاميم والمتفرقات القديمة',
            $legacyCircularMiscImportRun,
            ['run_id' => $legacyCircularMiscImportRun->id]
        );

        $legacyCircularMiscImportRun->delete();

        return redirect()
            ->route('legacy-circular-misc-import.index')
            ->with(
                'success',
                'تم حذف تقرير الفحص فقط. لم تُحذف ملفات Server-1.'
            );
    }

    private function categories(string $module)
    {
        return ArchiveCategory::query()
            ->forModule($module)
            ->active()
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function authorizeImport(): void
    {
        $user = Auth::user();

        $allowed = $user
            && method_exists($user, 'hasPermission')
            && (
                $user->hasPermission('legacy_import.manage')
                || $user->hasPermission('settings.manage')
            );

        abort_unless(
            $allowed,
            403,
            'غير مصرح لك بفحص أو استيراد التعاميم والكتب المتفرقة القديمة.'
        );
    }
}

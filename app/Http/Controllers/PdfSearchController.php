<?php

namespace App\Http\Controllers;

use App\Models\AttachmentTextIndex;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\PdfTextExtractionService;
use App\Services\PdfTextIndexingService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class PdfSearchController extends Controller
{
    private const SOURCE_CONFIG = [
        'documents' => [
            'type' => 'document',
            'permission' => 'documents.view',
            'label' => 'الكتب فقط',
        ],
        'memos' => [
            'type' => 'memo',
            'permission' => 'memos.view',
            'label' => 'المذكرات فقط',
        ],
        'circulars' => [
            'type' => 'circular',
            'permission' => 'circulars.view',
            'label' => 'التعاميم فقط',
        ],
        'misc_books' => [
            'type' => 'misc_book',
            'permission' => 'misc_books.view',
            'label' => 'الكتب المتفرقة فقط',
        ],
    ];

    public function index(Request $request, PdfTextExtractionService $extractor)
    {
        $permittedSources = $this->permittedSources();
        $sourceOptions = $this->sourceOptions($permittedSources);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'source' => (string) $request->input('source', 'all'),
            'status' => (string) $request->input('status', 'all'),
        ];

        if (! array_key_exists($filters['source'], $sourceOptions)) {
            $filters['source'] = 'all';
        }

        if (! in_array($filters['status'], ['all', 'indexed', 'needs_ocr', 'failed', 'missing', 'pending', 'skipped'], true)) {
            $filters['status'] = 'all';
        }

        $hasIndexTable = Schema::hasTable('attachment_text_indexes');

        if (! $hasIndexTable) {
            $indexes = new LengthAwarePaginator([], 0, 15, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
            $stats = ['total' => 0, 'indexed' => 0, 'needs_ocr' => 0, 'failed' => 0];
            $tools = $extractor->toolsStatus();
            $canIndex = auth()->user()?->hasPermission('pdf_search.index')
                && $permittedSources !== [];

            return view('pdf-search.index', compact(
                'indexes',
                'filters',
                'stats',
                'tools',
                'canIndex',
                'hasIndexTable',
                'sourceOptions'
            ));
        }

        $visibleSourceTypes = array_column($permittedSources, 'type');

        $query = AttachmentTextIndex::query()
            ->with([
                'document.department',
                'memo.department',
                'circular.category',
                'miscBook.category',
                'documentAttachment',
                'memoAttachment',
                'circularAttachment',
                'miscBookAttachment',
            ])
            ->latest('last_indexed_at')
            ->latest('id');

        $this->applyVisibleSourceScope($query, $visibleSourceTypes);

        if ($filters['source'] !== 'all') {
            $query->where('source_type', self::SOURCE_CONFIG[$filters['source']]['type']);
        }

        if ($filters['status'] !== 'all') {
            $query->where('index_status', $filters['status']);
        }

        if ($filters['q'] !== '') {
            $q = $filters['q'];

            $query->where(function ($builder) use ($q) {
                $builder->where('indexed_text', 'like', '%' . $q . '%')
                    ->orWhere('original_name', 'like', '%' . $q . '%')
                    ->orWhere('file_name', 'like', '%' . $q . '%')
                    ->orWhere(function ($sourceQuery) use ($q) {
                        $sourceQuery->where('source_type', 'document')
                            ->whereHas('document', function ($documentQuery) use ($q) {
                                $documentQuery->where('reference_number', 'like', '%' . $q . '%')
                                    ->orWhere('title', 'like', '%' . $q . '%')
                                    ->orWhere('subject', 'like', '%' . $q . '%')
                                    ->orWhere('sender', 'like', '%' . $q . '%')
                                    ->orWhere('receiver', 'like', '%' . $q . '%');
                            });
                    })
                    ->orWhere(function ($sourceQuery) use ($q) {
                        $sourceQuery->where('source_type', 'memo')
                            ->whereHas('memo', function ($memoQuery) use ($q) {
                                $memoQuery->where('memo_number', 'like', '%' . $q . '%')
                                    ->orWhere('subject', 'like', '%' . $q . '%')
                                    ->orWhere('sender', 'like', '%' . $q . '%')
                                    ->orWhere('receiver', 'like', '%' . $q . '%');
                            });
                    })
                    ->orWhere(function ($sourceQuery) use ($q) {
                        $sourceQuery->where('source_type', 'circular')
                            ->whereHas('circular', function ($circularQuery) use ($q) {
                                $circularQuery->where('circular_number', 'like', '%' . $q . '%')
                                    ->orWhere('original_number', 'like', '%' . $q . '%')
                                    ->orWhere('subject', 'like', '%' . $q . '%')
                                    ->orWhere('issuing_entity', 'like', '%' . $q . '%')
                                    ->orWhere('scope', 'like', '%' . $q . '%')
                                    ->orWhere('keywords', 'like', '%' . $q . '%');
                            });
                    })
                    ->orWhere(function ($sourceQuery) use ($q) {
                        $sourceQuery->where('source_type', 'misc_book')
                            ->whereHas('miscBook', function ($miscBookQuery) use ($q) {
                                $miscBookQuery->where('misc_number', 'like', '%' . $q . '%')
                                    ->orWhere('original_number', 'like', '%' . $q . '%')
                                    ->orWhere('subject', 'like', '%' . $q . '%')
                                    ->orWhere('sender', 'like', '%' . $q . '%')
                                    ->orWhere('receiver', 'like', '%' . $q . '%')
                                    ->orWhere('employee_name', 'like', '%' . $q . '%')
                                    ->orWhere('authority_name', 'like', '%' . $q . '%')
                                    ->orWhere('keywords', 'like', '%' . $q . '%');
                            });
                    });
            });
        }

        $indexes = $query->paginate(15)->withQueryString();

        $statsQuery = AttachmentTextIndex::query();
        $this->applyVisibleSourceScope($statsQuery, $visibleSourceTypes);

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'indexed' => (clone $statsQuery)->where('index_status', 'indexed')->count(),
            'needs_ocr' => (clone $statsQuery)->where('index_status', 'needs_ocr')->count(),
            'failed' => (clone $statsQuery)->whereIn('index_status', ['failed', 'missing'])->count(),
        ];

        $tools = $extractor->toolsStatus();
        $canIndex = auth()->user()?->hasPermission('pdf_search.index')
            && $permittedSources !== [];

        return view('pdf-search.index', compact(
            'indexes',
            'filters',
            'stats',
            'tools',
            'canIndex',
            'hasIndexTable',
            'sourceOptions'
        ));
    }

    public function run(Request $request, PdfTextIndexingService $indexingService)
    {
        abort_unless(auth()->user()?->hasPermission('pdf_search.index'), 403, 'ليست لديك صلاحية تشغيل فهرسة PDF/OCR.');

        $permittedSources = $this->permittedSources();
        $allowedSourceKeys = array_keys($permittedSources);

        abort_if($allowedSourceKeys === [], 403, 'لا توجد لديك صلاحية لعرض أي مصدر قابل للفهرسة.');

        $validated = $request->validate([
            'source' => ['required', Rule::in(array_merge(['all'], $allowedSourceKeys))],
            'limit' => ['required', 'integer', 'min:1', 'max:500'],
            'force' => ['nullable', 'boolean'],
            'enable_ocr' => ['nullable', 'boolean'],
        ], [
            'source.in' => 'مصدر الفهرسة غير صحيح أو غير مسموح لك به.',
            'limit.max' => 'الحد الأعلى للتشغيل الواحد هو 500 ملف.',
        ]);

        $enableOcr = $request->boolean('enable_ocr')
            || ((string) Setting::getValue('pdf_search_enable_ocr', '0') === '1');

        $summary = $indexingService->indexAll(
            $validated['source'],
            (int) $validated['limit'],
            $request->boolean('force'),
            $enableOcr,
            $allowedSourceKeys
        );

        ActivityLogger::log('pdf_search.indexed', 'تم تشغيل فهرسة محتوى PDF/OCR.', null, $summary + [
            'source' => $validated['source'],
            'allowed_sources' => $allowedSourceKeys,
            'force' => $request->boolean('force'),
            'enable_ocr' => $enableOcr,
        ]);

        return redirect()
            ->route('pdf-search.index')
            ->with(
                'success',
                'تم تشغيل الفهرسة. المعالجة: ' . $summary['processed']
                . ' / مفهرس: ' . $summary['indexed']
                . ' / يحتاج OCR: ' . $summary['needs_ocr']
                . ' / فشل: ' . ($summary['failed'] + $summary['missing'])
            );
    }

    public function reindex(
        AttachmentTextIndex $attachmentTextIndex,
        Request $request,
        PdfTextIndexingService $indexingService
    ) {
        abort_unless(auth()->user()?->hasPermission('pdf_search.index'), 403, 'ليست لديك صلاحية إعادة فهرسة PDF/OCR.');

        $sourceKey = match ($attachmentTextIndex->source_type) {
            'document' => 'documents',
            'memo' => 'memos',
            'circular' => 'circulars',
            'misc_book' => 'misc_books',
            default => null,
        };

        abort_unless(
            $sourceKey !== null && array_key_exists($sourceKey, $this->permittedSources()),
            403,
            'ليست لديك صلاحية إعادة فهرسة هذا المصدر.'
        );

        $enableOcr = $request->boolean('enable_ocr')
            || ((string) Setting::getValue('pdf_search_enable_ocr', '0') === '1');

        $attachment = match ($attachmentTextIndex->source_type) {
            'document' => $attachmentTextIndex->documentAttachment,
            'memo' => $attachmentTextIndex->memoAttachment,
            'circular' => $attachmentTextIndex->circularAttachment,
            'misc_book' => $attachmentTextIndex->miscBookAttachment,
            default => null,
        };

        abort_unless($attachment, 404, 'المرفق المرتبط بالفهرس غير موجود.');

        match ($attachmentTextIndex->source_type) {
            'document' => $indexingService->indexDocumentAttachment($attachment, true, $enableOcr),
            'memo' => $indexingService->indexMemoAttachment($attachment, true, $enableOcr),
            'circular' => $indexingService->indexCircularAttachment($attachment, true, $enableOcr),
            'misc_book' => $indexingService->indexMiscBookAttachment($attachment, true, $enableOcr),
        };

        ActivityLogger::log('pdf_search.reindexed', 'تمت إعادة فهرسة مرفق PDF.', $attachmentTextIndex, [
            'index_id' => $attachmentTextIndex->id,
            'source_type' => $attachmentTextIndex->source_type,
            'attachment_id' => $attachmentTextIndex->attachment_id,
            'enable_ocr' => $enableOcr,
        ]);

        return back()->with('success', 'تمت إعادة فهرسة المرفق بنجاح.');
    }

    private function permittedSources(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return array_filter(
            self::SOURCE_CONFIG,
            static fn (array $config): bool => $user->hasPermission($config['permission'])
        );
    }

    private function sourceOptions(array $permittedSources): array
    {
        $options = ['all' => 'جميع المصادر المتاحة'];

        foreach ($permittedSources as $key => $config) {
            $options[$key] = $config['label'];
        }

        return $options;
    }

    private function applyVisibleSourceScope($query, array $visibleSourceTypes): void
    {
        if ($visibleSourceTypes === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('source_type', $visibleSourceTypes);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AttachmentTextIndex;
use App\Models\CircularAttachment;
use App\Models\DocumentAttachment;
use App\Models\MemoAttachment;
use App\Models\MiscBookAttachment;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\AttachmentIndexInventoryService;
use App\Services\PdfTextExtractionService;
use App\Services\PdfTextIndexingService;
use Illuminate\Http\Request;
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

    public function index(
        Request $request,
        PdfTextExtractionService $extractor,
        AttachmentIndexInventoryService $inventory
    ) {
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

        if (! in_array($filters['status'], [
            'all',
            'unindexed',
            'indexed',
            'needs_ocr',
            'failed',
            'missing',
            'pending',
            'skipped',
            'unsupported',
        ], true)) {
            $filters['status'] = 'all';
        }

        $visibleSourceTypes = array_column($permittedSources, 'type');
        $selectedSourceType = $filters['source'] === 'all'
            ? 'all'
            : self::SOURCE_CONFIG[$filters['source']]['type'];

        $indexes = $inventory->paginate($visibleSourceTypes, [
            'q' => $filters['q'],
            'source_type' => $selectedSourceType,
            'status' => $filters['status'],
        ], 15)->withQueryString();

        $stats = $inventory->statistics($visibleSourceTypes);
        $tools = $extractor->toolsStatus();
        $hasIndexTable = Schema::hasTable('attachment_text_indexes');
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

    public function indexAttachment(
        string $sourceType,
        int $attachmentId,
        Request $request,
        PdfTextIndexingService $indexingService
    ) {
        abort_unless(auth()->user()?->hasPermission('pdf_search.index'), 403, 'ليست لديك صلاحية فهرسة المرفقات.');
        $this->authorizeSourceType($sourceType);

        $enableOcr = $request->boolean('enable_ocr')
            || ((string) Setting::getValue('pdf_search_enable_ocr', '0') === '1');
        $force = $request->boolean('force');

        $attachment = match ($sourceType) {
            'document' => DocumentAttachment::query()->findOrFail($attachmentId),
            'memo' => MemoAttachment::query()->findOrFail($attachmentId),
            'circular' => CircularAttachment::query()->findOrFail($attachmentId),
            'misc_book' => MiscBookAttachment::query()->findOrFail($attachmentId),
        };

        $index = match ($sourceType) {
            'document' => $indexingService->indexDocumentAttachment($attachment, $force, $enableOcr),
            'memo' => $indexingService->indexMemoAttachment($attachment, $force, $enableOcr),
            'circular' => $indexingService->indexCircularAttachment($attachment, $force, $enableOcr),
            'misc_book' => $indexingService->indexMiscBookAttachment($attachment, $force, $enableOcr),
        };

        ActivityLogger::log('pdf_search.attachment_indexed', 'تمت فهرسة مرفق واحد.', $attachment, [
            'source_type' => $sourceType,
            'attachment_id' => $attachmentId,
            'index_status' => $index->index_status,
            'enable_ocr' => $enableOcr,
            'force' => $force,
        ]);

        return back()->with('success', 'تمت معالجة المرفق. الحالة: ' . $index->status_name . '.');
    }

    public function indexRecord(
        string $sourceType,
        int $sourceId,
        Request $request,
        PdfTextIndexingService $indexingService
    ) {
        abort_unless(auth()->user()?->hasPermission('pdf_search.index'), 403, 'ليست لديك صلاحية فهرسة المرفقات.');
        $this->authorizeSourceType($sourceType);

        $enableOcr = $request->boolean('enable_ocr')
            || ((string) Setting::getValue('pdf_search_enable_ocr', '0') === '1');

        $summary = $indexingService->indexRecord(
            $sourceType,
            $sourceId,
            $request->boolean('force'),
            $enableOcr
        );

        ActivityLogger::log('pdf_search.record_indexed', 'تم تشغيل فهرسة مرفقات سجل واحد.', null, $summary + [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'enable_ocr' => $enableOcr,
        ]);

        return back()->with(
            'success',
            'تمت معالجة ' . $summary['processed'] . ' مرفق؛ مفهرس: ' . $summary['indexed']
            . '، يحتاج OCR: ' . $summary['needs_ocr']
            . '، فشل أو مفقود: ' . ($summary['failed'] + $summary['missing']) . '.'
        );
    }

    private function authorizeSourceType(string $sourceType): void
    {
        $sourceKey = match ($sourceType) {
            'document' => 'documents',
            'memo' => 'memos',
            'circular' => 'circulars',
            'misc_book' => 'misc_books',
            default => null,
        };

        abort_unless(
            $sourceKey !== null && array_key_exists($sourceKey, $this->permittedSources()),
            403,
            'ليست لديك صلاحية فهرسة هذا المصدر.'
        );
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

}

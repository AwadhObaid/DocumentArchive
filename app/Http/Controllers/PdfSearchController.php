<?php

namespace App\Http\Controllers;

use App\Models\AttachmentTextIndex;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\PdfTextExtractionService;
use App\Services\PdfTextIndexingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;

class PdfSearchController extends Controller
{
    public function index(Request $request, PdfTextExtractionService $extractor)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'source' => (string) $request->input('source', 'all'),
            'status' => (string) $request->input('status', 'all'),
        ];

        if (! in_array($filters['source'], ['all', 'documents', 'memos'], true)) {
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
            $canIndex = auth()->user()?->hasPermission('pdf_search.index');

            return view('pdf-search.index', compact('indexes', 'filters', 'stats', 'tools', 'canIndex', 'hasIndexTable'));
        }

        $query = AttachmentTextIndex::query()
            ->with(['document.department', 'memo.department', 'documentAttachment', 'memoAttachment'])
            ->latest('last_indexed_at')
            ->latest('id');

        if ($filters['source'] === 'documents') {
            $query->where('source_type', 'document');
        } elseif ($filters['source'] === 'memos') {
            $query->where('source_type', 'memo');
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
                    });
            });
        }

        $indexes = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => AttachmentTextIndex::query()->count(),
            'indexed' => AttachmentTextIndex::query()->where('index_status', 'indexed')->count(),
            'needs_ocr' => AttachmentTextIndex::query()->where('index_status', 'needs_ocr')->count(),
            'failed' => AttachmentTextIndex::query()->whereIn('index_status', ['failed', 'missing'])->count(),
        ];

        $tools = $extractor->toolsStatus();
        $canIndex = auth()->user()?->hasPermission('pdf_search.index');

        return view('pdf-search.index', compact('indexes', 'filters', 'stats', 'tools', 'canIndex', 'hasIndexTable'));
    }

    public function run(Request $request, PdfTextIndexingService $indexingService)
    {
        abort_unless(auth()->user()?->hasPermission('pdf_search.index'), 403, 'ليست لديك صلاحية تشغيل فهرسة PDF/OCR.');

        $validated = $request->validate([
            'source' => ['required', 'in:all,documents,memos'],
            'limit' => ['required', 'integer', 'min:1', 'max:500'],
            'force' => ['nullable', 'boolean'],
            'enable_ocr' => ['nullable', 'boolean'],
        ], [
            'source.in' => 'مصدر الفهرسة غير صحيح.',
            'limit.max' => 'الحد الأعلى للتشغيل الواحد هو 500 ملف.',
        ]);

        $enableOcr = $request->boolean('enable_ocr') || ((string) Setting::getValue('pdf_search_enable_ocr', '0') === '1');

        $summary = $indexingService->indexAll(
            $validated['source'],
            (int) $validated['limit'],
            $request->boolean('force'),
            $enableOcr
        );

        ActivityLogger::log('pdf_search.indexed', 'تم تشغيل فهرسة محتوى PDF/OCR.', null, $summary + [
            'source' => $validated['source'],
            'force' => $request->boolean('force'),
            'enable_ocr' => $enableOcr,
        ]);

        return redirect()
            ->route('pdf-search.index')
            ->with('success', 'تم تشغيل الفهرسة. المعالجة: ' . $summary['processed'] . ' / مفهرس: ' . $summary['indexed'] . ' / يحتاج OCR: ' . $summary['needs_ocr'] . ' / فشل: ' . ($summary['failed'] + $summary['missing']));
    }

    public function reindex(AttachmentTextIndex $attachmentTextIndex, Request $request, PdfTextIndexingService $indexingService)
    {
        abort_unless(auth()->user()?->hasPermission('pdf_search.index'), 403, 'ليست لديك صلاحية إعادة فهرسة PDF/OCR.');

        $enableOcr = $request->boolean('enable_ocr') || ((string) Setting::getValue('pdf_search_enable_ocr', '0') === '1');

        if ($attachmentTextIndex->source_type === 'memo') {
            $attachment = $attachmentTextIndex->memoAttachment;
            abort_unless($attachment, 404, 'مرفق المذكرة غير موجود.');
            $indexingService->indexMemoAttachment($attachment, true, $enableOcr);
        } else {
            $attachment = $attachmentTextIndex->documentAttachment;
            abort_unless($attachment, 404, 'مرفق الكتاب غير موجود.');
            $indexingService->indexDocumentAttachment($attachment, true, $enableOcr);
        }

        ActivityLogger::log('pdf_search.reindexed', 'تمت إعادة فهرسة مرفق PDF.', $attachmentTextIndex, [
            'index_id' => $attachmentTextIndex->id,
            'source_type' => $attachmentTextIndex->source_type,
            'attachment_id' => $attachmentTextIndex->attachment_id,
            'enable_ocr' => $enableOcr,
        ]);

        return back()->with('success', 'تمت إعادة فهرسة المرفق بنجاح.');
    }
}

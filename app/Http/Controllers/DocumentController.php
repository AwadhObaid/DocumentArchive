<?php

namespace App\Http\Controllers;

use App\Models\BookSubject;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentType;
use App\Models\Setting;
use App\Services\ReferenceNumberGenerator;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        /* DOCUMENTS_SEARCH_POLISH_CONTROLLER_START */
        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $documentTypes = DocumentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $bookSubjects = BookSubject::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $hasColumnCache = [];
        $hasColumn = function (string $column) use (&$hasColumnCache): bool {
            if (array_key_exists($column, $hasColumnCache)) {
                return $hasColumnCache[$column];
            }

            try {
                return $hasColumnCache[$column] = \Illuminate\Support\Facades\Schema::hasColumn('documents', $column);
            } catch (\Throwable $e) {
                return $hasColumnCache[$column] = false;
            }
        };

        $user = Auth::user();
        $canViewTrashed = $user && method_exists($user, 'hasPermission') && $user->hasPermission('documents.restore');

        $recordState = (string) $request->input('record_state', 'active');
        if (!in_array($recordState, ['active', 'trashed', 'all'], true)) {
            $recordState = 'active';
        }

        if (!$canViewTrashed && in_array($recordState, ['trashed', 'all'], true)) {
            $recordState = 'active';
        }

        $baseQuery = match ($recordState) {
            'trashed' => Document::onlyTrashed(),
            'all' => Document::withTrashed(),
            default => Document::query(),
        };

        $baseQuery->with(['department', 'documentType', 'bookSubject', 'mainAttachment']);

        try {
            $baseQuery->withCount('attachments');
        } catch (\Throwable $e) {
            // في حال كان المشروع يحتوي نسخة قديمة من العلاقات، لا نعطل صفحة الكتب.
        }

        $normalizeToken = function (?string $value): ?string {
            if ($value === null) {
                return null;
            }

            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }

            $value = preg_replace('/\s+/u', '', $value) ?: $value;

            return $value === '' ? null : $value;
        };

        $applyNormalizedLike = function ($query, string $column, ?string $value, string $boolean = 'and') use ($normalizeToken): void {
            $value = $normalizeToken($value);
            if ($value === null) {
                return;
            }

            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
            $columnExpression = 'documents.' . $column;

            $query->{$method}(
                "REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE({$columnExpression}, '')), ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), '') LIKE ?",
                ['%' . $value . '%']
            );
        };

        $applyFilters = function ($query) use ($request, $hasColumn, $applyNormalizedLike) {
            if ($request->filled('q')) {
                $q = trim((string) $request->q);

                $query->where(function ($subQuery) use ($q, $hasColumn, $applyNormalizedLike) {
                    foreach ([
                        'reference_number',
                        'title',
                        'subject',
                        'sender',
                        'receiver',
                        'main_policy_number',
                        'sub_policy_number',
                        'description',
                        'notes',
                        'search_text',
                    ] as $column) {
                        if ($hasColumn($column)) {
                            $subQuery->orWhere('documents.' . $column, 'like', "%{$q}%");
                        }
                    }

                    foreach (['reference_number', 'main_policy_number', 'sub_policy_number'] as $numberColumn) {
                        if ($hasColumn($numberColumn)) {
                            $applyNormalizedLike($subQuery, $numberColumn, $q, 'or');
                        }
                    }

                    $subQuery->orWhereHas('department', function ($departmentQuery) use ($q) {
                        $departmentQuery->where('name', 'like', "%{$q}%");
                    });

                    $subQuery->orWhereHas('documentType', function ($typeQuery) use ($q) {
                        $typeQuery->where('name', 'like', "%{$q}%");
                    });

                    $subQuery->orWhereHas('bookSubject', function ($subjectQuery) use ($q) {
                        $subjectQuery->where('name', 'like', "%{$q}%");
                    });
                });
            }

            if ($request->filled('reference_number') && $hasColumn('reference_number')) {
                $applyNormalizedLike($query, 'reference_number', (string) $request->reference_number);
            }

            if ($request->filled('main_policy_number') && $hasColumn('main_policy_number')) {
                $applyNormalizedLike($query, 'main_policy_number', (string) $request->main_policy_number);
            }

            if ($request->filled('sub_policy_number') && $hasColumn('sub_policy_number')) {
                $applyNormalizedLike($query, 'sub_policy_number', (string) $request->sub_policy_number);
            }

            if ($request->filled('title') && $hasColumn('title')) {
                $query->where('documents.title', 'like', '%' . trim((string) $request->title) . '%');
            }

            if ($request->filled('subject') && $hasColumn('subject')) {
                $query->where('documents.subject', 'like', '%' . trim((string) $request->subject) . '%');
            }

            if ($request->filled('sender') && $hasColumn('sender')) {
                $query->where('documents.sender', 'like', '%' . trim((string) $request->sender) . '%');
            }

            if ($request->filled('receiver') && $hasColumn('receiver')) {
                $query->where('documents.receiver', 'like', '%' . trim((string) $request->receiver) . '%');
            }

            if ($request->filled('department_id') && $hasColumn('department_id')) {
                $query->where('documents.department_id', $request->department_id);
            }

            if ($request->filled('document_type_id') && $hasColumn('document_type_id')) {
                $query->where('documents.document_type_id', $request->document_type_id);
            }

            if ($request->filled('book_subject_id') && $hasColumn('book_subject_id')) {
                $query->where('documents.book_subject_id', $request->book_subject_id);
            }

            if ($request->filled('status') && $hasColumn('status')) {
                $query->where('documents.status', $request->status);
            }

            if ($request->filled('priority') && $hasColumn('priority')) {
                $query->where('documents.priority', $request->priority);
            }

            if ($request->filled('confidentiality') && $hasColumn('confidentiality')) {
                $query->where('documents.confidentiality', $request->confidentiality);
            }

            if ($request->filled('date_from') && $hasColumn('reference_date')) {
                $query->whereDate('documents.reference_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to') && $hasColumn('reference_date')) {
                $query->whereDate('documents.reference_date', '<=', $request->date_to);
            }

            if ($request->filled('created_from') && $hasColumn('created_at')) {
                $query->whereDate('documents.created_at', '>=', $request->created_from);
            }

            if ($request->filled('created_to') && $hasColumn('created_at')) {
                $query->whereDate('documents.created_at', '<=', $request->created_to);
            }

            if ($request->filled('has_attachment')) {
                if ($request->has_attachment === 'yes') {
                    $query->whereHas('attachments');
                } elseif ($request->has_attachment === 'no') {
                    $query->whereDoesntHave('attachments');
                }
            }

            return $query;
        };

        $documentsQuery = $applyFilters(clone $baseQuery);
        $filteredCountQuery = clone $documentsQuery;

        $defaultSort = $recordState === 'trashed' && $hasColumn('deleted_at') ? 'deleted_at' : 'created_at';
        $allowedSorts = [
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'reference_date' => 'reference_date',
            'reference_number' => 'reference_number',
            'title' => 'title',
            'main_policy_number' => 'main_policy_number',
            'sub_policy_number' => 'sub_policy_number',
            'deleted_at' => 'deleted_at',
        ];

        $sortKey = (string) $request->get('sort', $defaultSort);
        $sort = $allowedSorts[$sortKey] ?? $defaultSort;
        if (!$hasColumn($sort)) {
            $sort = $hasColumn('created_at') ? 'created_at' : 'id';
        }

        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->get('per_page', 15);
        if (!in_array($perPage, [15, 25, 50, 100], true)) {
            $perPage = 15;
        }

        $documents = $documentsQuery
            ->orderBy('documents.' . $sort, $direction)
            ->orderByDesc('documents.id')
            ->paginate($perPage)
            ->withQueryString();

        $filteredTotal = (int) (clone $filteredCountQuery)->count();

        try {
            $filteredWithAttachments = (int) (clone $filteredCountQuery)->whereHas('attachments')->count();
        } catch (\Throwable $e) {
            $filteredWithAttachments = 0;
        }

        $summary = [
            'total' => (int) Document::query()->count(),
            'trashed_total' => (int) Document::onlyTrashed()->count(),
            'all_total' => (int) Document::withTrashed()->count(),
            'filtered' => $filteredTotal,
            'with_attachments' => $filteredWithAttachments,
            'without_attachments' => max(0, $filteredTotal - $filteredWithAttachments),
        ];

        $statusOptions = [
            'active' => 'نشط',
            'archived' => 'مؤرشف',
            'cancelled' => 'ملغي',
        ];

        $priorityOptions = [
            'normal' => 'عادي',
            'high' => 'هام',
            'urgent' => 'عاجل',
        ];

        $confidentialityOptions = [
            'normal' => 'عادي',
            'confidential' => 'سري',
            'very_confidential' => 'سري جداً',
        ];

        $recordStateOptions = [
            'active' => 'الكتب النشطة فقط',
            'trashed' => 'سلة المحذوفات فقط',
            'all' => 'النشطة والمحذوفة',
        ];

        if (!$canViewTrashed) {
            $recordStateOptions = ['active' => 'الكتب النشطة فقط'];
        }

        $sortOptions = [
            'created_at' => 'تاريخ الإضافة',
            'updated_at' => 'آخر تعديل',
            'reference_date' => 'تاريخ الكتاب',
            'reference_number' => 'رقم الكتاب',
            'main_policy_number' => 'البوليصة الرئيسية',
            'sub_policy_number' => 'البوليصة الفرعية',
            'title' => 'العنوان',
        ];

        if ($hasColumn('deleted_at')) {
            $sortOptions['deleted_at'] = 'تاريخ الحذف';
        }

        $filterKeys = [
            'q',
            'reference_number',
            'main_policy_number',
            'sub_policy_number',
            'title',
            'subject',
            'sender',
            'receiver',
            'department_id',
            'document_type_id',
            'book_subject_id',
            'status',
            'priority',
            'confidentiality',
            'date_from',
            'date_to',
            'created_from',
            'created_to',
            'has_attachment',
        ];

        $activeFiltersCount = 0;
        foreach ($filterKeys as $key) {
            if ($request->filled($key)) {
                $activeFiltersCount++;
            }
        }

        if ($recordState !== 'active') {
            $activeFiltersCount++;
        }

        if ((string) $request->get('sort', $defaultSort) !== $defaultSort) {
            $activeFiltersCount++;
        }

        if ((string) $request->get('direction', 'desc') !== 'desc') {
            $activeFiltersCount++;
        }

        if ((int) $request->get('per_page', 15) !== 15) {
            $activeFiltersCount++;
        }

        return view('documents.index', compact(
            'documents',
            'departments',
            'documentTypes',
            'bookSubjects',
            'summary',
            'statusOptions',
            'priorityOptions',
            'confidentialityOptions',
            'recordStateOptions',
            'sortOptions',
            'recordState',
            'defaultSort',
            'perPage',
            'direction',
            'activeFiltersCount',
            'canViewTrashed'
        ));
        /* DOCUMENTS_SEARCH_POLISH_CONTROLLER_END */
    }

    public function create()
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $documentTypes = DocumentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $bookSubjects = BookSubject::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $initialNextReference = ReferenceNumberGenerator::preview(date('Y-m-d'));

        return view('documents.create', compact('departments', 'documentTypes', 'bookSubjects', 'initialNextReference'));
    }

    public function nextReferenceNumber(Request $request)
    {
        $validated = $request->validate([
            'reference_date' => ['nullable', 'date'],
        ]);

        $preview = ReferenceNumberGenerator::preview($validated['reference_date'] ?? null);

        return response()->json([
            'ok' => true,
            'reference_number' => $preview['reference_number'],
            'reference_year' => $preview['reference_year'],
            'reference_sequence' => $preview['reference_sequence'],
            'reference_date' => $preview['reference_date'],
            'start_number' => $preview['start_number'],
            'reserved' => false,
            'message' => 'هذا رقم مبدئي للعرض فقط، ويتم حجز الرقم النهائي عند الحفظ.',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reference_date' => ['required', 'date'],
            'main_policy_number' => ['nullable', 'string', 'max:255'],
            'sub_policy_number' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'book_subject_id' => ['nullable', 'exists:book_subjects,id'],
            'subject' => ['nullable', 'string', 'required_without:book_subject_id'],
            'description' => ['nullable', 'string'],
            'sender' => ['nullable', 'string', 'max:255'],
            'receiver' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'confidentiality' => ['required', 'in:normal,confidential,very_confidential'],
            'priority' => ['required', 'in:normal,high,urgent'],
            'attachment' => ['nullable', 'file', 'max:20480'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['main_policy_number'] = $this->normalizeDocumentNumber($validated['main_policy_number'] ?? null);
        $validated['sub_policy_number'] = $this->normalizeDocumentNumber($validated['sub_policy_number'] ?? null);
        $validated = $this->resolveBookSubjectData($validated);

        $document = DB::transaction(function () use ($request, $validated) {
            $reference = ReferenceNumberGenerator::generate($validated['reference_date']);

            $document = Document::create([
                'reference_number' => $reference['reference_number'],
                'reference_year' => $reference['reference_year'],
                'reference_sequence' => $reference['reference_sequence'],
                'reference_date' => $reference['reference_date'],

                'main_policy_number' => $validated['main_policy_number'] ?? null,
                'sub_policy_number' => $validated['sub_policy_number'] ?? null,

                'title' => $validated['title'],
                'subject' => $validated['subject'] ?? null,
                'book_subject_id' => $validated['book_subject_id'] ?? null,
                'description' => $validated['description'] ?? null,
                'sender' => $validated['sender'] ?? null,
                'receiver' => $validated['receiver'] ?? null,

                'department_id' => $validated['department_id'] ?? null,
                'document_type_id' => $validated['document_type_id'] ?? null,
                'created_by' => Auth::id(),

                'status' => 'active',
                'workflow_status' => 'draft',
                'confidentiality' => $validated['confidentiality'],
                'priority' => $validated['priority'],

                'print_title' => Setting::getValue('print_department_title', 'الشحن والتأمين'),
                'print_top_mm' => Setting::getValue('print_top_mm', '53.30'),
                'print_left_mm' => Setting::getValue('print_left_mm', '30.80'),

                'search_text' => $this->buildSearchText($validated),
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($request->hasFile('attachment')) {
                $this->storeAttachment($request, $document);
            }

            return $document;
        });

        ActivityLogger::log(
            'document.created',
            'تم إنشاء الكتاب رقم ' . $document->reference_number,
            $document,
            ['reference_number' => $document->reference_number]
        );

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'تم إنشاء الكتاب وتوليد رقم الكتاب بنجاح.');
    }

    public function show(Document $document)
    {
        $document->load(['department', 'documentType', 'bookSubject', 'attachments.textIndex', 'workflowActions.actor', 'workflowSubmitter', 'workflowReviewer', 'workflowFinalizer']);

        return view('documents.show', compact('document'));
    }

    public function edit(Document $document)
    {
        $this->ensureDocumentCanBeModified($document);

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $documentTypes = DocumentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $bookSubjects = BookSubject::query()
            ->where(function ($query) use ($document) {
                $query->where('is_active', true);
                if ($document->book_subject_id) {
                    $query->orWhere('id', $document->book_subject_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $document->load(['department', 'documentType', 'bookSubject', 'attachments.textIndex']);

        return view('documents.edit', compact('document', 'departments', 'documentTypes', 'bookSubjects'));
    }

    public function update(Request $request, Document $document)
    {
        $this->ensureDocumentCanBeModified($document);

        $validated = $request->validate([
            'reference_date' => ['required', 'date'],
            'main_policy_number' => ['nullable', 'string', 'max:255'],
            'sub_policy_number' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'book_subject_id' => ['nullable', 'exists:book_subjects,id'],
            'subject' => ['nullable', 'string', 'required_without:book_subject_id'],
            'description' => ['nullable', 'string'],
            'sender' => ['nullable', 'string', 'max:255'],
            'receiver' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'confidentiality' => ['required', 'in:normal,confidential,very_confidential'],
            'priority' => ['required', 'in:normal,high,urgent'],
            'status' => ['required', 'in:active,archived,cancelled'],
            'attachment' => ['nullable', 'file', 'max:20480'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['main_policy_number'] = $this->normalizeDocumentNumber($validated['main_policy_number'] ?? null);
        $validated['sub_policy_number'] = $this->normalizeDocumentNumber($validated['sub_policy_number'] ?? null);
        $validated = $this->resolveBookSubjectData($validated);

        DB::transaction(function () use ($request, $document, $validated) {
            $document->update([
                'reference_date' => $validated['reference_date'],
                'main_policy_number' => $validated['main_policy_number'] ?? null,
                'sub_policy_number' => $validated['sub_policy_number'] ?? null,
                'title' => $validated['title'],
                'subject' => $validated['subject'] ?? null,
                'book_subject_id' => $validated['book_subject_id'] ?? null,
                'description' => $validated['description'] ?? null,
                'sender' => $validated['sender'] ?? null,
                'receiver' => $validated['receiver'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'document_type_id' => $validated['document_type_id'] ?? null,
                'status' => $validated['status'],
                'confidentiality' => $validated['confidentiality'],
                'priority' => $validated['priority'],
                'search_text' => $this->buildSearchText($validated),
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($request->hasFile('attachment')) {
                DocumentAttachment::query()
                    ->where('document_id', $document->id)
                    ->where('is_main', true)
                    ->update(['is_main' => false]);

                $this->storeAttachment($request, $document);
            }
        });

        ActivityLogger::log(
            'document.updated',
            'تم تعديل بيانات الكتاب رقم ' . $document->reference_number,
            $document,
            ['reference_number' => $document->reference_number]
        );

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'تم تحديث بيانات الكتاب بنجاح.');
    }

    public function destroy(Document $document)
    {
        $this->ensureDocumentCanBeModified($document);

        ActivityLogger::log(
            'document.deleted',
            'تم حذف الكتاب رقم ' . $document->reference_number . ' ونقله إلى سلة المحذوفات',
            $document,
            ['reference_number' => $document->reference_number]
        );

        $document->delete();

        return redirect()
            ->route('documents.index')
            ->with('success', 'تم حذف الكتاب ونقله إلى سلة المحذوفات.');
    }

    public function trash()
    {
        $documents = Document::onlyTrashed()
            ->with(['department', 'documentType'])
            ->latest('deleted_at')
            ->paginate(15);

        return view('documents.trash', compact('documents'));
    }

    public function restore(int $id)
    {
        $document = Document::onlyTrashed()->findOrFail($id);
        $document->restore();

        ActivityLogger::log(
            'document.restored',
            'تمت استعادة الكتاب رقم ' . $document->reference_number,
            $document,
            ['reference_number' => $document->reference_number]
        );

        return redirect()
            ->route('documents.trash')
            ->with('success', 'تمت استعادة الكتاب بنجاح.');
    }

    public function forceDelete(int $id)
    {
        $document = Document::onlyTrashed()
            ->with('attachments')
            ->findOrFail($id);

        foreach ($document->attachments as $attachment) {
            $disk = Storage::disk($attachment->disk ?: 'local');

            if ($disk->exists($attachment->file_path)) {
                $disk->delete($attachment->file_path);
            }
        }

        ActivityLogger::log(
            'document.force_deleted',
            'تم حذف الكتاب رقم ' . $document->reference_number . ' نهائياً',
            $document,
            ['reference_number' => $document->reference_number]
        );

        $document->forceDelete();

        return redirect()
            ->route('documents.trash')
            ->with('success', 'تم حذف الكتاب نهائياً.');
    }

    public function printReference(Document $document)
    {
        ActivityLogger::log(
            'document.printed',
            'تمت طباعة رقم الكتاب ' . $document->reference_number,
            $document,
            ['reference_number' => $document->reference_number]
        );

        return view('documents.print-reference', compact('document'));
    }

    public function previewAttachment(DocumentAttachment $attachment)
    {
        $this->authorizeAttachmentPreview($attachment);
        $attachment->load('document');

        ActivityLogger::log(
            'attachment.previewed',
            'تمت معاينة المرفق: ' . ($attachment->original_name ?: $attachment->file_name),
            $attachment,
            [
                'document_id' => $attachment->document_id,
                'reference_number' => $attachment->document?->reference_number,
            ]
        );

        return view('attachments.preview', compact('attachment'));
    }

    public function attachmentData(DocumentAttachment $attachment)
    {
        $this->authorizeAttachmentPreview($attachment);

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (!$disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        $extension = strtolower(
            $attachment->extension ?: pathinfo($attachment->original_name, PATHINFO_EXTENSION)
        );

        $mimeType = match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => $attachment->mime_type ?: 'application/octet-stream',
        };

        $binary = $disk->get($attachment->file_path);

        return response()->json([
            'id' => $attachment->id,
            'name' => $attachment->original_name ?: $attachment->file_name,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $attachment->file_size,
            'base64' => base64_encode($binary),
        ]);
    }

    public function inlineAttachment(DocumentAttachment $attachment)
    {
        $this->authorizeAttachmentPreview($attachment);

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (!$disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        $extension = strtolower(
            $attachment->extension ?: pathinfo($attachment->original_name, PATHINFO_EXTENSION)
        );

        $mimeType = match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => $attachment->mime_type ?: 'application/octet-stream',
        };

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="preview.' . $extension . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (method_exists($disk, 'path')) {
            return response()->file($disk->path($attachment->file_path), $headers);
        }

        return response()->stream(function () use ($disk, $attachment) {
            $stream = $disk->readStream($attachment->file_path);

            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }

    public function downloadAttachment(DocumentAttachment $attachment)
    {
        $this->authorizeAttachmentDownload($attachment);
        $attachment->load('document');

        ActivityLogger::log(
            'attachment.downloaded',
            'تم تنزيل المرفق: ' . ($attachment->original_name ?: $attachment->file_name),
            $attachment,
            [
                'document_id' => $attachment->document_id,
                'reference_number' => $attachment->document?->reference_number,
            ]
        );

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (!$disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        $fileName = $attachment->original_name ?: $attachment->file_name;

        if (method_exists($disk, 'path')) {
            return response()->download(
                $disk->path($attachment->file_path),
                $fileName
            );
        }

        return response()->streamDownload(function () use ($disk, $attachment) {
            $stream = $disk->readStream($attachment->file_path);

            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $fileName);
    }

    /**
     * فحص تكرار رقم البوليصة الرئيسية أو الفرعية أثناء إدخال بيانات الكتاب.
     * لا يمنع التكرار من قاعدة البيانات؛ فقط يعيد نتيجة واضحة للواجهة لتطلب موافقة المستخدم.
     */
        /**
     * فحص تكرار رقم البوليصة الرئيسية/الفرعية أثناء الإدخال.
     * ملاحظة: لا يمنع التكرار في قاعدة البيانات، بل يعطي الواجهة قرار نعم/لا للمستخدم.
     */
        /**
     * فحص تكرار رقم البوليصة الرئيسية/الفرعية أثناء الإدخال.
     * لا يمنع التكرار من قاعدة البيانات؛ الواجهة تسأل المستخدم: نعم/لا.
     */
        /**
     * فحص تكرار رقم البوليصة الرئيسية/الفرعية أثناء الإدخال.
     * لا يمنع التكرار من قاعدة البيانات؛ الواجهة تسأل المستخدم: نعم/لا.
     */
    public function checkPolicyDuplicate(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'field' => ['nullable', 'in:main_policy_number,sub_policy_number'],
            'value' => ['required', 'string', 'max:255'],
            'document_id' => ['nullable', 'integer'],
        ]);

        $field = $validated['field'] ?? null;
        $value = $this->normalizeDocumentNumber($validated['value']) ?? '';

        if ($value === '') {
            return response()->json(['exists' => false, 'message' => null, 'document' => null]);
        }

        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('documents')) {
                return response()->json(['exists' => false, 'message' => null, 'document' => null]);
            }

            $hasMain = \Illuminate\Support\Facades\Schema::hasColumn('documents', 'main_policy_number');
            $hasSub = \Illuminate\Support\Facades\Schema::hasColumn('documents', 'sub_policy_number');

            if (!$hasMain && !$hasSub) {
                return response()->json(['exists' => false, 'message' => null, 'document' => null]);
            }

            $query = \App\Models\Document::query();

            if (!empty($validated['document_id'])) {
                $query->where('id', '<>', (int) $validated['document_id']);
            }

            $query->where(function ($q) use ($value, $hasMain, $hasSub) {
                if ($hasMain) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(TRIM(COALESCE(main_policy_number, '')), ' ', ''), CHAR(9), ''), CHAR(10), '') = ?", [$value]);
                }
                if ($hasSub) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(TRIM(COALESCE(sub_policy_number, '')), ' ', ''), CHAR(9), ''), CHAR(10), '') = ?", [$value]);
                }
            });

            $document = $query->orderByDesc('id')->first();

            if (!$document) {
                return response()->json(['exists' => false, 'message' => null, 'document' => null]);
            }

            $referenceDate = null;
            if (!empty($document->reference_date)) {
                try {
                    $referenceDate = \Illuminate\Support\Carbon::parse($document->reference_date)->format('d/m/Y');
                } catch (\Throwable $dateException) {
                    $referenceDate = (string) $document->reference_date;
                }
            }

            $matchedField = null;
            if ($hasMain && $this->normalizeDocumentNumber((string) ($document->main_policy_number ?? '')) === $value) {
                $matchedField = 'main_policy_number';
            } elseif ($hasSub && $this->normalizeDocumentNumber((string) ($document->sub_policy_number ?? '')) === $value) {
                $matchedField = 'sub_policy_number';
            }

            $inputLabel = $field === 'sub_policy_number' ? 'البوليصة الفرعية' : 'البوليصة الرئيسية';
            $matchedLabel = $matchedField === 'sub_policy_number' ? 'البوليصة الفرعية' : 'البوليصة الرئيسية';

            return response()->json([
                'exists' => true,
                'message' => "رقم {$inputLabel} موجود مسبقاً في {$matchedLabel}.",
                'matched_field' => $matchedField,
                'document' => [
                    'id' => $document->id,
                    'reference_number' => $document->reference_number ?? null,
                    'reference_date' => $referenceDate,
                    'title' => $document->title ?? null,
                    'subject' => $document->subject ?? null,
                    'main_policy_number' => $document->main_policy_number ?? null,
                    'sub_policy_number' => $document->sub_policy_number ?? null,
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'exists' => false,
                'message' => 'تعذر فحص تكرار البوليصة حالياً.',
                'document' => null,
            ], 200);
        }
    }

    private function authorizeAttachmentPreview(DocumentAttachment $attachment): void
    {
        $user = Auth::user();

        $allowed = $user && (
            (method_exists($user, 'hasPermission') && $user->hasPermission('documents.view')) ||
            (method_exists($user, 'hasPermission') && $user->hasPermission('attachments.preview'))
        );

        abort_unless($allowed, 403, 'غير مصرح لك بمعاينة هذا المرفق.');
    }

    private function authorizeAttachmentDownload(DocumentAttachment $attachment): void
    {
        $user = Auth::user();

        $allowed = $user && method_exists($user, 'hasPermission') && $user->hasPermission('attachments.download');

        abort_unless($allowed, 403, 'غير مصرح لك بتنزيل هذا المرفق.');
    }

    private function ensureDocumentCanBeModified(Document $document): void
    {
        if (method_exists($document, 'canBeModifiedBy') && ! $document->canBeModifiedBy(Auth::user())) {
            abort(403, 'هذا الكتاب مؤرشف نهائيًا ولا يمكن تعديله أو حذفه إلا بصلاحية عليا.');
        }
    }

    private function storeAttachment(Request $request, Document $document): void
    {
        $file = $request->file('attachment');

        $extension = strtolower($file->getClientOriginalExtension());
        $safeName = $document->reference_number . '_' . Str::random(12) . '.' . $extension;
        $folder = 'documents/' . $document->reference_year . '/' . $document->reference_number;
        $path = $file->storeAs($folder, $safeName, 'local');

        $latestVersion = DocumentAttachment::query()
            ->where('document_id', $document->id)
            ->max('version_no');

        $attachment = DocumentAttachment::create([
            'document_id' => $document->id,
            'attachment_type' => 'main',
            'version_no' => ((int) $latestVersion) + 1,
            'is_main' => true,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => $safeName,
            'file_path' => $path,
            'disk' => 'local',
            'extension' => $extension,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'ocr_status' => 'pending',
            'uploaded_by' => Auth::id(),
        ]);

        ActivityLogger::log(
            'attachment.uploaded',
            'تم رفع مرفق للكتاب رقم ' . $document->reference_number,
            $attachment,
            [
                'document_id' => $document->id,
                'reference_number' => $document->reference_number,
                'original_name' => $attachment->original_name,
            ]
        );
    }

    private function resolveBookSubjectData(array $validated): array
    {
        $subjectId = $validated['book_subject_id'] ?? null;
        $subjectText = trim((string) ($validated['subject'] ?? ''));

        if ($subjectId) {
            $bookSubject = BookSubject::query()->find($subjectId);

            if ($bookSubject && $subjectText === '') {
                $subjectText = $bookSubject->name;
            }
        }

        $validated['book_subject_id'] = $subjectId ?: null;
        $validated['subject'] = $subjectText !== '' ? $subjectText : null;

        return $validated;
    }

    private function normalizeDocumentNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Policy/reference numbers are identifiers; accidental spaces should not create false duplicates.
        $value = preg_replace('/\s+/u', '', $value) ?: $value;

        return $value === '' ? null : $value;
    }
    private function buildSearchText(array $data): string
    {
        return trim(
            ($data['title'] ?? '') . ' ' .
            ($data['subject'] ?? '') . ' ' .
            ($data['description'] ?? '') . ' ' .
            ($data['sender'] ?? '') . ' ' .
            ($data['receiver'] ?? '') . ' ' .
            ($data['main_policy_number'] ?? '') . ' ' .
            ($data['sub_policy_number'] ?? '')
        );
    }
}

<?php

namespace App\Http\Controllers;

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
        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $documentTypes = DocumentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $hasColumn = function (string $column): bool {
            try {
                return \Illuminate\Support\Facades\Schema::hasColumn('documents', $column);
            } catch (\Throwable $e) {
                return false;
            }
        };

        $baseQuery = Document::query()
            ->with(['department', 'documentType', 'mainAttachment']);

        try {
            $baseQuery->withCount('attachments');
        } catch (\Throwable $e) {
            // في حال كان المشروع يحتوي نسخة قديمة من العلاقات، لا نعطل صفحة الكتب.
        }

        $applyFilters = function ($query) use ($request, $hasColumn) {
            if ($request->filled('q')) {
                $q = trim((string) $request->q);
                $query->where(function ($subQuery) use ($q, $hasColumn) {
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
                            $subQuery->orWhere($column, 'like', "%{$q}%");
                        }
                    }
                });
            }

            if ($request->filled('department_id') && $hasColumn('department_id')) {
                $query->where('department_id', $request->department_id);
            }

            if ($request->filled('document_type_id') && $hasColumn('document_type_id')) {
                $query->where('document_type_id', $request->document_type_id);
            }

            if ($request->filled('status') && $hasColumn('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('priority') && $hasColumn('priority')) {
                $query->where('priority', $request->priority);
            }

            if ($request->filled('confidentiality') && $hasColumn('confidentiality')) {
                $query->where('confidentiality', $request->confidentiality);
            }

            if ($request->filled('date_from') && $hasColumn('reference_date')) {
                $query->whereDate('reference_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to') && $hasColumn('reference_date')) {
                $query->whereDate('reference_date', '<=', $request->date_to);
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

        $allowedSorts = [
            'created_at' => 'created_at',
            'reference_date' => 'reference_date',
            'reference_number' => 'reference_number',
            'title' => 'title',
        ];

        $sort = $allowedSorts[$request->get('sort', 'created_at')] ?? 'created_at';
        if (!$hasColumn($sort)) {
            $sort = $hasColumn('created_at') ? 'created_at' : 'id';
        }

        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        $documents = $documentsQuery
            ->orderBy($sort, $direction)
            ->paginate((int) $request->get('per_page', 15) ?: 15)
            ->withQueryString();

        $filteredForSummary = $applyFilters(Document::query());

        $summary = [
            'total' => (int) Document::query()->count(),
            'filtered' => (int) (clone $filteredForSummary)->count(),
            'with_attachments' => 0,
            'without_attachments' => 0,
        ];

        try {
            $summary['with_attachments'] = (int) Document::query()->whereHas('attachments')->count();
            $summary['without_attachments'] = max(0, $summary['total'] - $summary['with_attachments']);
        } catch (\Throwable $e) {
            $summary['with_attachments'] = 0;
            $summary['without_attachments'] = 0;
        }

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

        return view('documents.index', compact(
            'documents',
            'departments',
            'documentTypes',
            'summary',
            'statusOptions',
            'priorityOptions',
            'confidentialityOptions'
        ));
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

        return view('documents.create', compact('departments', 'documentTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reference_date' => ['required', 'date'],
            'main_policy_number' => ['nullable', 'string', 'max:255'],
            'sub_policy_number' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string'],
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
                'description' => $validated['description'] ?? null,
                'sender' => $validated['sender'] ?? null,
                'receiver' => $validated['receiver'] ?? null,

                'department_id' => $validated['department_id'] ?? null,
                'document_type_id' => $validated['document_type_id'] ?? null,
                'created_by' => Auth::id(),

                'status' => 'active',
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
        $document->load(['department', 'documentType', 'attachments']);

        return view('documents.show', compact('document'));
    }

    public function edit(Document $document)
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $documentTypes = DocumentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $document->load(['department', 'documentType', 'attachments']);

        return view('documents.edit', compact('document', 'departments', 'documentTypes'));
    }

    public function update(Request $request, Document $document)
    {
        $validated = $request->validate([
            'reference_date' => ['required', 'date'],
            'main_policy_number' => ['nullable', 'string', 'max:255'],
            'sub_policy_number' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string'],
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

        DB::transaction(function () use ($request, $document, $validated) {
            $document->update([
                'reference_date' => $validated['reference_date'],
                'main_policy_number' => $validated['main_policy_number'] ?? null,
                'sub_policy_number' => $validated['sub_policy_number'] ?? null,
                'title' => $validated['title'],
                'subject' => $validated['subject'] ?? null,
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
        $value = trim((string) $validated['value']);

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
                    $q->orWhereRaw("TRIM(COALESCE(main_policy_number, '')) = ?", [$value]);
                }
                if ($hasSub) {
                    $q->orWhereRaw("TRIM(COALESCE(sub_policy_number, '')) = ?", [$value]);
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
            if ($hasMain && trim((string) ($document->main_policy_number ?? '')) === $value) {
                $matchedField = 'main_policy_number';
            } elseif ($hasSub && trim((string) ($document->sub_policy_number ?? '')) === $value) {
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

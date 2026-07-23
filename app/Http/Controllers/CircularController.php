<?php

namespace App\Http\Controllers;

use App\Models\ArchiveCategory;
use App\Models\Circular;
use App\Models\CircularAttachment;
use App\Services\ActivityLogger;
use App\Services\CircularNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CircularController extends Controller
{
    public function index(Request $request)
    {
        return $this->listPage($request, false);
    }

    public function trash(Request $request)
    {
        return $this->listPage($request, true);
    }

    public function create()
    {
        $categories = $this->categories();
        $nextNumber = CircularNumberGenerator::preview();

        return view('circulars.create', compact('categories', 'nextNumber'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), $this->messages());

        $circular = DB::transaction(function () use ($request, $validated) {
            $date = \Carbon\Carbon::parse($validated['circular_date']);
            $number = CircularNumberGenerator::generate($date);

            $circular = Circular::create($this->recordData(
                $validated,
                $number,
                true
            ));

            $this->storeAttachments($request, $circular);

            return $circular;
        });

        ActivityLogger::log(
            'circular.created',
            'تم إنشاء التعميم رقم ' . $circular->circular_number,
            $circular,
            ['number' => $circular->circular_number]
        );

        return redirect()
            ->route('circulars.show', $circular)
            ->with('success', 'تم حفظ التعميم بنجاح.');
    }

    public function show(Circular $circular)
    {
        $circular->load([
            'category.parent',
            'creator',
            'attachments.uploader',
        ]);

        return view('circulars.show', [
            'circular' => $circular,
        ]);
    }

    public function edit(Circular $circular)
    {
        $this->ensureCanModify($circular);
        $circular->load(['category.parent', 'attachments']);
        $categories = $this->categories();

        return view('circulars.edit', [
            'circular' => $circular,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Circular $circular)
    {
        $this->ensureCanModify($circular);
        $validated = $request->validate($this->rules(), $this->messages());

        DB::transaction(function () use ($request, $circular, $validated) {
            $circular->update($this->recordData($validated, null, false));
            $this->storeAttachments($request, $circular);
        });

        ActivityLogger::log(
            'circular.updated',
            'تم تعديل التعميم رقم ' . $circular->circular_number,
            $circular,
            ['number' => $circular->circular_number]
        );

        return redirect()
            ->route('circulars.show', $circular)
            ->with('success', 'تم تحديث التعميم بنجاح.');
    }

    public function destroy(Circular $circular)
    {
        $this->ensureCanModify($circular);

        ActivityLogger::log(
            'circular.deleted',
            'تم حذف التعميم رقم ' . $circular->circular_number,
            $circular,
            ['number' => $circular->circular_number]
        );

        $circular->delete();

        return redirect()
            ->route('circulars.index')
            ->with('success', 'تم نقل التعميم إلى السلة.');
    }

    public function restore(Circular $circular)
    {
        abort_unless($circular->trashed(), 404);
        $circular->restore();

        ActivityLogger::log(
            'circular.restored',
            'تمت استعادة التعميم رقم ' . $circular->circular_number,
            $circular,
            ['number' => $circular->circular_number]
        );

        return redirect()
            ->route('circulars.trash')
            ->with('success', 'تمت استعادة التعميم بنجاح.');
    }

    public function forceDelete(Circular $circular)
    {
        abort_unless($circular->trashed(), 404);
        $circular->load('attachmentsWithTrashed');

        foreach ($circular->attachmentsWithTrashed as $attachment) {
            $disk = Storage::disk($attachment->disk ?: 'local');

            if ($attachment->file_path && $disk->exists($attachment->file_path)) {
                $disk->delete($attachment->file_path);
            }
        }

        ActivityLogger::log(
            'circular.force_deleted',
            'تم حذف التعميم نهائيًا رقم ' . $circular->circular_number,
            $circular,
            ['number' => $circular->circular_number]
        );

        $circular->forceDelete();

        return redirect()
            ->route('circulars.trash')
            ->with('success', 'تم حذف التعميم نهائيًا.');
    }

    public function previewAttachment(
        Circular $circular,
        CircularAttachment $attachment
    ) {
        $this->ensureAttachmentBelongs($circular, $attachment);

        ActivityLogger::log(
            'circular_attachment.previewed',
            'تمت معاينة المرفق: '
                . ($attachment->original_name ?: $attachment->file_name),
            $attachment,
            [
                'record_id' => $circular->id,
                'number' => $circular->circular_number,
            ]
        );

        return view('archive-modules.attachment-preview', [
            'pageTitle' => 'معاينة مرفق التعميم',
            'recordNumber' => $circular->circular_number,
            'recordUrl' => route('circulars.show', $circular),
            'inlineUrl' => route(
                'circulars.attachments.inline',
                [$circular, $attachment]
            ),
            'downloadUrl' => route(
                'circulars.attachments.download',
                [$circular, $attachment]
            ),
            'attachment' => $attachment,
        ]);
    }

    public function inlineAttachment(
        Circular $circular,
        CircularAttachment $attachment
    ) {
        $this->ensureAttachmentBelongs($circular, $attachment);

        return $this->inlineResponse($attachment);
    }

    public function downloadAttachment(
        Circular $circular,
        CircularAttachment $attachment
    ) {
        $this->ensureAttachmentBelongs($circular, $attachment);

        ActivityLogger::log(
            'circular_attachment.downloaded',
            'تم تنزيل المرفق: '
                . ($attachment->original_name ?: $attachment->file_name),
            $attachment,
            [
                'record_id' => $circular->id,
                'number' => $circular->circular_number,
            ]
        );

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (! $disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود على وسيط التخزين.');
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

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $fileName);
    }

    private function listPage(Request $request, bool $trash)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'category_id' => $request->input('category_id'),
            'status' => (string) $request->input('status', 'all'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'issuing_entity' => (string) $request->input('issuing_entity', 'all'),
        ];

        $query = $trash
            ? Circular::onlyTrashed()
            : Circular::query();

        $query->with(['category.parent', 'mainAttachment'])
            ->withCount('attachments');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('circular_number', 'like', '%' . $q . '%')
                    ->orWhere('original_number', 'like', '%' . $q . '%')
                    ->orWhere('subject', 'like', '%' . $q . '%')
                    
                    ->orWhere('issuing_entity', 'like', '%' . $q . '%')
                    ->orWhere('scope', 'like', '%' . $q . '%')
                    ->orWhere('keywords', 'like', '%' . $q . '%')
                    ->orWhere('notes', 'like', '%' . $q . '%')
                    ->orWhere('search_text', 'like', '%' . $q . '%')
                    ->orWhereHas('category', function ($categoryQuery) use ($q) {
                        $categoryQuery->where('name', 'like', '%' . $q . '%');
                    });
            });
        }

        if ($filters['category_id']) {
            $query->where('category_id', $filters['category_id']);
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['date_from']) {
            $query->whereDate('circular_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('circular_date', '<=', $filters['date_to']);
        }

        if ($filters['issuing_entity'] !== 'all'
            && $filters['issuing_entity'] !== '') {
            $query->where(
                'issuing_entity',
                'like',
                '%' . $filters['issuing_entity'] . '%'
            );
        }

        $records = $query
            ->orderByDesc('circular_year')
            ->orderByDesc('circular_sequence')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => Circular::query()->count(),
            'active' => Circular::query()->where('status', 'active')->count(),
            'archived' => Circular::query()->where('status', 'archived')->count(),
            'with_attachments' => Circular::query()->whereHas('attachments')->count(),
            'trashed' => Circular::onlyTrashed()->count(),
        ];

        return view('circulars.index', [
            'circulars' => $records,
            'categories' => $this->categories(),
            'filters' => $filters,
            'stats' => $stats,
            'isTrash' => $trash,
        ]);
    }

    private function rules(): array
    {
        return [
            'circular_date' => ['required', 'date'],
            'original_number' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:5000'],
            'category_id' => [
                'nullable',
                Rule::exists('archive_categories', 'id')
                    ->where('module', 'circular'),
            ],
            'issuing_entity' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', 'string', 'max:255'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => [
                'nullable',
                'date',
                'after_or_equal:effective_date',
            ],
            'status' => ['required', Rule::in(['active', 'expired', 'cancelled', 'archived'])],
            'confidentiality' => [
                'required',
                Rule::in(['normal', 'confidential', 'secret', 'top_secret']),
            ],
            'priority' => [
                'required',
                Rule::in(['normal', 'urgent', 'very_urgent']),
            ],
            'keywords' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:20480'],
        ];
    }

    private function messages(): array
    {
        return [
            'circular_date.required' => 'تاريخ التعميم مطلوب.',
            'subject.required' => 'موضوع التعميم مطلوب.',
            'attachments.*.max' => 'حجم كل مرفق يجب ألا يتجاوز 20 MB.',
        ];
    }

    private function recordData(
        array $validated,
        ?array $number,
        bool $creating
    ): array {
        $data = [
            'circular_date' => $validated['circular_date'],
            'original_number' => $this->nullableText(
                $validated['original_number'] ?? null
            ),
            'subject' => $this->text($validated['subject']),
            'category_id' => $validated['category_id'] ?? null,
            'issuing_entity' => $this->nullableText(
                $validated['issuing_entity'] ?? null
            ),
            'scope' => $this->nullableText($validated['scope'] ?? null),
            'effective_date' => $validated['effective_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'confidentiality' => $validated['confidentiality'] ?? 'normal',
            'priority' => $validated['priority'] ?? 'normal',
            'keywords' => $this->nullableText($validated['keywords'] ?? null),
            'notes' => $this->nullableText($validated['notes'] ?? null),
            'search_text' => $this->buildSearchText($validated),
        ];

        if ($creating && $number) {
            $data['circular_number'] = $number['circular_number'];
            $data['circular_sequence'] = $number['circular_sequence'];
            $data['circular_year'] = $number['circular_year'];
            $data['created_by'] = Auth::id();
            $data['workflow_status'] = 'draft';
        }

        return $data;
    }

    private function storeAttachments(Request $request, Circular $circular): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $files = $request->file('attachments');

        if (! is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $extension = strtolower(
                $file->getClientOriginalExtension() ?: 'bin'
            );
            $safeName = $circular->circular_number
                . '_'
                . Str::random(12)
                . '.'
                . $extension;
            $folder = 'circulars/'
                . $circular->circular_year
                . '/'
                . $circular->circular_number;
            $temporaryPath = $file->getRealPath();
            $sha256 = is_string($temporaryPath)
                && $temporaryPath !== ''
                && is_file($temporaryPath)
                    ? hash_file('sha256', $temporaryPath)
                    : null;
            $mimeType = $file->getMimeType();
            $fileSize = (int) $file->getSize();

            $path = $file->storeAs($folder, $safeName, 'local');

            if (! is_string($path) || $path === '') {
                throw new \RuntimeException('تعذر حفظ المرفق على وسيط التخزين.');
            }

            $latestVersion = CircularAttachment::query()
                ->where('circular_id', $circular->id)
                ->max('version_no');
            $isFirst = ! CircularAttachment::query()
                ->where('circular_id', $circular->id)
                ->exists();

CircularAttachment::create([
                'circular_id' => $circular->id,
                'version_no' => ((int) $latestVersion) + 1,
                'is_main' => $isFirst,
                'original_name' => $file->getClientOriginalName(),
                'file_name' => $safeName,
                'file_path' => $path,
                'disk' => 'local',
                'extension' => $extension,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'sha256' => $sha256,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function inlineResponse(CircularAttachment $attachment)
    {
        $disk = Storage::disk($attachment->disk ?: 'local');

        if (! $disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود على وسيط التخزين.');
        }

        $extension = strtolower(
            $attachment->extension
                ?: pathinfo(
                    $attachment->original_name ?: $attachment->file_name,
                    PATHINFO_EXTENSION
                )
        );

        $mimeType = match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            default => $attachment->mime_type ?: 'application/octet-stream',
        };

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="archive-file.'
                . ($extension ?: 'bin')
                . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (method_exists($disk, 'path')) {
            return response()->file(
                $disk->path($attachment->file_path),
                $headers
            );
        }

        return response()->stream(function () use ($disk, $attachment) {
            $stream = $disk->readStream($attachment->file_path);

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }

    private function ensureAttachmentBelongs(
        Circular $circular,
        CircularAttachment $attachment
    ): void {
        abort_unless(
            (int) $attachment->circular_id === (int) $circular->id,
            404
        );
    }

    private function ensureCanModify(Circular $circular): void
    {
        if (method_exists($circular, 'canBeModifiedBy')
            && ! $circular->canBeModifiedBy(Auth::user())) {
            abort(
                403,
                'السجل مؤرشف نهائيًا ولا يمكن تعديله أو حذفه إلا بصلاحية عليا.'
            );
        }
    }

    private function categories()
    {
        return ArchiveCategory::query()
            ->forModule('circular')
            ->active()
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function buildSearchText(array $data): string
    {
        return trim(implode(' ', array_filter([
            $data['original_number'] ?? null,
            $data['subject'] ?? null,
            $data['issuing_entity'] ?? null,
            $data['scope'] ?? null,
            $data['keywords'] ?? null,
            $data['notes'] ?? null,
        ])));
    }

    private function text(?string $value): string
    {
        return trim(
            preg_replace('/\s+/u', ' ', (string) $value)
                ?: (string) $value
        );
    }

    private function nullableText(?string $value): ?string
    {
        $value = $this->text($value);

        return $value === '' ? null : $value;
    }
}

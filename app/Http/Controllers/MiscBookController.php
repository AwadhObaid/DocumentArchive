<?php

namespace App\Http\Controllers;

use App\Support\AttachmentUploadLimits;
use App\Models\ArchiveCategory;
use App\Models\MiscBook;
use App\Models\MiscBookAttachment;
use App\Services\ActivityLogger;
use App\Services\AttachmentIndexInventoryService;
use App\Services\MiscBookNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MiscBookController extends Controller
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
        $nextNumber = MiscBookNumberGenerator::preview();

        return view('misc-books.create', compact('categories', 'nextNumber'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), $this->messages());

        $miscBook = DB::transaction(function () use ($request, $validated) {
            $date = \Carbon\Carbon::parse($validated['book_date']);
            $number = MiscBookNumberGenerator::generate($date);

            $miscBook = MiscBook::create($this->recordData(
                $validated,
                $number,
                true
            ));

            $this->storeAttachments($request, $miscBook);

            return $miscBook;
        });

        ActivityLogger::log(
            'misc_book.created',
            'تم إنشاء الكتاب المتفرق رقم ' . $miscBook->misc_number,
            $miscBook,
            ['number' => $miscBook->misc_number]
        );

        return redirect()
            ->route('misc-books.show', $miscBook)
            ->with('success', 'تم حفظ الكتاب المتفرق بنجاح.');
    }

    public function show(MiscBook $miscBook)
    {
        $miscBook->load([
            'category.parent',
            'creator',
            'attachments.uploader',
        ]);

        return view('misc-books.show', [
            'miscBook' => $miscBook,
        ]);
    }

    public function edit(MiscBook $miscBook)
    {
        $this->ensureCanModify($miscBook);
        $miscBook->load(['category.parent', 'attachments']);
        $categories = $this->categories();

        return view('misc-books.edit', [
            'miscBook' => $miscBook,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, MiscBook $miscBook)
    {
        $this->ensureCanModify($miscBook);
        $validated = $request->validate($this->rules(), $this->messages());

        DB::transaction(function () use ($request, $miscBook, $validated) {
            $miscBook->update($this->recordData($validated, null, false));
            $this->storeAttachments($request, $miscBook);
        });

        ActivityLogger::log(
            'misc_book.updated',
            'تم تعديل الكتاب المتفرق رقم ' . $miscBook->misc_number,
            $miscBook,
            ['number' => $miscBook->misc_number]
        );

        return redirect()
            ->route('misc-books.show', $miscBook)
            ->with('success', 'تم تحديث الكتاب المتفرق بنجاح.');
    }

    public function destroy(MiscBook $miscBook)
    {
        $this->ensureCanModify($miscBook);

        ActivityLogger::log(
            'misc_book.deleted',
            'تم حذف الكتاب المتفرق رقم ' . $miscBook->misc_number,
            $miscBook,
            ['number' => $miscBook->misc_number]
        );

        $miscBook->delete();

        return redirect()
            ->route('misc-books.index')
            ->with('success', 'تم نقل الكتاب المتفرق إلى السلة.');
    }

    public function restore(MiscBook $miscBook)
    {
        abort_unless($miscBook->trashed(), 404);
        $miscBook->restore();

        ActivityLogger::log(
            'misc_book.restored',
            'تمت استعادة الكتاب المتفرق رقم ' . $miscBook->misc_number,
            $miscBook,
            ['number' => $miscBook->misc_number]
        );

        return redirect()
            ->route('misc-books.trash')
            ->with('success', 'تمت استعادة الكتاب المتفرق بنجاح.');
    }

    public function forceDelete(MiscBook $miscBook)
    {
        abort_unless($miscBook->trashed(), 404);
        $miscBook->load('attachmentsWithTrashed');

        foreach ($miscBook->attachmentsWithTrashed as $attachment) {
            $disk = Storage::disk($attachment->disk ?: 'local');

            if ($attachment->file_path && $disk->exists($attachment->file_path)) {
                $disk->delete($attachment->file_path);
            }
        }

        ActivityLogger::log(
            'misc_book.force_deleted',
            'تم حذف الكتاب المتفرق نهائيًا رقم ' . $miscBook->misc_number,
            $miscBook,
            ['number' => $miscBook->misc_number]
        );

        $miscBook->forceDelete();

        return redirect()
            ->route('misc-books.trash')
            ->with('success', 'تم حذف الكتاب المتفرق نهائيًا.');
    }

    public function previewAttachment(
        MiscBook $miscBook,
        MiscBookAttachment $attachment
    ) {
        $this->ensureAttachmentBelongs($miscBook, $attachment);

        ActivityLogger::log(
            'misc_book_attachment.previewed',
            'تمت معاينة المرفق: '
                . ($attachment->original_name ?: $attachment->file_name),
            $attachment,
            [
                'record_id' => $miscBook->id,
                'number' => $miscBook->misc_number,
            ]
        );

        return view('archive-modules.attachment-preview', [
            'pageTitle' => 'معاينة مرفق الكتاب المتفرق',
            'recordNumber' => $miscBook->misc_number,
            'recordUrl' => route('misc-books.show', $miscBook),
            'inlineUrl' => route(
                'misc-books.attachments.inline',
                [$miscBook, $attachment]
            ),
            'downloadUrl' => route(
                'misc-books.attachments.download',
                [$miscBook, $attachment]
            ),
            'attachment' => $attachment,
        ]);
    }

    public function inlineAttachment(
        MiscBook $miscBook,
        MiscBookAttachment $attachment
    ) {
        $this->ensureAttachmentBelongs($miscBook, $attachment);

        return $this->inlineResponse($attachment);
    }

    public function downloadAttachment(
        MiscBook $miscBook,
        MiscBookAttachment $attachment
    ) {
        $this->ensureAttachmentBelongs($miscBook, $attachment);

        ActivityLogger::log(
            'misc_book_attachment.downloaded',
            'تم تنزيل المرفق: '
                . ($attachment->original_name ?: $attachment->file_name),
            $attachment,
            [
                'record_id' => $miscBook->id,
                'number' => $miscBook->misc_number,
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
            'nature' => (string) $request->input('nature', 'all'),
        ];

        $query = $trash
            ? MiscBook::onlyTrashed()
            : MiscBook::query();

        $query->with(['category.parent', 'mainAttachment'])
            ->withCount('attachments');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('misc_number', 'like', '%' . $q . '%')
                    ->orWhere('original_number', 'like', '%' . $q . '%')
                    ->orWhere('subject', 'like', '%' . $q . '%')
                    
                    ->orWhere('sender', 'like', '%' . $q . '%')
                    ->orWhere('receiver', 'like', '%' . $q . '%')
                    ->orWhere('employee_name', 'like', '%' . $q . '%')
                    ->orWhere('authority_name', 'like', '%' . $q . '%')
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
            $query->whereDate('book_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('book_date', '<=', $filters['date_to']);
        }

        if ($filters['nature'] !== 'all') {
            $query->where('nature', $filters['nature']);
        }

        $records = $query
            ->orderByDesc('misc_year')
            ->orderByDesc('misc_sequence')
            ->paginate(15)
            ->withQueryString();

        app(AttachmentIndexInventoryService::class)->decoratePaginator($records, 'misc_book');

        $stats = [
            'total' => MiscBook::query()->count(),
            'active' => MiscBook::query()->where('status', 'active')->count(),
            'archived' => MiscBook::query()->where('status', 'archived')->count(),
            'with_attachments' => MiscBook::query()->whereHas('attachments')->count(),
            'trashed' => MiscBook::onlyTrashed()->count(),
        ];

        return view('misc-books.index', [
            'miscBooks' => $records,
            'categories' => $this->categories(),
            'filters' => $filters,
            'stats' => $stats,
            'isTrash' => $trash,
        ]);
    }

    private function rules(): array
    {
        return [
            'book_date' => ['required', 'date'],
            'original_number' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:5000'],
            'category_id' => [
                'nullable',
                Rule::exists('archive_categories', 'id')
                    ->where('module', 'misc_book'),
            ],
            'correspondence_direction' => [
                'required',
                Rule::in(['incoming', 'outgoing', 'internal']),
            ],
            'nature' => [
                'required',
                Rule::in(['military', 'civil', 'unspecified']),
            ],
            'sender' => ['nullable', 'string', 'max:255'],
            'receiver' => ['nullable', 'string', 'max:255'],
            'employee_name' => ['nullable', 'string', 'max:255'],
            'authority_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'archived', 'cancelled'])],
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
            'attachments.*' => ['file', 'max:' . AttachmentUploadLimits::MAX_KB],
        ];
    }

    private function messages(): array
    {
        return [
            'book_date.required' => 'تاريخ الكتاب مطلوب.',
            'subject.required' => 'موضوع الكتاب المتفرق مطلوب.',
            'attachments.*.max' => 'حجم كل مرفق يجب ألا يتجاوز ' . AttachmentUploadLimits::MAX_MB . ' MB.',
        ];
    }

    private function recordData(
        array $validated,
        ?array $number,
        bool $creating
    ): array {
        $data = [
            'book_date' => $validated['book_date'],
            'original_number' => $this->nullableText(
                $validated['original_number'] ?? null
            ),
            'subject' => $this->text($validated['subject']),
            'category_id' => $validated['category_id'] ?? null,
            'correspondence_direction' =>
                $validated['correspondence_direction'] ?? 'incoming',
            'nature' => $validated['nature'] ?? 'unspecified',
            'sender' => $this->nullableText($validated['sender'] ?? null),
            'receiver' => $this->nullableText($validated['receiver'] ?? null),
            'employee_name' => $this->nullableText(
                $validated['employee_name'] ?? null
            ),
            'authority_name' => $this->nullableText(
                $validated['authority_name'] ?? null
            ),
            'status' => $validated['status'] ?? 'active',
            'confidentiality' => $validated['confidentiality'] ?? 'normal',
            'priority' => $validated['priority'] ?? 'normal',
            'keywords' => $this->nullableText($validated['keywords'] ?? null),
            'notes' => $this->nullableText($validated['notes'] ?? null),
            'search_text' => $this->buildSearchText($validated),
        ];

        if ($creating && $number) {
            $data['misc_number'] = $number['misc_number'];
            $data['misc_sequence'] = $number['misc_sequence'];
            $data['misc_year'] = $number['misc_year'];
            $data['created_by'] = Auth::id();
            $data['workflow_status'] = 'draft';
        }

        return $data;
    }

    private function storeAttachments(Request $request, MiscBook $miscBook): void
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
            $safeName = $miscBook->misc_number
                . '_'
                . Str::random(12)
                . '.'
                . $extension;
            $folder = 'misc-books/'
                . $miscBook->misc_year
                . '/'
                . $miscBook->misc_number;
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

            $latestVersion = MiscBookAttachment::query()
                ->where('misc_book_id', $miscBook->id)
                ->max('version_no');
            $isFirst = ! MiscBookAttachment::query()
                ->where('misc_book_id', $miscBook->id)
                ->exists();

MiscBookAttachment::create([
                'misc_book_id' => $miscBook->id,
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

    private function inlineResponse(MiscBookAttachment $attachment)
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
        MiscBook $miscBook,
        MiscBookAttachment $attachment
    ): void {
        abort_unless(
            (int) $attachment->misc_book_id === (int) $miscBook->id,
            404
        );
    }

    private function ensureCanModify(MiscBook $miscBook): void
    {
        if (method_exists($miscBook, 'canBeModifiedBy')
            && ! $miscBook->canBeModifiedBy(Auth::user())) {
            abort(
                403,
                'السجل مؤرشف نهائيًا ولا يمكن تعديله أو حذفه إلا بصلاحية عليا.'
            );
        }
    }

    private function categories()
    {
        return ArchiveCategory::query()
            ->forModule('misc_book')
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
            $data['sender'] ?? null,
            $data['receiver'] ?? null,
            $data['employee_name'] ?? null,
            $data['authority_name'] ?? null,
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

<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Memo;
use App\Models\MemoAttachment;
use App\Services\ActivityLogger;
use App\Services\MemoNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MemoController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'department_id' => $request->input('department_id'),
            'status' => (string) $request->input('status', 'all'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'sort' => (string) $request->input('sort', 'latest'),
        ];

        if (! in_array($filters['status'], ['all', 'active', 'archived', 'cancelled'], true)) {
            $filters['status'] = 'all';
        }

        if (! in_array($filters['sort'], ['latest', 'oldest', 'number', 'date'], true)) {
            $filters['sort'] = 'latest';
        }

        $departments = Department::query()->where('is_active', true)->orderBy('name')->get();

        $query = Memo::query()->with(['department', 'mainAttachment'])->withCount('attachments');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($builder) use ($q) {
                $builder->where('memo_number', 'like', '%' . $q . '%')
                    ->orWhere('subject', 'like', '%' . $q . '%')
                    ->orWhere('sender', 'like', '%' . $q . '%')
                    ->orWhere('receiver', 'like', '%' . $q . '%')
                    ->orWhere('description', 'like', '%' . $q . '%')
                    ->orWhere('notes', 'like', '%' . $q . '%')
                    ->orWhere('search_text', 'like', '%' . $q . '%')
                    ->orWhereHas('department', function ($departmentQuery) use ($q) {
                        $departmentQuery->where('name', 'like', '%' . $q . '%');
                    });
            });
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('memo_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('memo_date', '<=', $filters['date_to']);
        }

        match ($filters['sort']) {
            'oldest' => $query->orderBy('id'),
            'number' => $query->orderByDesc('memo_sequence')->orderByDesc('id'),
            'date' => $query->orderByDesc('memo_date')->orderByDesc('id'),
            default => $query->latest('id'),
        };

        $memos = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => Memo::query()->count(),
            'active' => Memo::query()->where('status', 'active')->count(),
            'archived' => Memo::query()->where('status', 'archived')->count(),
            'with_attachments' => Memo::query()->whereHas('attachments')->count(),
        ];

        return view('memos.index', compact('memos', 'departments', 'filters', 'stats'));
    }

    public function create()
    {
        $departments = Department::query()->where('is_active', true)->orderBy('name')->get();
        $nextMemo = MemoNumberGenerator::preview();

        return view('memos.create', compact('departments', 'nextMemo'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), $this->messages());

        $memo = DB::transaction(function () use ($request, $validated) {
            $number = MemoNumberGenerator::generate();

            $memo = Memo::create([
                'memo_number' => $number['memo_number'],
                'memo_sequence' => $number['memo_sequence'],
                'memo_date' => $validated['memo_date'],
                'subject' => $this->normalizeText($validated['subject']),
                'description' => $this->normalizeNullableText($validated['description'] ?? null),
                'department_id' => $validated['department_id'] ?? null,
                'sender' => $this->normalizeNullableText($validated['sender'] ?? null),
                'receiver' => $this->normalizeNullableText($validated['receiver'] ?? null),
                'status' => $validated['status'] ?? 'active',
                'workflow_status' => 'draft',
                'notes' => $this->normalizeNullableText($validated['notes'] ?? null),
                'created_by' => Auth::id(),
                'search_text' => $this->buildSearchText($validated),
            ]);

            $this->storeAttachments($request, $memo);

            return $memo;
        });

        ActivityLogger::log('memo.created', 'تم إنشاء المذكرة رقم ' . $memo->memo_number, $memo, ['memo_number' => $memo->memo_number]);

        return redirect()->route('memos.show', $memo)->with('success', 'تم حفظ المذكرة وأرشفتها بنجاح.');
    }

    public function show(Memo $memo)
    {
        $memo->load(['department', 'creator', 'attachments.uploader', 'workflowActions.actor', 'workflowSubmitter', 'workflowReviewer', 'workflowFinalizer']);

        return view('memos.show', compact('memo'));
    }

    public function previewAttachment(Memo $memo, MemoAttachment $attachment)
    {
        $this->ensureAttachmentBelongsToMemo($memo, $attachment);
        $attachment->load(['memo.department', 'uploader']);

        ActivityLogger::log('memo_attachment.previewed', 'تمت معاينة مرفق مذكرة: ' . ($attachment->original_name ?: $attachment->file_name), $attachment, [
            'memo_id' => $memo->id,
            'memo_number' => $memo->memo_number,
        ]);

        return view('memos.attachment-preview', compact('memo', 'attachment'));
    }

    public function attachmentData(Memo $memo, MemoAttachment $attachment)
    {
        $this->ensureAttachmentBelongsToMemo($memo, $attachment);

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (! $disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        $extension = strtolower(
            $attachment->extension ?: pathinfo($attachment->original_name ?: $attachment->file_name, PATHINFO_EXTENSION)
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

        $binary = $disk->get($attachment->file_path);

        return response()->json([
            'id' => $attachment->id,
            'name' => $attachment->original_name ?: $attachment->file_name,
            'file_name' => $attachment->original_name ?: $attachment->file_name,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $attachment->file_size,
            'base64' => base64_encode($binary),
        ]);
    }

    public function edit(Memo $memo)
    {
        $this->ensureMemoCanBeModified($memo);

        $departments = Department::query()->where('is_active', true)->orderBy('name')->get();
        $memo->load(['department', 'attachments']);

        return view('memos.edit', compact('memo', 'departments'));
    }

    public function update(Request $request, Memo $memo)
    {
        $this->ensureMemoCanBeModified($memo);

        $validated = $request->validate($this->rules(true), $this->messages());

        DB::transaction(function () use ($request, $memo, $validated) {
            $memo->update([
                'memo_date' => $validated['memo_date'],
                'subject' => $this->normalizeText($validated['subject']),
                'description' => $this->normalizeNullableText($validated['description'] ?? null),
                'department_id' => $validated['department_id'] ?? null,
                'sender' => $this->normalizeNullableText($validated['sender'] ?? null),
                'receiver' => $this->normalizeNullableText($validated['receiver'] ?? null),
                'status' => $validated['status'] ?? 'active',
                'workflow_status' => 'draft',
                'notes' => $this->normalizeNullableText($validated['notes'] ?? null),
                'search_text' => $this->buildSearchText($validated),
            ]);

            $this->storeAttachments($request, $memo);
        });

        ActivityLogger::log('memo.updated', 'تم تعديل المذكرة رقم ' . $memo->memo_number, $memo, ['memo_number' => $memo->memo_number]);

        return redirect()->route('memos.show', $memo)->with('success', 'تم تحديث المذكرة بنجاح.');
    }

    public function destroy(Memo $memo)
    {
        $this->ensureMemoCanBeModified($memo);

        ActivityLogger::log('memo.deleted', 'تم حذف المذكرة رقم ' . $memo->memo_number, $memo, ['memo_number' => $memo->memo_number]);

        $memo->delete();

        return redirect()->route('memos.index')->with('success', 'تم حذف المذكرة ونقلها من القائمة الحالية.');
    }

    public function inlineAttachment(Memo $memo, MemoAttachment $attachment)
    {
        $this->ensureAttachmentBelongsToMemo($memo, $attachment);

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (! $disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        $extension = strtolower($attachment->extension ?: pathinfo($attachment->original_name, PATHINFO_EXTENSION));
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
            'Content-Disposition' => 'inline; filename="memo-attachment.' . ($extension ?: 'bin') . '"',
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

    public function downloadAttachment(Memo $memo, MemoAttachment $attachment)
    {
        $this->ensureAttachmentBelongsToMemo($memo, $attachment);

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (! $disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        ActivityLogger::log('memo_attachment.downloaded', 'تم تنزيل مرفق مذكرة: ' . ($attachment->original_name ?: $attachment->file_name), $attachment, [
            'memo_id' => $memo->id,
            'memo_number' => $memo->memo_number,
        ]);

        $fileName = $attachment->original_name ?: $attachment->file_name;

        if (method_exists($disk, 'path')) {
            return response()->download($disk->path($attachment->file_path), $fileName);
        }

        return response()->streamDownload(function () use ($disk, $attachment) {
            $stream = $disk->readStream($attachment->file_path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $fileName);
    }


    private function ensureMemoCanBeModified(Memo $memo): void
    {
        if (method_exists($memo, 'canBeModifiedBy') && ! $memo->canBeModifiedBy(Auth::user())) {
            abort(403, 'هذه المذكرة مؤرشفة نهائيًا ولا يمكن تعديلها أو حذفها إلا بصلاحية عليا.');
        }
    }

    private function rules(bool $updating = false): array
    {
        return [
            'memo_date' => ['required', 'date'],
            'subject' => ['required', 'string', 'max:5000'],
            'description' => ['nullable', 'string'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'sender' => ['nullable', 'string', 'max:255'],
            'receiver' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,archived,cancelled'],
            'notes' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:20480'],
        ];
    }

    private function messages(): array
    {
        return [
            'memo_date.required' => 'تاريخ المذكرة مطلوب.',
            'subject.required' => 'موضوع المذكرة مطلوب.',
            'attachments.*.max' => 'حجم كل مرفق يجب ألا يتجاوز 20 MB.',
        ];
    }

    private function storeAttachments(Request $request, Memo $memo): void
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

            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $safeName = $memo->memo_number . '_' . Str::random(12) . '.' . $extension;
            $folder = 'memos/' . $memo->memo_number;
            $path = $file->storeAs($folder, $safeName, 'local');

            $latestVersion = MemoAttachment::query()->where('memo_id', $memo->id)->max('version_no');
            $isFirst = ! MemoAttachment::query()->where('memo_id', $memo->id)->exists();

            MemoAttachment::create([
                'memo_id' => $memo->id,
                'version_no' => ((int) $latestVersion) + 1,
                'is_main' => $isFirst,
                'original_name' => $file->getClientOriginalName(),
                'file_name' => $safeName,
                'file_path' => $path,
                'disk' => 'local',
                'extension' => $extension,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function ensureAttachmentBelongsToMemo(Memo $memo, MemoAttachment $attachment): void
    {
        abort_unless((int) $attachment->memo_id === (int) $memo->id, 404);
    }

    private function buildSearchText(array $data): string
    {
        return trim(
            ($data['subject'] ?? '') . ' ' .
            ($data['description'] ?? '') . ' ' .
            ($data['sender'] ?? '') . ' ' .
            ($data['receiver'] ?? '') . ' ' .
            ($data['notes'] ?? '')
        );
    }

    private function normalizeText(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?: (string) $value);
    }

    private function normalizeNullableText(?string $value): ?string
    {
        $value = $this->normalizeText($value);
        return $value === '' ? null : $value;
    }
}

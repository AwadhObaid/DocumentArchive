<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentType;
use App\Models\Setting;
use App\Services\ReferenceNumberGenerator;
use Illuminate\Http\Request;
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

        $documents = Document::query()
            ->with(['department', 'documentType', 'mainAttachment'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim($request->q);

                $query->where(function ($subQuery) use ($q) {
                    $subQuery
                        ->where('reference_number', 'like', "%{$q}%")
                        ->orWhere('main_policy_number', 'like', "%{$q}%")
                        ->orWhere('sub_policy_number', 'like', "%{$q}%")
                        ->orWhere('title', 'like', "%{$q}%")
                        ->orWhere('subject', 'like', "%{$q}%")
                        ->orWhere('sender', 'like', "%{$q}%")
                        ->orWhere('receiver', 'like', "%{$q}%")
                        ->orWhere('search_text', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('department_id'), function ($query) use ($request) {
                $query->where('department_id', $request->department_id);
            })
            ->when($request->filled('document_type_id'), function ($query) use ($request) {
                $query->where('document_type_id', $request->document_type_id);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('reference_date', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('reference_date', '<=', $request->date_to);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('documents.index', compact('documents', 'departments', 'documentTypes'));
    }

    public function trash()
    {
        $documents = Document::onlyTrashed()
            ->with(['department', 'documentType', 'mainAttachment'])
            ->latest('deleted_at')
            ->paginate(15);

        return view('documents.trash', compact('documents'));
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
                'created_by' => auth()->id(),

                'status' => $request->hasFile('attachment') ? 'archived' : 'registered',
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
            'status' => ['required', 'in:registered,archived,active,cancelled'],
            'attachment' => ['nullable', 'file', 'max:20480'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $document, $validated) {
            $status = $validated['status'];

            if ($request->hasFile('attachment')) {
                $status = 'archived';
            }

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

                'status' => $status,
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

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'تم تحديث بيانات الكتاب بنجاح.');
    }

    public function destroy(Document $document)
    {
        $document->delete();

        return redirect()
            ->route('documents.index')
            ->with('success', 'تم حذف الكتاب ونقله إلى سلة المحذوفات.');
    }

    public function restore($id)
    {
        $document = Document::onlyTrashed()->findOrFail($id);
        $document->restore();

        return redirect()
            ->route('documents.trash')
            ->with('success', 'تمت استعادة الكتاب بنجاح.');
    }

    public function forceDelete($id)
    {
        $document = Document::onlyTrashed()->with('attachments')->findOrFail($id);

        DB::transaction(function () use ($document) {
            foreach ($document->attachments as $attachment) {
                if (Storage::disk($attachment->disk)->exists($attachment->file_path)) {
                    Storage::disk($attachment->disk)->delete($attachment->file_path);
                }
            }

            $document->forceDelete();
        });

        return redirect()
            ->route('documents.trash')
            ->with('success', 'تم حذف الكتاب نهائياً.');
    }

    public function printReference(Document $document)
    {
        return view('documents.print-reference', compact('document'));
    }

    public function downloadAttachment(DocumentAttachment $attachment)
    {
        if (!Storage::disk($attachment->disk)->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->file_path,
            $attachment->original_name
        );
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

        DocumentAttachment::create([
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
            'uploaded_by' => auth()->id(),
        ]);
    }

    private function buildSearchText(array $data): string
    {
        return trim(
            ($data['main_policy_number'] ?? '') . ' ' .
            ($data['sub_policy_number'] ?? '') . ' ' .
            ($data['title'] ?? '') . ' ' .
            ($data['subject'] ?? '') . ' ' .
            ($data['description'] ?? '') . ' ' .
            ($data['sender'] ?? '') . ' ' .
            ($data['receiver'] ?? '')
        );
    }
}

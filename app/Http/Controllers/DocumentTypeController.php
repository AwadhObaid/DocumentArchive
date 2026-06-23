<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentTypeController extends Controller
{
    public function index()
    {
        $documentTypes = DocumentType::query()
            ->withCount('documents')
            ->orderByDesc('id')
            ->paginate(15);

        return view('document-types.index', compact('documentTypes'));
    }

    public function create()
    {
        return view('document-types.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:document_types,code'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DocumentType::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('document-types.index')
            ->with('success', 'تمت إضافة نوع المستند بنجاح.');
    }

    public function edit(DocumentType $documentType)
    {
        return view('document-types.edit', compact('documentType'));
    }

    public function update(Request $request, DocumentType $documentType)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('document_types', 'code')->ignore($documentType->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $documentType->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('document-types.index')
            ->with('success', 'تم تحديث نوع المستند بنجاح.');
    }

    public function destroy(DocumentType $documentType)
    {
        if ($documentType->documents()->exists()) {
            $documentType->update(['is_active' => false]);

            return redirect()
                ->route('document-types.index')
                ->with('success', 'لا يمكن حذف النوع لارتباطه بمستندات، لذلك تم تعطيله فقط.');
        }

        $documentType->delete();

        return redirect()
            ->route('document-types.index')
            ->with('success', 'تم حذف نوع المستند بنجاح.');
    }
}
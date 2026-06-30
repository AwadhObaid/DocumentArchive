<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentTypeController extends Controller
{
    public function index(Request $request)
    {
        /* DEFINITIONS_POLISH_CONTROLLER_START */
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', 'all'),
            'linked' => (string) $request->input('linked', 'all'),
            'sort' => (string) $request->input('sort', 'latest'),
        ];

        if (! in_array($filters['status'], ['all', 'active', 'inactive'], true)) {
            $filters['status'] = 'all';
        }

        if (! in_array($filters['linked'], ['all', 'linked', 'empty'], true)) {
            $filters['linked'] = 'all';
        }

        if (! in_array($filters['sort'], ['latest', 'oldest', 'name', 'documents'], true)) {
            $filters['sort'] = 'latest';
        }

        $baseQuery = DocumentType::query()
            ->withCount([
                'documents',
                'documents as all_documents_count' => function ($query) {
                    $query->withTrashed();
                },
            ]);

        $documentTypesQuery = (clone $baseQuery);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $documentTypesQuery->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('code', 'like', '%' . $q . '%')
                    ->orWhere('description', 'like', '%' . $q . '%');
            });
        }

        if ($filters['status'] === 'active') {
            $documentTypesQuery->where('is_active', true);
        } elseif ($filters['status'] === 'inactive') {
            $documentTypesQuery->where('is_active', false);
        }

        if ($filters['linked'] === 'linked') {
            $documentTypesQuery->whereHas('documents', function ($query) {
                $query->withTrashed();
            });
        } elseif ($filters['linked'] === 'empty') {
            $documentTypesQuery->whereDoesntHave('documents', function ($query) {
                $query->withTrashed();
            });
        }

        match ($filters['sort']) {
            'oldest' => $documentTypesQuery->orderBy('id'),
            'name' => $documentTypesQuery->orderBy('name'),
            'documents' => $documentTypesQuery->orderByDesc('all_documents_count')->orderBy('name'),
            default => $documentTypesQuery->orderByDesc('id'),
        };

        $documentTypes = $documentTypesQuery
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => DocumentType::query()->count(),
            'active' => DocumentType::query()->where('is_active', true)->count(),
            'inactive' => DocumentType::query()->where('is_active', false)->count(),
            'linked' => DocumentType::query()->whereHas('documents', function ($query) {
                $query->withTrashed();
            })->count(),
        ];

        return view('document-types.index', compact('documentTypes', 'filters', 'stats'));
        /* DEFINITIONS_POLISH_CONTROLLER_END */
    }

    public function create()
    {
        return view('document-types.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), $this->messages());

        $documentType = DocumentType::create([
            'name' => $this->normalizeText($validated['name']),
            'code' => $this->normalizeCode($validated['code'] ?? null),
            'description' => $this->normalizeNullableText($validated['description'] ?? null),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->logDefinitionEvent(
            'document_type.created',
            'تمت إضافة نوع الكتاب: ' . $documentType->name,
            $documentType,
            ['name' => $documentType->name, 'code' => $documentType->code]
        );

        return redirect()
            ->route('document-types.index')
            ->with('success', 'تمت إضافة نوع الكتاب بنجاح.');
    }

    public function edit(DocumentType $documentType)
    {
        $documentType->loadCount([
            'documents',
            'documents as all_documents_count' => function ($query) {
                $query->withTrashed();
            },
        ]);

        return view('document-types.edit', compact('documentType'));
    }

    public function update(Request $request, DocumentType $documentType)
    {
        $validated = $request->validate($this->rules($documentType), $this->messages());

        $documentType->update([
            'name' => $this->normalizeText($validated['name']),
            'code' => $this->normalizeCode($validated['code'] ?? null),
            'description' => $this->normalizeNullableText($validated['description'] ?? null),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->logDefinitionEvent(
            'document_type.updated',
            'تم تعديل نوع الكتاب: ' . $documentType->name,
            $documentType,
            ['name' => $documentType->name, 'code' => $documentType->code]
        );

        return redirect()
            ->route('document-types.index')
            ->with('success', 'تم تحديث نوع الكتاب بنجاح.');
    }

    public function destroy(DocumentType $documentType)
    {
        $hasDocuments = $documentType->documents()->withTrashed()->exists();

        if ($hasDocuments) {
            $documentType->update(['is_active' => false]);

            $this->logDefinitionEvent(
                'document_type.disabled',
                'تم تعطيل نوع الكتاب المرتبط بكتب: ' . $documentType->name,
                $documentType,
                ['name' => $documentType->name, 'reason' => 'linked_documents']
            );

            return redirect()
                ->route('document-types.index')
                ->with('success', 'لا يمكن حذف نوع الكتاب لارتباطه بكتب، لذلك تم تعطيله فقط.');
        }

        $name = $documentType->name;
        $documentType->delete();

        $this->logDefinitionEvent(
            'document_type.deleted',
            'تم حذف نوع الكتاب: ' . $name,
            $documentType,
            ['name' => $name]
        );

        return redirect()
            ->route('document-types.index')
            ->with('success', 'تم حذف نوع الكتاب بنجاح.');
    }

    private function rules(?DocumentType $documentType = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('document_types', 'name')->ignore($documentType?->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('document_types', 'code')->ignore($documentType?->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'اسم نوع الكتاب مطلوب.',
            'name.unique' => 'اسم نوع الكتاب موجود مسبقاً.',
            'code.unique' => 'كود نوع الكتاب موجود مسبقاً.',
        ];
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

    private function normalizeCode(?string $value): ?string
    {
        $value = $this->normalizeNullableText($value);

        return $value === null ? null : mb_strtoupper($value, 'UTF-8');
    }

    private function logDefinitionEvent(string $action, string $description, DocumentType $documentType, array $properties = []): void
    {
        try {
            if (class_exists(\App\Services\ActivityLogger::class) && method_exists(\App\Services\ActivityLogger::class, 'log')) {
                \App\Services\ActivityLogger::log($action, $description, $documentType, $properties);
            }
        } catch (\Throwable $e) {
            // لا نعطل إدارة التعريفات إذا تعذر تسجيل النشاط.
        }
    }
}
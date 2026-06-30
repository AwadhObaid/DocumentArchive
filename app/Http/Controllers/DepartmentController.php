<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
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

        $baseQuery = Department::query()
            ->withCount([
                'documents',
                'documents as all_documents_count' => function ($query) {
                    $query->withTrashed();
                },
            ]);

        $departmentsQuery = (clone $baseQuery);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $departmentsQuery->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('code', 'like', '%' . $q . '%')
                    ->orWhere('description', 'like', '%' . $q . '%');
            });
        }

        if ($filters['status'] === 'active') {
            $departmentsQuery->where('is_active', true);
        } elseif ($filters['status'] === 'inactive') {
            $departmentsQuery->where('is_active', false);
        }

        if ($filters['linked'] === 'linked') {
            $departmentsQuery->whereHas('documents', function ($query) {
                $query->withTrashed();
            });
        } elseif ($filters['linked'] === 'empty') {
            $departmentsQuery->whereDoesntHave('documents', function ($query) {
                $query->withTrashed();
            });
        }

        match ($filters['sort']) {
            'oldest' => $departmentsQuery->orderBy('id'),
            'name' => $departmentsQuery->orderBy('name'),
            'documents' => $departmentsQuery->orderByDesc('all_documents_count')->orderBy('name'),
            default => $departmentsQuery->orderByDesc('id'),
        };

        $departments = $departmentsQuery
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => Department::query()->count(),
            'active' => Department::query()->where('is_active', true)->count(),
            'inactive' => Department::query()->where('is_active', false)->count(),
            'linked' => Department::query()->whereHas('documents', function ($query) {
                $query->withTrashed();
            })->count(),
        ];

        return view('departments.index', compact('departments', 'filters', 'stats'));
        /* DEFINITIONS_POLISH_CONTROLLER_END */
    }

    public function create()
    {
        return view('departments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), $this->messages());

        $department = Department::create([
            'name' => $this->normalizeText($validated['name']),
            'code' => $this->normalizeCode($validated['code'] ?? null),
            'description' => $this->normalizeNullableText($validated['description'] ?? null),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->logDefinitionEvent(
            'department.created',
            'تمت إضافة الإدارة: ' . $department->name,
            $department,
            ['name' => $department->name, 'code' => $department->code]
        );

        return redirect()
            ->route('departments.index')
            ->with('success', 'تمت إضافة الإدارة بنجاح.');
    }

    public function edit(Department $department)
    {
        $department->loadCount([
            'documents',
            'documents as all_documents_count' => function ($query) {
                $query->withTrashed();
            },
        ]);

        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate($this->rules($department), $this->messages());

        $department->update([
            'name' => $this->normalizeText($validated['name']),
            'code' => $this->normalizeCode($validated['code'] ?? null),
            'description' => $this->normalizeNullableText($validated['description'] ?? null),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->logDefinitionEvent(
            'department.updated',
            'تم تعديل الإدارة: ' . $department->name,
            $department,
            ['name' => $department->name, 'code' => $department->code]
        );

        return redirect()
            ->route('departments.index')
            ->with('success', 'تم تحديث بيانات الإدارة بنجاح.');
    }

    public function destroy(Department $department)
    {
        $hasDocuments = $department->documents()->withTrashed()->exists();

        if ($hasDocuments) {
            $department->update(['is_active' => false]);

            $this->logDefinitionEvent(
                'department.disabled',
                'تم تعطيل الإدارة المرتبطة بكتب: ' . $department->name,
                $department,
                ['name' => $department->name, 'reason' => 'linked_documents']
            );

            return redirect()
                ->route('departments.index')
                ->with('success', 'لا يمكن حذف الإدارة لارتباطها بكتب، لذلك تم تعطيلها فقط.');
        }

        $name = $department->name;
        $department->delete();

        $this->logDefinitionEvent(
            'department.deleted',
            'تم حذف الإدارة: ' . $name,
            $department,
            ['name' => $name]
        );

        return redirect()
            ->route('departments.index')
            ->with('success', 'تم حذف الإدارة بنجاح.');
    }

    private function rules(?Department $department = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department?->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('departments', 'code')->ignore($department?->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'اسم الإدارة مطلوب.',
            'name.unique' => 'اسم الإدارة موجود مسبقاً.',
            'code.unique' => 'كود الإدارة موجود مسبقاً.',
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

    private function logDefinitionEvent(string $action, string $description, Department $department, array $properties = []): void
    {
        try {
            if (class_exists(\App\Services\ActivityLogger::class) && method_exists(\App\Services\ActivityLogger::class, 'log')) {
                \App\Services\ActivityLogger::log($action, $description, $department, $properties);
            }
        } catch (\Throwable $e) {
            // لا نعطل إدارة التعريفات إذا تعذر تسجيل النشاط.
        }
    }
}
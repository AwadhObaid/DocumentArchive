<?php

namespace App\Http\Controllers;

use App\Models\BookSubject;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookSubjectController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', 'all'),
            'linked' => (string) $request->input('linked', 'all'),
            'sort' => (string) $request->input('sort', 'order'),
        ];

        if (! in_array($filters['status'], ['all', 'active', 'inactive'], true)) {
            $filters['status'] = 'all';
        }

        if (! in_array($filters['linked'], ['all', 'linked', 'empty'], true)) {
            $filters['linked'] = 'all';
        }

        if (! in_array($filters['sort'], ['order', 'latest', 'oldest', 'name', 'documents'], true)) {
            $filters['sort'] = 'order';
        }

        $query = BookSubject::query()->withCount([
            'documents',
            'documents as all_documents_count' => function ($q) {
                $q->withTrashed();
            },
        ]);

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($filters['status'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['status'] === 'inactive') {
            $query->where('is_active', false);
        }

        if ($filters['linked'] === 'linked') {
            $query->whereHas('documents', function ($q) {
                $q->withTrashed();
            });
        } elseif ($filters['linked'] === 'empty') {
            $query->whereDoesntHave('documents', function ($q) {
                $q->withTrashed();
            });
        }

        match ($filters['sort']) {
            'latest' => $query->orderByDesc('id'),
            'oldest' => $query->orderBy('id'),
            'name' => $query->orderBy('name'),
            'documents' => $query->orderByDesc('all_documents_count')->orderBy('name'),
            default => $query->orderBy('sort_order')->orderBy('name'),
        };

        $bookSubjects = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => BookSubject::query()->count(),
            'active' => BookSubject::query()->where('is_active', true)->count(),
            'inactive' => BookSubject::query()->where('is_active', false)->count(),
            'linked' => BookSubject::query()->whereHas('documents', function ($q) {
                $q->withTrashed();
            })->count(),
        ];

        return view('book-subjects.index', compact('bookSubjects', 'filters', 'stats'));
    }

    public function create()
    {
        return view('book-subjects.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(), $this->messages());

        $subject = BookSubject::create([
            'name' => $this->normalizeText($validated['name']),
            'code' => $this->normalizeCode($validated['code'] ?? null),
            'description' => $this->normalizeNullableText($validated['description'] ?? null),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        ActivityLogger::log('book_subject.created', 'تمت إضافة موضوع كتاب: ' . $subject->name, $subject, ['name' => $subject->name]);

        return redirect()->route('book-subjects.index')->with('success', 'تمت إضافة موضوع الكتاب بنجاح.');
    }

    public function edit(BookSubject $bookSubject)
    {
        $bookSubject->loadCount([
            'documents',
            'documents as all_documents_count' => function ($q) {
                $q->withTrashed();
            },
        ]);

        return view('book-subjects.edit', compact('bookSubject'));
    }

    public function update(Request $request, BookSubject $bookSubject)
    {
        $validated = $request->validate($this->rules($bookSubject), $this->messages());

        $bookSubject->update([
            'name' => $this->normalizeText($validated['name']),
            'code' => $this->normalizeCode($validated['code'] ?? null),
            'description' => $this->normalizeNullableText($validated['description'] ?? null),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        ActivityLogger::log('book_subject.updated', 'تم تعديل موضوع كتاب: ' . $bookSubject->name, $bookSubject, ['name' => $bookSubject->name]);

        return redirect()->route('book-subjects.index')->with('success', 'تم تحديث موضوع الكتاب بنجاح.');
    }

    public function destroy(BookSubject $bookSubject)
    {
        $hasDocuments = $bookSubject->documents()->withTrashed()->exists();

        if ($hasDocuments) {
            $bookSubject->update(['is_active' => false]);

            ActivityLogger::log('book_subject.disabled', 'تم تعطيل موضوع كتاب مرتبط بكتب: ' . $bookSubject->name, $bookSubject, ['name' => $bookSubject->name]);

            return redirect()->route('book-subjects.index')->with('success', 'لا يمكن حذف موضوع مرتبط بكتب، لذلك تم تعطيله فقط.');
        }

        $name = $bookSubject->name;
        $bookSubject->delete();

        ActivityLogger::log('book_subject.deleted', 'تم حذف موضوع كتاب: ' . $name, null, ['name' => $name]);

        return redirect()->route('book-subjects.index')->with('success', 'تم حذف موضوع الكتاب بنجاح.');
    }

    private function rules(?BookSubject $bookSubject = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('book_subjects', 'name')->ignore($bookSubject?->id)],
            'code' => ['nullable', 'string', 'max:100', Rule::unique('book_subjects', 'code')->ignore($bookSubject?->id)],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'اسم موضوع الكتاب مطلوب.',
            'name.unique' => 'اسم موضوع الكتاب موجود مسبقاً.',
            'code.unique' => 'كود موضوع الكتاب موجود مسبقاً.',
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
}

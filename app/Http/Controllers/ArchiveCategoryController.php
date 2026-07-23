<?php

namespace App\Http\Controllers;

use App\Models\ArchiveCategory;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArchiveCategoryController extends Controller
{
    public function index()
    {
        $circularCategories = ArchiveCategory::query()
            ->forModule('circular')
            ->withCount('circulars')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $miscCategories = ArchiveCategory::query()
            ->forModule('misc_book')
            ->with(['parent', 'children'])
            ->withCount('miscBooks')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view(
            'archive-categories.index',
            compact('circularCategories', 'miscCategories')
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $this->ensureParentMatchesModule(
            $validated['module'],
            $validated['parent_id'] ?? null
        );

        $category = ArchiveCategory::create([
            'module' => $validated['module'],
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => trim($validated['name']),
            'code' => trim($validated['code']),
            'description' => $this->nullableText(
                $validated['description'] ?? null
            ),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        ActivityLogger::log(
            'archive_category.created',
            'تمت إضافة التصنيف: ' . $category->name,
            $category
        );

        return back()->with('success', 'تمت إضافة التصنيف بنجاح.');
    }

    public function update(
        Request $request,
        ArchiveCategory $archiveCategory
    ) {
        $validated = $request->validate(
            $this->rules($archiveCategory)
        );

        $this->ensureParentMatchesModule(
            $validated['module'],
            $validated['parent_id'] ?? null,
            $archiveCategory->id
        );

        $archiveCategory->update([
            'module' => $validated['module'],
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => trim($validated['name']),
            'code' => trim($validated['code']),
            'description' => $this->nullableText(
                $validated['description'] ?? null
            ),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        ActivityLogger::log(
            'archive_category.updated',
            'تم تعديل التصنيف: ' . $archiveCategory->name,
            $archiveCategory
        );

        return back()->with('success', 'تم تحديث التصنيف بنجاح.');
    }

    public function toggle(ArchiveCategory $archiveCategory)
    {
        $archiveCategory->update([
            'is_active' => ! $archiveCategory->is_active,
        ]);

        ActivityLogger::log(
            'archive_category.toggled',
            'تم تغيير حالة التصنيف: ' . $archiveCategory->name,
            $archiveCategory,
            ['is_active' => $archiveCategory->is_active]
        );

        return back()->with('success', 'تم تغيير حالة التصنيف.');
    }

    public function destroy(ArchiveCategory $archiveCategory)
    {
        $inUse = $archiveCategory->circulars()->withTrashed()->exists()
            || $archiveCategory->miscBooks()->withTrashed()->exists()
            || $archiveCategory->children()->exists();

        if ($inUse) {
            return back()->withErrors([
                'category' => 'لا يمكن حذف التصنيف لأنه مستخدم أو يحتوي تصنيفات فرعية. يمكنك تعطيله بدلًا من حذفه.',
            ]);
        }

        ActivityLogger::log(
            'archive_category.deleted',
            'تم حذف التصنيف: ' . $archiveCategory->name,
            $archiveCategory
        );

        $archiveCategory->delete();

        return back()->with('success', 'تم حذف التصنيف بنجاح.');
    }

    private function rules(?ArchiveCategory $category = null): array
    {
        return [
            'module' => [
                'required',
                Rule::in(['circular', 'misc_book']),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:archive_categories,id',
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('archive_categories', 'code')
                    ->where(
                        fn ($query) => $query->where(
                            'module',
                            request('module')
                        )
                    )
                    ->ignore($category?->id),
            ],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function ensureParentMatchesModule(
        string $module,
        mixed $parentId,
        ?int $currentId = null
    ): void {
        if (! $parentId) {
            return;
        }

        $parent = ArchiveCategory::findOrFail((int) $parentId);

        abort_if(
            $parent->module !== $module,
            422,
            'يجب أن يكون التصنيف الأب من الوحدة نفسها.'
        );

        abort_if(
            $currentId !== null && $parent->id === $currentId,
            422,
            'لا يمكن جعل التصنيف تابعًا لنفسه.'
        );
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

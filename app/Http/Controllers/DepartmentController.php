<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::query()
            ->withCount('documents')
            ->orderByDesc('id')
            ->paginate(15);

        return view('departments.index', compact('departments'));
    }

    public function create()
    {
        return view('departments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:departments,code'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Department::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('departments.index')
            ->with('success', 'تمت إضافة الإدارة بنجاح.');
    }

    public function edit(Department $department)
    {
        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('departments', 'code')->ignore($department->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $department->update([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('departments.index')
            ->with('success', 'تم تحديث بيانات الإدارة بنجاح.');
    }

    public function destroy(Department $department)
    {
        if ($department->documents()->exists()) {
            $department->update(['is_active' => false]);

            return redirect()
                ->route('departments.index')
                ->with('success', 'لا يمكن حذف الإدارة لارتباطها بمستندات، لذلك تم تعطيلها فقط.');
        }

        $department->delete();

        return redirect()
            ->route('departments.index')
            ->with('success', 'تم حذف الإدارة بنجاح.');
    }
}
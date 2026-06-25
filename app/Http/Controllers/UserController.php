<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function ensureAdmin(): void
    {
        $role = trim((string) (auth()->user()->role ?? ''));
        $adminRoles = ['admin', 'administrator', 'super_admin', 'مدير النظام', 'مدير'];

        abort_unless(auth()->check() && in_array($role, $adminRoles, true), 403, 'هذه الصفحة متاحة لمدير النظام فقط.');
    }

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $users = User::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim($request->q);

                $query->where(function ($subQuery) use ($q) {
                    $subQuery
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('username', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->role))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', (bool) $request->is_active))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $this->ensureAdmin();

        return view('users.create', [
            'permissionGroups' => PermissionRegistry::groups(),
            'defaultPermissions' => PermissionRegistry::defaultsForRole('user'),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', 'in:admin,user,viewer'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionRegistry::keys())],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $role = $validated['role'];
        $permissions = $role === 'admin'
            ? ['*']
            : PermissionRegistry::normalize($validated['permissions'] ?? PermissionRegistry::defaultsForRole($role));

        User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => $role,
            'permissions' => $permissions,
            'password' => $validated['password'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'تم إنشاء المستخدم والصلاحيات بنجاح.');
    }

    public function edit(User $user)
    {
        $this->ensureAdmin();

        return view('users.edit', [
            'user' => $user,
            'permissionGroups' => PermissionRegistry::groups(),
            'defaultPermissions' => $user->resolvedPermissions(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', 'in:admin,user,viewer'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionRegistry::keys())],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $role = $validated['role'];
        $permissions = $role === 'admin'
            ? ['*']
            : PermissionRegistry::normalize($validated['permissions'] ?? []);

        $user->update([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => $role,
            'permissions' => $permissions,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'تم تحديث بيانات المستخدم والصلاحيات بنجاح.');
    }

    public function editPassword(User $user)
    {
        $this->ensureAdmin();

        return view('users.password', compact('user'));
    }

    public function updatePassword(Request $request, User $user)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => $validated['password'],
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'تم تغيير كلمة مرور المستخدم بنجاح.');
    }

    public function activate(User $user)
    {
        $this->ensureAdmin();

        $user->update(['is_active' => true]);

        return back()->with('success', 'تم تفعيل المستخدم بنجاح.');
    }

    public function deactivate(User $user)
    {
        $this->ensureAdmin();

        if (auth()->id() === $user->id) {
            return back()->withErrors(['user' => 'لا يمكنك تعطيل حسابك الحالي.']);
        }

        $user->update(['is_active' => false]);

        return back()->with('success', 'تم تعطيل المستخدم بنجاح.');
    }

    public function destroy(User $user)
    {
        $this->ensureAdmin();

        if (auth()->id() === $user->id) {
            return back()->withErrors(['user' => 'لا يمكنك حذف حسابك الحالي.']);
        }

        $user->update(['is_active' => false]);

        return redirect()
            ->route('users.index')
            ->with('success', 'تم تعطيل المستخدم بدلاً من حذفه للحفاظ على سجل العمليات.');
    }
}

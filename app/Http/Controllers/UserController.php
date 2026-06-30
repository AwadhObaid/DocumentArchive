<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function ensureCanManageUsers(): void
    {
        abort_unless(
            auth()->check() && auth()->user()?->hasPermission('users.manage'),
            403,
            'هذه الصفحة متاحة لمن يملك صلاحية إدارة المستخدمين فقط.'
        );
    }

    public function index(Request $request)
    {
        $this->ensureCanManageUsers();

        $users = User::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->q);

                $query->where(function ($subQuery) use ($q) {
                    $subQuery
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('username', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->role))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->is_active === '1'))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $this->ensureCanManageUsers();

        return view('users.create', [
            'permissionGroups' => PermissionRegistry::groups(),
            'defaultPermissions' => PermissionRegistry::defaultsForRole('user'),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureCanManageUsers();

        $validated = $request->validate($this->rules(), $this->messages());

        $role = $validated['role'];
        $permissions = $role === 'admin'
            ? ['*']
            : PermissionRegistry::normalize($validated['permissions'] ?? PermissionRegistry::defaultsForRole($role));

        User::create([
            'name' => trim((string) $validated['name']),
            'username' => trim((string) $validated['username']),
            'email' => filled($validated['email'] ?? null) ? trim((string) $validated['email']) : null,
            'phone' => filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null,
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
        $this->ensureCanManageUsers();

        return view('users.edit', [
            'user' => $user,
            'permissionGroups' => PermissionRegistry::groups(),
            'defaultPermissions' => $user->resolvedPermissions(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureCanManageUsers();

        $validated = $request->validate($this->rules($user), $this->messages());

        $role = $validated['role'];
        $willBeActive = $request->boolean('is_active');

        if (auth()->id() === $user->id && (!$willBeActive || $role !== 'admin')) {
            return back()->withErrors([
                'user' => 'لا يمكنك تعطيل حسابك الحالي أو إزالة صلاحية مدير النظام عن نفسك.',
            ])->withInput();
        }

        if ($this->isLastActiveAdmin($user) && (!$willBeActive || $role !== 'admin')) {
            return back()->withErrors([
                'user' => 'لا يمكن تعطيل أو تخفيض آخر مدير نظام نشط.',
            ])->withInput();
        }

        $permissions = $role === 'admin'
            ? ['*']
            : PermissionRegistry::normalize($validated['permissions'] ?? []);

        $user->update([
            'name' => trim((string) $validated['name']),
            'username' => trim((string) $validated['username']),
            'email' => filled($validated['email'] ?? null) ? trim((string) $validated['email']) : null,
            'phone' => filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null,
            'role' => $role,
            'permissions' => $permissions,
            'is_active' => $willBeActive,
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'تم تحديث بيانات المستخدم والصلاحيات بنجاح.');
    }

    public function editPassword(User $user)
    {
        $this->ensureCanManageUsers();

        return view('users.password', compact('user'));
    }

    public function updatePassword(Request $request, User $user)
    {
        $this->ensureCanManageUsers();

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => 'كلمة المرور الجديدة مطلوبة.',
            'password.min' => 'كلمة المرور الجديدة يجب ألا تقل عن 8 أحرف.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
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
        $this->ensureCanManageUsers();

        $user->update(['is_active' => true]);

        return back()->with('success', 'تم تفعيل المستخدم بنجاح.');
    }

    public function deactivate(User $user)
    {
        $this->ensureCanManageUsers();

        if (auth()->id() === $user->id) {
            return back()->withErrors(['user' => 'لا يمكنك تعطيل حسابك الحالي.']);
        }

        if ($this->isLastActiveAdmin($user)) {
            return back()->withErrors(['user' => 'لا يمكن تعطيل آخر مدير نظام نشط.']);
        }

        $user->update(['is_active' => false]);

        return back()->with('success', 'تم تعطيل المستخدم بنجاح.');
    }

    public function destroy(User $user)
    {
        $this->ensureCanManageUsers();

        if (auth()->id() === $user->id) {
            return back()->withErrors(['user' => 'لا يمكنك حذف حسابك الحالي.']);
        }

        if ($this->isLastActiveAdmin($user)) {
            return back()->withErrors(['user' => 'لا يمكن حذف أو تعطيل آخر مدير نظام نشط.']);
        }

        $user->update(['is_active' => false]);

        return redirect()
            ->route('users.index')
            ->with('success', 'تم تعطيل المستخدم بدلاً من حذفه للحفاظ على سجل العمليات.');
    }

    private function rules(?User $user = null): array
    {
        $userId = $user?->id;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', 'in:admin,user,viewer'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionRegistry::keys())],
            'is_active' => ['nullable', 'boolean'],
        ];

        if (!$user) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        return $rules;
    }

    private function messages(): array
    {
        return [
            'name.required' => 'اسم المستخدم الكامل مطلوب.',
            'username.required' => 'اسم الدخول مطلوب.',
            'username.unique' => 'اسم الدخول مستخدم مسبقاً.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique' => 'البريد الإلكتروني مستخدم مسبقاً.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'كلمة المرور يجب ألا تقل عن 8 أحرف.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
        ];
    }

    private function isLastActiveAdmin(User $user): bool
    {
        if (!$user->isAdmin() || !$user->is_active) {
            return false;
        }

        return User::query()
            ->where('is_active', true)
            ->whereIn('role', ['admin', 'administrator', 'super_admin', 'مدير النظام', 'مدير'])
            ->whereKeyNot($user->id)
            ->doesntExist();
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'اسم المستخدم مطلوب.',
            'password.required' => 'كلمة المرور مطلوبة.',
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withErrors([
                    'username' => 'تم إيقاف المحاولة مؤقتاً بسبب كثرة محاولات الدخول. حاول مرة أخرى بعد ' . $seconds . ' ثانية.',
                ])
                ->onlyInput('username');
        }

        $credentials = [
            'username' => trim((string) $validated['username']),
            'password' => $validated['password'],
        ];

        $remember = $request->boolean('remember');

        if (!Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors([
                    'username' => 'بيانات الدخول غير صحيحة.',
                ])
                ->onlyInput('username');
        }

        $user = $request->user();

        if (!$user || !$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors([
                    'username' => 'تم تعطيل هذا الحساب. يرجى مراجعة مدير النظام.',
                ])
                ->onlyInput('username');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        if (Schema::hasColumn('users', 'last_login_at')) {
            $user->forceFill([
                'last_login_at' => now(),
            ])->save();
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logoutNotice(Request $request)
    {
        return response()
            ->view('auth.logout-notice')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'تم تسجيل الخروج بنجاح.');
    }

    private function throttleKey(Request $request): string
    {
        return mb_strtolower(trim((string) $request->input('username'))) . '|' . $request->ip();
    }
}

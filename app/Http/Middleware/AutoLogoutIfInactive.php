<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLogoutIfInactive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $enabled = (string) Setting::getValue('auto_logout_enabled', '0') === '1';

        if (! $enabled) {
            $request->session()->put('last_activity_at', now()->timestamp);
            return $next($request);
        }

        $minutes = (int) Setting::getValue('auto_logout_minutes', 30);
        $minutes = max(1, min(1440, $minutes));
        $timeoutSeconds = $minutes * 60;
        $lastActivity = (int) $request->session()->get('last_activity_at', now()->timestamp);
        $idleSeconds = now()->timestamp - $lastActivity;

        if ($idleSeconds > $timeoutSeconds) {
            Auth::guard()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->is('session/activity')) {
                return response()->json([
                    'message' => 'تم تسجيل الخروج تلقائياً بسبب عدم النشاط.',
                    'redirect' => route('login'),
                ], 419);
            }

            return redirect()
                ->route('login')
                ->with('error', 'تم تسجيل الخروج تلقائياً بسبب عدم النشاط لمدة ' . $minutes . ' دقيقة.');
        }

        $request->session()->put('last_activity_at', now()->timestamp);

        return $next($request);
    }
}

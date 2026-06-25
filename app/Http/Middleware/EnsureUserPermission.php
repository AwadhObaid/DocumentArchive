<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless($user && $user->is_active && $user->hasPermission($permission), 403, 'ليست لديك صلاحية الوصول إلى هذه العملية.');

        return $next($request);
    }
}

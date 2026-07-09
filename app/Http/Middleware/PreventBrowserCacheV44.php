<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBrowserCacheV44
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($this->shouldProtect($request)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Mon, 01 Jan 1990 00:00:00 GMT');
            $response->headers->set('X-Accel-Expires', '0');
            $response->headers->set('Referrer-Policy', 'same-origin');
        }

        if ($request->is('logout')) {
            // Clears local browser storage/cache for this app origin after logout.
            // It does not and cannot delete passwords already saved in Chrome/Edge password manager.
            $response->headers->set('Clear-Site-Data', '"cache", "storage"');
        }

        return $response;
    }

    private function shouldProtect(Request $request): bool
    {
        if ($request->is('login') || $request->is('logout')) {
            return true;
        }

        try {
            return (bool) $request->user();
        } catch (\Throwable $exception) {
            return false;
        }
    }
}

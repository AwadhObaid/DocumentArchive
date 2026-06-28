<?php

namespace App\Http\Middleware;

use App\Services\SystemNotificationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersistImportantFlashNotifications
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$request->user() || !$request->hasSession()) {
            return $response;
        }

        if ($request->is('notifications*')) {
            return $response;
        }

        $map = [
            'success' => ['نجاح العملية', 'success'],
            'status' => ['تنبيه النظام', 'success'],
            'info' => ['معلومة', 'info'],
            'warning' => ['تحذير', 'warning'],
            'error' => ['خطأ', 'danger'],
        ];

        foreach ($map as $key => [$title, $type]) {
            $message = $request->session()->get($key);
            if (is_string($message) && trim($message) !== '') {
                SystemNotificationService::notifyCurrentUser($title, trim($message), $type, null, [
                    'source' => 'flash',
                    'path' => $request->path(),
                ]);
            }
        }

        if ($request->session()->has('errors')) {
            $errors = $request->session()->get('errors');
            if (is_object($errors) && method_exists($errors, 'all') && count($errors->all()) > 0) {
                SystemNotificationService::notifyCurrentUser(
                    'أخطاء في النموذج',
                    'توجد حقول تحتاج مراجعة قبل إكمال العملية.',
                    'warning',
                    null,
                    ['source' => 'validation', 'path' => $request->path()]
                );
            }
        }

        return $response;
    }
}
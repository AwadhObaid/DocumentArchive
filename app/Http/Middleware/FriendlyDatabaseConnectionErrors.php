<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class FriendlyDatabaseConnectionErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);

            return $this->withNoStoreHeaders($response);
        } catch (Throwable $exception) {
            if ($this->isDatabaseConnectionError($exception)) {
                return $this->renderFriendlyDatabaseError($request, $exception);
            }

            throw $exception;
        }
    }

    private function isDatabaseConnectionError(Throwable $exception): bool
    {
        $current = $exception;

        while ($current) {
            $message = $current->getMessage();

            if ($current instanceof QueryException || $current instanceof PDOException) {
                if ($this->messageLooksLikeConnectionFailure($message)) {
                    return true;
                }
            }

            if ($this->messageLooksLikeConnectionFailure($message)) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    private function messageLooksLikeConnectionFailure(string $message): bool
    {
        $needles = [
            'SQLSTATE[HY000] [2002]',
            'No connection could be made because the target machine actively refused it',
            'Connection refused',
            'php_network_getaddresses',
            'getaddrinfo failed',
            'SQLSTATE[HY000] [1049]',
            'Unknown database',
            'SQLSTATE[HY000] [2006]',
            'MySQL server has gone away',
        ];

        foreach ($needles as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function renderFriendlyDatabaseError(Request $request, Throwable $exception): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'تعذر الاتصال بقاعدة البيانات. يرجى التأكد من تشغيل MySQL ثم تحديث الصفحة.',
                'status' => 'database_unavailable',
            ], 503, $this->noStoreHeaders());
        }

        return response()
            ->view('errors.database-connection', [
                'appName' => config('app.name', 'DocumentArchive'),
                'databaseHost' => config('database.connections.' . config('database.default') . '.host', '127.0.0.1'),
                'databasePort' => config('database.connections.' . config('database.default') . '.port', '3306'),
                'databaseName' => config('database.connections.' . config('database.default') . '.database', 'document_archive'),
            ], 503)
            ->withHeaders($this->noStoreHeaders());
    }

    private function withNoStoreHeaders(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Mon, 01 Jan 1990 00:00:00 GMT');

        return $response;
    }

    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Mon, 01 Jan 1990 00:00:00 GMT',
        ];
    }
}
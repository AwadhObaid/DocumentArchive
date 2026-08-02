<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // friendly-database-error-v48:start
        $middleware->prepend(\App\Http\Middleware\FriendlyDatabaseConnectionErrors::class);
        // friendly-database-error-v48:end
        // auth-no-autofill-v44:start
        // Prevent cached authenticated screens and login form values after logout.
        $middleware->web(append: [
            \App\Http\Middleware\PreventBrowserCacheV44::class,
        ]);
        // auth-no-autofill-v44:end

        $middleware->web(append: [
            \App\Http\Middleware\PersistImportantFlashNotifications::class,
            \App\Http\Middleware\AutoLogoutIfInactive::class,
        ]);

        // file-bridge-v94-2-csrf:start
        // The Windows helper authenticates with a one-time high-entropy token,
        // not a browser session. Keep the exclusion limited to these endpoints.
        $middleware->preventRequestForgery(except: [
            'file-bridge/client/*',
        ]);
        // file-bridge-v94-2-csrf:end
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        
        // DocumentArchive friendly database exceptions v49:start
        $exceptions->render(function (\Throwable $exception, \Illuminate\Http\Request $request) {
            $current = $exception;

            while ($current) {
                $message = $current->getMessage();
                $isDatabaseConnectionError = str_contains($message, 'SQLSTATE[HY000] [2002]')
                    || str_contains($message, 'No connection could be made because the target machine actively refused it')
                    || str_contains($message, 'Connection refused')
                    || str_contains($message, 'php_network_getaddresses')
                    || str_contains($message, 'getaddrinfo failed')
                    || str_contains($message, 'SQLSTATE[HY000] [1049]')
                    || str_contains($message, 'Unknown database')
                    || str_contains($message, 'SQLSTATE[HY000] [2006]')
                    || str_contains($message, 'MySQL server has gone away');

                if ($isDatabaseConnectionError) {
                    $headers = [
                        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                        'Pragma' => 'no-cache',
                        'Expires' => 'Mon, 01 Jan 1990 00:00:00 GMT',
                    ];

                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json([
                            'message' => 'تعذر الاتصال بقاعدة البيانات. يرجى التأكد من تشغيل MySQL ثم تحديث الصفحة.',
                            'status' => 'database_unavailable',
                        ], 503, $headers);
                    }

                    return response()->view('errors.database-connection', [
                        'appName' => config('app.name', 'DocumentArchive'),
                        'databaseHost' => config('database.connections.' . config('database.default') . '.host', '127.0.0.1'),
                        'databasePort' => config('database.connections.' . config('database.default') . '.port', '3306'),
                        'databaseName' => config('database.connections.' . config('database.default') . '.database', 'document_archive'),
                    ], 503, $headers);
                }

                $current = $current->getPrevious();
            }

            return null;
        });
        // DocumentArchive friendly database exceptions v49:end
$exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->create();

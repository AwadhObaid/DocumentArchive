<?php

$root = dirname(__DIR__);

function v49_write_file(string $path, string $content): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $content);
    echo "[OK] Wrote: {$path}" . PHP_EOL;
}

function v49_insert_exception_renderer(string $bootstrapPath): void
{
    if (! is_file($bootstrapPath)) {
        echo "[FAIL] Missing bootstrap/app.php: {$bootstrapPath}" . PHP_EOL;
        exit(1);
    }

    $content = file_get_contents($bootstrapPath);

    $renderer = <<<'PHP'
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
PHP;

    if (str_contains($content, 'DocumentArchive friendly database exceptions v49:start')) {
        echo "[OK] bootstrap/app.php already contains V49 exception renderer." . PHP_EOL;
        return;
    }

    // Remove previous V49 block if partially repeated.
    $content = preg_replace('/\s*\/\/ DocumentArchive friendly database exceptions v49:start.*?\/\/ DocumentArchive friendly database exceptions v49:end\s*/s', "\n", $content) ?? $content;

    $pattern = '/(->withExceptions\s*\(\s*function\s*\([^)]*\$exceptions[^)]*\)\s*(?::\s*void)?\s*\{\s*)/s';
    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, '$1' . "\n" . $renderer . "\n", $content, 1);
        file_put_contents($bootstrapPath, $content);
        echo "[OK] Inserted V49 exception renderer into existing withExceptions block." . PHP_EOL;
        return;
    }

    if (str_contains($content, '->create();')) {
        $replacement = "->withExceptions(function (\\Illuminate\\Foundation\\Configuration\\Exceptions \\$exceptions): void {\n" . $renderer . "\n    })\n    ->create();";
        $content = str_replace('->create();', $replacement, $content);
        file_put_contents($bootstrapPath, $content);
        echo "[OK] Added withExceptions block with V49 renderer before create()." . PHP_EOL;
        return;
    }

    echo "[FAIL] Could not locate withExceptions block or ->create() in bootstrap/app.php." . PHP_EOL;
    exit(1);
}

$middlewareContent = <<<'PHP'
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
PHP;

$viewContent = <<<'BLADE'
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>تعذر الاتصال بقاعدة البيانات</title>
    <style>
        :root { color-scheme: light dark; --bg:#f3f6fb; --card:#fff; --text:#0f172a; --muted:#64748b; --border:#dbe4f0; --primary:#2563eb; --warning-bg:#fff7ed; --warning-border:#fed7aa; --warning-text:#9a3412; --shadow:0 24px 70px rgba(15,23,42,.12); }
        @media (prefers-color-scheme: dark) { :root { --bg:#0b1220; --card:#111827; --text:#e5e7eb; --muted:#9ca3af; --border:#263244; --primary:#60a5fa; --warning-bg:rgba(251,146,60,.12); --warning-border:rgba(251,146,60,.35); --warning-text:#fed7aa; --shadow:0 24px 70px rgba(0,0,0,.35); } }
        *{box-sizing:border-box} body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(circle at top right,rgba(37,99,235,.14),transparent 32rem),radial-gradient(circle at bottom left,rgba(14,165,233,.10),transparent 28rem),var(--bg);color:var(--text);font-family:Tahoma,Arial,sans-serif;padding:24px}.db-error-card{width:min(760px,100%);background:var(--card);border:1px solid var(--border);border-radius:26px;box-shadow:var(--shadow);padding:30px}.db-error-head{display:flex;gap:18px;align-items:center;margin-bottom:18px}.db-error-icon{width:64px;height:64px;display:grid;place-items:center;border-radius:20px;background:rgba(37,99,235,.12);color:var(--primary);font-size:32px;flex:0 0 auto}h1{margin:0 0 8px;font-size:24px;line-height:1.45}p{margin:0;color:var(--muted);line-height:1.9;font-size:15px}.db-error-warning{margin-top:20px;padding:16px 18px;border-radius:18px;background:var(--warning-bg);border:1px solid var(--warning-border);color:var(--warning-text);line-height:1.9}.db-error-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:18px}.db-error-info{border:1px solid var(--border);border-radius:16px;padding:12px;min-width:0}.db-error-info span{display:block;color:var(--muted);font-size:12px;margin-bottom:6px}.db-error-info strong{display:block;direction:ltr;text-align:right;overflow-wrap:anywhere;font-size:14px}.db-error-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.db-error-actions button,.db-error-actions a{border:0;border-radius:14px;padding:11px 16px;font:inherit;cursor:pointer;text-decoration:none}.primary{background:var(--primary);color:#fff}.secondary{background:transparent;color:var(--text);border:1px solid var(--border)!important}code{direction:ltr;display:inline-block;background:rgba(148,163,184,.16);border-radius:8px;padding:2px 7px}@media(max-width:680px){.db-error-card{padding:22px}.db-error-head{align-items:flex-start}.db-error-grid{grid-template-columns:1fr}h1{font-size:20px}}
    </style>
</head>
<body>
<main class="db-error-card" role="main" aria-labelledby="dbErrorTitle">
    <div class="db-error-head"><div class="db-error-icon">🗄️</div><div><h1 id="dbErrorTitle">تعذر الاتصال بقاعدة البيانات</h1><p>لا يمكن للنظام الوصول إلى قاعدة البيانات حاليًا. غالبًا خدمة MySQL متوقفة أو المنفذ غير صحيح.</p></div></div>
    <div class="db-error-warning">يرجى تشغيل <strong>Laragon</strong> ثم الضغط على <strong>Start All</strong> أو تشغيل خدمة <strong>MySQL</strong>، وبعدها اضغط زر تحديث الصفحة.</div>
    <section class="db-error-grid" aria-label="بيانات الاتصال الحالية"><div class="db-error-info"><span>الخادم</span><strong>{{ $databaseHost ?? '127.0.0.1' }}</strong></div><div class="db-error-info"><span>المنفذ</span><strong>{{ $databasePort ?? '3306' }}</strong></div><div class="db-error-info"><span>قاعدة البيانات</span><strong>{{ $databaseName ?? 'document_archive' }}</strong></div></section>
    <div class="db-error-warning">إذا كانت MySQL تعمل على منفذ مختلف مثل <code>3307</code>، عدّل قيمة <code>DB_PORT</code> في ملف <code>.env</code> ثم نفّذ <code>php artisan config:clear</code>.</div>
    <div class="db-error-actions"><button class="primary" type="button" onclick="window.location.reload()">تحديث الصفحة</button><a class="secondary" href="/login">العودة إلى تسجيل الدخول</a></div>
</main>
</body>
</html>
BLADE;

v49_write_file($root . '/app/Http/Middleware/FriendlyDatabaseConnectionErrors.php', $middlewareContent);
v49_write_file($root . '/resources/views/errors/database-connection.blade.php', $viewContent);
v49_insert_exception_renderer($root . '/bootstrap/app.php');

echo PHP_EOL . 'Friendly database error fix V49 applied.' . PHP_EOL;

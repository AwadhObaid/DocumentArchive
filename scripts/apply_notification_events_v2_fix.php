<?php
/**
 * DocumentArchive - Notification Events V2 Fix
 * Creates observers, middleware, migration, notification service/model, and registers them safely.
 */

function root_path(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 8; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
    throw new RuntimeException('تعذر تحديد جذر مشروع Laravel. شغّل السكربت من داخل مجلد المشروع الذي يحتوي ملف artisan.');
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("تعذر إنشاء المجلد: {$dir}");
    }
}

function write_file(string $path, string $content): void
{
    ensure_dir(dirname($path));
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("تعذر كتابة الملف: {$path}");
    }
}

function replace_file_once(string $path, callable $callback): void
{
    if (!is_file($path)) {
        return;
    }
    $old = file_get_contents($path);
    $new = $callback($old);
    if ($new !== $old) {
        file_put_contents($path, $new);
    }
}

function ensure_use(string $content, string $useLine): string
{
    if (str_contains($content, $useLine)) {
        return $content;
    }
    $pos = strpos($content, "\nclass ");
    if ($pos === false) {
        return $content . "\n" . $useLine . "\n";
    }
    return substr($content, 0, $pos) . "\n" . $useLine . substr($content, $pos);
}

$root = root_path();

$observerDocument = <<<'PHPDOC'
<?php

namespace App\Observers;

use App\Models\Document;
use App\Services\SystemNotificationService;
use Illuminate\Support\Facades\Auth;

class DocumentObserver
{
    public function created(Document $document): void
    {
        SystemNotificationService::notifyAdmins(
            'تم إنشاء كتاب جديد',
            'تم إنشاء الكتاب رقم ' . $this->ref($document) . ' بواسطة ' . $this->actorName() . '.',
            'document_created',
            $this->documentUrl($document),
            ['document_id' => $document->id, 'reference_number' => $this->ref($document)]
        );
    }

    public function updated(Document $document): void
    {
        $changed = array_diff(array_keys($document->getChanges()), ['updated_at']);
        if (empty($changed)) {
            return;
        }

        SystemNotificationService::notifyAdmins(
            'تم تعديل كتاب',
            'تم تعديل بيانات الكتاب رقم ' . $this->ref($document) . ' بواسطة ' . $this->actorName() . '.',
            'document_updated',
            $this->documentUrl($document),
            ['document_id' => $document->id, 'reference_number' => $this->ref($document), 'changed' => array_values($changed)]
        );
    }

    public function deleted(Document $document): void
    {
        SystemNotificationService::notifyAdmins(
            'تم حذف كتاب',
            'تم نقل الكتاب رقم ' . $this->ref($document) . ' إلى سلة المحذوفات بواسطة ' . $this->actorName() . '.',
            'document_deleted',
            $this->documentUrl($document),
            ['document_id' => $document->id, 'reference_number' => $this->ref($document)]
        );
    }

    public function restored(Document $document): void
    {
        SystemNotificationService::notifyAdmins(
            'تم استعادة كتاب',
            'تم استعادة الكتاب رقم ' . $this->ref($document) . ' بواسطة ' . $this->actorName() . '.',
            'document_restored',
            $this->documentUrl($document),
            ['document_id' => $document->id, 'reference_number' => $this->ref($document)]
        );
    }

    private function ref(Document $document): string
    {
        return (string) ($document->reference_number ?? $document->document_no ?? $document->id ?? 'غير محدد');
    }

    private function actorName(): string
    {
        return (string) (Auth::user()->name ?? Auth::user()->username ?? 'النظام');
    }

    private function documentUrl(Document $document): string
    {
        try {
            return route('documents.show', $document);
        } catch (\Throwable $e) {
            return url('/documents/' . $document->id);
        }
    }
}
PHPDOC;

$observerAttachment = <<<'PHPDOC'
<?php

namespace App\Observers;

use App\Models\DocumentAttachment;
use App\Services\SystemNotificationService;
use Illuminate\Support\Facades\Auth;

class DocumentAttachmentObserver
{
    public function created(DocumentAttachment $attachment): void
    {
        $document = $this->document($attachment);
        SystemNotificationService::notifyAdmins(
            'تم رفع مرفق جديد',
            'تم رفع مرفق جديد للكتاب رقم ' . $this->ref($document, $attachment) . ' بواسطة ' . $this->actorName() . '.',
            'attachment_uploaded',
            $this->documentUrl($document, $attachment),
            ['attachment_id' => $attachment->id, 'document_id' => $attachment->document_id]
        );
    }

    public function deleted(DocumentAttachment $attachment): void
    {
        $document = $this->document($attachment);
        SystemNotificationService::notifyAdmins(
            'تم حذف مرفق',
            'تم حذف مرفق من الكتاب رقم ' . $this->ref($document, $attachment) . ' بواسطة ' . $this->actorName() . '.',
            'attachment_deleted',
            $this->documentUrl($document, $attachment),
            ['attachment_id' => $attachment->id, 'document_id' => $attachment->document_id]
        );
    }

    private function document(DocumentAttachment $attachment): mixed
    {
        try {
            if (method_exists($attachment, 'document')) {
                return $attachment->document()->withTrashed()->first() ?? $attachment->document;
            }
        } catch (\Throwable $e) {
            // Ignore and use fallback below.
        }
        return $attachment->document ?? null;
    }

    private function ref(mixed $document, DocumentAttachment $attachment): string
    {
        return (string) ($document->reference_number ?? $document->document_no ?? $attachment->document_id ?? 'غير محدد');
    }

    private function actorName(): string
    {
        return (string) (Auth::user()->name ?? Auth::user()->username ?? 'النظام');
    }

    private function documentUrl(mixed $document, DocumentAttachment $attachment): string
    {
        try {
            if ($document && isset($document->id)) {
                return route('documents.show', $document);
            }
        } catch (\Throwable $e) {
            // Fallback below.
        }
        return url('/documents/' . ($attachment->document_id ?? ''));
    }
}
PHPDOC;

$model = <<<'PHPDOC'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'body',
        'url',
        'data',
        'read_at',
        'hidden_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'hidden_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getBodyTextAttribute(): string
    {
        return (string) ($this->body ?? $this->message ?? '');
    }
}
PHPDOC;

$service = <<<'PHPDOC'
<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemNotificationService
{
    public static function notifyAdmins(string $title, string $body, string $type = 'info', ?string $url = null, array $data = []): void
    {
        if (!self::tableReady()) {
            return;
        }

        $adminIds = self::adminUserIds();
        $currentUserId = Auth::id();

        if (empty($adminIds) && $currentUserId) {
            $adminIds = [$currentUserId];
        }

        foreach (array_unique($adminIds) as $userId) {
            self::createForUser((int) $userId, $title, $body, $type, $url, $data);
        }
    }

    public static function notifyCurrentUser(string $title, string $body, string $type = 'info', ?string $url = null, array $data = []): void
    {
        $userId = Auth::id();
        if (!$userId || !self::tableReady()) {
            return;
        }

        self::createForUser((int) $userId, $title, $body, $type, $url, $data);
    }

    public static function createForUser(int $userId, string $title, string $body, string $type = 'info', ?string $url = null, array $data = []): void
    {
        if (!$userId || !self::tableReady()) {
            return;
        }

        try {
            if (self::recentDuplicateExists($userId, $title, $body, $type)) {
                return;
            }

            $payload = [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'url' => $url,
                'data' => $data,
            ];

            if (Schema::hasColumn('system_notifications', 'body')) {
                $payload['body'] = $body;
            }
            if (Schema::hasColumn('system_notifications', 'message')) {
                $payload['message'] = $body;
            }

            SystemNotification::create($payload);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function tableReady(): bool
    {
        try {
            return Schema::hasTable('system_notifications')
                && Schema::hasColumn('system_notifications', 'user_id')
                && Schema::hasColumn('system_notifications', 'title');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function adminUserIds(): array
    {
        try {
            if (!Schema::hasTable('users')) {
                return [];
            }

            $query = User::query();
            if (Schema::hasColumn('users', 'is_active')) {
                $query->where('is_active', 1);
            }

            if (Schema::hasColumn('users', 'role')) {
                $query->where(function ($q) {
                    $q->where('role', 'admin')
                      ->orWhere('role', 'مدير النظام')
                      ->orWhere('role', 'like', '%admin%')
                      ->orWhere('role', 'like', '%مدير%');
                });
            }

            return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    private static function recentDuplicateExists(int $userId, string $title, string $body, string $type): bool
    {
        try {
            $query = DB::table('system_notifications')
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('title', $title)
                ->where('created_at', '>=', Carbon::now()->subMinutes(2));

            if (Schema::hasColumn('system_notifications', 'body')) {
                $query->where('body', $body);
            } elseif (Schema::hasColumn('system_notifications', 'message')) {
                $query->where('message', $body);
            }

            return $query->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
PHPDOC;

$middleware = <<<'PHPDOC'
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
PHPDOC;

$migration = <<<'PHPDOC'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_notifications')) {
            Schema::create('system_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type')->default('info')->index();
                $table->string('title');
                $table->text('message')->nullable();
                $table->text('body')->nullable();
                $table->string('url')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamp('hidden_at')->nullable()->index();
                $table->timestamps();

                $table->index(['user_id', 'read_at']);
                $table->index(['user_id', 'hidden_at']);
            });

            return;
        }

        Schema::table('system_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('system_notifications', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            if (!Schema::hasColumn('system_notifications', 'type')) {
                $table->string('type')->default('info')->index();
            }
            if (!Schema::hasColumn('system_notifications', 'title')) {
                $table->string('title')->nullable();
            }
            if (!Schema::hasColumn('system_notifications', 'message')) {
                $table->text('message')->nullable();
            }
            if (!Schema::hasColumn('system_notifications', 'body')) {
                $table->text('body')->nullable();
            }
            if (!Schema::hasColumn('system_notifications', 'url')) {
                $table->string('url')->nullable();
            }
            if (!Schema::hasColumn('system_notifications', 'data')) {
                $table->json('data')->nullable();
            }
            if (!Schema::hasColumn('system_notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->index();
            }
            if (!Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->index();
            }
            if (!Schema::hasColumn('system_notifications', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // لا نحذف الجدول عند الرجوع حتى لا نفقد الإشعارات المحفوظة.
    }
};
PHPDOC;

write_file($root . '/app/Observers/DocumentObserver.php', $observerDocument);
write_file($root . '/app/Observers/DocumentAttachmentObserver.php', $observerAttachment);
write_file($root . '/app/Models/SystemNotification.php', $model);
write_file($root . '/app/Services/SystemNotificationService.php', $service);
write_file($root . '/app/Http/Middleware/PersistImportantFlashNotifications.php', $middleware);
write_file($root . '/database/migrations/2026_06_28_000010_create_system_notifications_table_if_not_exists.php', $migration);

// Register observers in AppServiceProvider.
$appServiceProvider = $root . '/app/Providers/AppServiceProvider.php';
if (is_file($appServiceProvider)) {
    replace_file_once($appServiceProvider, function (string $content): string {
        $content = ensure_use($content, 'use App\\Models\\Document;');
        $content = ensure_use($content, 'use App\\Models\\DocumentAttachment;');
        $content = ensure_use($content, 'use App\\Observers\\DocumentObserver;');
        $content = ensure_use($content, 'use App\\Observers\\DocumentAttachmentObserver;');

        $observerLines = "        Document::observe(DocumentObserver::class);\n        DocumentAttachment::observe(DocumentAttachmentObserver::class);";
        if (str_contains($content, 'Document::observe(DocumentObserver::class)') && str_contains($content, 'DocumentAttachment::observe(DocumentAttachmentObserver::class)')) {
            return $content;
        }

        if (preg_match('/public function boot\s*\(\s*\)\s*:\s*void\s*\{/', $content, $m, PREG_OFFSET_CAPTURE)) {
            $insertAt = $m[0][1] + strlen($m[0][0]);
            return substr($content, 0, $insertAt) . "\n" . $observerLines . "\n" . substr($content, $insertAt);
        }

        if (preg_match('/public function boot\s*\(\s*\)\s*\{/', $content, $m, PREG_OFFSET_CAPTURE)) {
            $insertAt = $m[0][1] + strlen($m[0][0]);
            return substr($content, 0, $insertAt) . "\n" . $observerLines . "\n" . substr($content, $insertAt);
        }

        // Add boot method before final class closing brace.
        $pos = strrpos($content, '}');
        if ($pos !== false) {
            $method = "\n    public function boot(): void\n    {\n" . $observerLines . "\n    }\n";
            return substr($content, 0, $pos) . $method . substr($content, $pos);
        }

        return $content;
    });
}

// Register middleware in Laravel 11/12/13 bootstrap/app.php when possible.
$bootstrapApp = $root . '/bootstrap/app.php';
$middlewareClass = '\\App\\Http\\Middleware\\PersistImportantFlashNotifications::class';
if (is_file($bootstrapApp)) {
    replace_file_once($bootstrapApp, function (string $content) use ($middlewareClass): string {
        if (str_contains($content, 'PersistImportantFlashNotifications::class')) {
            return $content;
        }

        // Case: ->withMiddleware(function (Middleware $middleware) { ... })
        if (preg_match('/->withMiddleware\s*\(\s*function\s*\([^)]*\$middleware[^)]*\)\s*\{/', $content, $m, PREG_OFFSET_CAPTURE)) {
            $insertAt = $m[0][1] + strlen($m[0][0]);
            $line = "\n        $" . "middleware->web(append: [{$middlewareClass}]);\n";
            return substr($content, 0, $insertAt) . $line . substr($content, $insertAt);
        }

        // Case: create(...)->withRouting(...)->create(); Add withMiddleware before create.
        $needle = '->create();';
        $pos = strrpos($content, $needle);
        if ($pos !== false) {
            $block = "->withMiddleware(function (Illuminate\\Foundation\\Configuration\\Middleware $" . "middleware) {\n        $" . "middleware->web(append: [{$middlewareClass}]);\n    })\n    ";
            return substr($content, 0, $pos) . $block . substr($content, $pos);
        }

        return $content;
    });
}

// Fallback for older projects with Kernel.php.
$kernel = $root . '/app/Http/Kernel.php';
if (is_file($kernel)) {
    replace_file_once($kernel, function (string $content): string {
        if (str_contains($content, 'PersistImportantFlashNotifications::class')) {
            return $content;
        }

        $line = "        \\App\\Http\\Middleware\\PersistImportantFlashNotifications::class,\n";
        if (preg_match('/\'web\'\s*=>\s*\[/', $content, $m, PREG_OFFSET_CAPTURE)) {
            $insertAt = $m[0][1] + strlen($m[0][0]);
            return substr($content, 0, $insertAt) . "\n" . $line . substr($content, $insertAt);
        }
        return $content;
    });
}

echo "DONE: تم تطبيق إصلاح ربط الإشعارات V2 بنجاح.\n";
echo "NEXT: نفّذ php artisan migrate ثم composer dump-autoload ثم php scripts/check_notification_events_v2_fix.php\n";

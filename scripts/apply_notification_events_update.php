<?php
/**
 * DocumentArchive - Notification Events Update
 * Adds persistent notifications for documents, attachments, and backup-related flash messages.
 */

$root = realpath(__DIR__ . '/..');
if (!$root || !file_exists($root . DIRECTORY_SEPARATOR . 'artisan')) {
    fwrite(STDERR, "ERROR: تأكد أنك تفك الضغط داخل جذر مشروع Laravel حيث يوجد ملف artisan.\n");
    exit(1);
}

function normalize_path(string $path): string
{
    return str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
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
    if (file_exists($path)) {
        $backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'patch-backups' . DIRECTORY_SEPARATOR . 'notification-events-' . date('Ymd-His');
        ensure_dir($backupDir);
        $relative = str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $path);
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\', ':'], '_', $relative);
        @copy($path, $backupPath);
    }
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("تعذر كتابة الملف: {$path}");
    }
}

function patch_file_once(string $path, callable $patcher): bool
{
    if (!file_exists($path)) {
        return false;
    }
    $original = file_get_contents($path);
    $patched = $patcher($original);
    if ($patched !== $original) {
        write_file($path, $patched);
        return true;
    }
    return false;
}

$service = <<<'PHP'
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SystemNotificationService
{
    public static function tableName(): string
    {
        if (Schema::hasTable('system_notifications')) {
            return 'system_notifications';
        }

        // Fallback for projects that may have named the table differently later.
        if (Schema::hasTable('notifications_center')) {
            return 'notifications_center';
        }

        return 'system_notifications';
    }

    public static function notify(?int $userId, string $type, string $title, string $message, ?string $url = null, array $meta = []): void
    {
        try {
            $table = self::tableName();

            if (!Schema::hasTable($table)) {
                return;
            }

            $now = now();
            $type = self::normalizeType($type);
            $hash = sha1(implode('|', [
                (string) $userId,
                $type,
                trim($title),
                trim($message),
                (string) $url,
            ]));

            // Prevent duplicate notifications caused by repeated redirects or observers.
            $duplicate = DB::table($table)
                ->where('user_id', $userId)
                ->where('dedupe_key', $hash)
                ->where('created_at', '>=', $now->copy()->subMinutes(2))
                ->exists();

            if ($duplicate) {
                return;
            }

            DB::table($table)->insert([
                'user_id' => $userId,
                'type' => $type,
                'title' => mb_substr(trim($title), 0, 190),
                'message' => mb_substr(trim($message), 0, 1000),
                'url' => $url,
                'icon' => self::iconForType($type),
                'dedupe_key' => $hash,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'read_at' => null,
                'hidden_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            Log::warning('System notification was not created.', [
                'error' => $e->getMessage(),
                'title' => $title,
            ]);
        }
    }

    public static function toCurrentUser(string $type, string $title, string $message, ?string $url = null, array $meta = []): void
    {
        $user = Auth::user();
        if ($user) {
            self::notify((int) $user->id, $type, $title, $message, $url, $meta);
        }
    }

    public static function toAdmins(string $type, string $title, string $message, ?string $url = null, array $meta = []): void
    {
        try {
            if (!Schema::hasTable('users')) {
                return;
            }

            User::query()
                ->where(function ($query) {
                    $query->where('role', 'admin')
                        ->orWhere('role', 'مدير النظام')
                        ->orWhere('username', 'admin');
                })
                ->where(function ($query) {
                    if (Schema::hasColumn('users', 'is_active')) {
                        $query->where('is_active', 1);
                    }
                })
                ->select('id')
                ->chunkById(100, function ($admins) use ($type, $title, $message, $url, $meta) {
                    foreach ($admins as $admin) {
                        self::notify((int) $admin->id, $type, $title, $message, $url, $meta);
                    }
                });
        } catch (\Throwable $e) {
            Log::warning('Admin notifications were not created.', ['error' => $e->getMessage()]);
        }
    }

    public static function toCurrentUserAndAdmins(string $type, string $title, string $message, ?string $url = null, array $meta = []): void
    {
        self::toCurrentUser($type, $title, $message, $url, $meta);
        self::toAdmins($type, $title, $message, $url, $meta);
    }

    protected static function normalizeType(string $type): string
    {
        $allowed = ['success', 'error', 'warning', 'info'];
        return in_array($type, $allowed, true) ? $type : 'info';
    }

    protected static function iconForType(string $type): string
    {
        return match ($type) {
            'success' => '✅',
            'error' => '⛔',
            'warning' => '⚠️',
            default => '🔔',
        };
    }
}
PHP;

$documentObserver = <<<'PHP'
<?php

namespace App\Observers;

use App\Models\Document;
use App\Services\SystemNotificationService;
use Illuminate\Support\Facades\Auth;

class DocumentObserver
{
    public function created(Document $document): void
    {
        $reference = $document->reference_number ?? $document->document_number ?? $document->id;
        $subject = $document->subject ?? $document->title ?? 'بدون موضوع';
        $url = $this->documentUrl($document);

        SystemNotificationService::toCurrentUserAndAdmins(
            'success',
            'تم إنشاء كتاب جديد',
            "تم إنشاء الكتاب رقم {$reference} بعنوان: {$subject}.",
            $url,
            $this->meta($document, 'document.created')
        );
    }

    public function updated(Document $document): void
    {
        if (!$document->wasChanged()) {
            return;
        }

        $ignored = ['updated_at'];
        $changed = array_values(array_diff(array_keys($document->getChanges()), $ignored));
        if (empty($changed)) {
            return;
        }

        $reference = $document->reference_number ?? $document->document_number ?? $document->id;
        $url = $this->documentUrl($document);

        SystemNotificationService::toCurrentUserAndAdmins(
            'info',
            'تم تعديل كتاب',
            "تم تعديل بيانات الكتاب رقم {$reference}.",
            $url,
            $this->meta($document, 'document.updated', ['changed_fields' => $changed])
        );
    }

    public function deleted(Document $document): void
    {
        $reference = $document->reference_number ?? $document->document_number ?? $document->id;

        SystemNotificationService::toCurrentUserAndAdmins(
            'warning',
            'تم حذف كتاب',
            "تم نقل الكتاب رقم {$reference} إلى سلة المحذوفات.",
            route_exists('documents.trash') ? route('documents.trash') : null,
            $this->meta($document, 'document.deleted')
        );
    }

    public function restored(Document $document): void
    {
        $reference = $document->reference_number ?? $document->document_number ?? $document->id;
        $url = $this->documentUrl($document);

        SystemNotificationService::toCurrentUserAndAdmins(
            'success',
            'تم استعادة كتاب',
            "تم استعادة الكتاب رقم {$reference} من سلة المحذوفات.",
            $url,
            $this->meta($document, 'document.restored')
        );
    }

    protected function documentUrl(Document $document): ?string
    {
        try {
            return route_exists('documents.show') ? route('documents.show', $document) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function meta(Document $document, string $event, array $extra = []): array
    {
        return array_merge([
            'event' => $event,
            'document_id' => $document->id ?? null,
            'reference_number' => $document->reference_number ?? null,
            'actor_user_id' => Auth::id(),
        ], $extra);
    }
}

if (!function_exists('App\\Observers\\route_exists')) {
    function route_exists(string $name): bool
    {
        try {
            return app('router')->has($name);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
PHP;

$attachmentObserver = <<<'PHP'
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
        $reference = $document?->reference_number ?? $document?->document_number ?? $attachment->document_id ?? $attachment->id;
        $filename = $attachment->original_name ?? $attachment->file_name ?? $attachment->filename ?? 'مرفق';

        SystemNotificationService::toCurrentUserAndAdmins(
            'success',
            'تم رفع مرفق',
            "تم رفع المرفق {$filename} للكتاب رقم {$reference}.",
            $this->documentUrl($document),
            $this->meta($attachment, 'attachment.created')
        );
    }

    public function deleted(DocumentAttachment $attachment): void
    {
        $document = $this->document($attachment);
        $reference = $document?->reference_number ?? $document?->document_number ?? $attachment->document_id ?? $attachment->id;
        $filename = $attachment->original_name ?? $attachment->file_name ?? $attachment->filename ?? 'مرفق';

        SystemNotificationService::toCurrentUserAndAdmins(
            'warning',
            'تم حذف مرفق',
            "تم حذف المرفق {$filename} من الكتاب رقم {$reference}.",
            $this->documentUrl($document),
            $this->meta($attachment, 'attachment.deleted')
        );
    }

    protected function document(DocumentAttachment $attachment): ?object
    {
        try {
            if (method_exists($attachment, 'document')) {
                return $attachment->document()->withTrashed()->first() ?? $attachment->document;
            }
            return $attachment->document ?? null;
        } catch (\Throwable $e) {
            return $attachment->document ?? null;
        }
    }

    protected function documentUrl(?object $document): ?string
    {
        if (!$document) {
            return null;
        }

        try {
            return route_exists('documents.show') ? route('documents.show', $document) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function meta(DocumentAttachment $attachment, string $event): array
    {
        return [
            'event' => $event,
            'attachment_id' => $attachment->id ?? null,
            'document_id' => $attachment->document_id ?? null,
            'actor_user_id' => Auth::id(),
        ];
    }
}

if (!function_exists('App\\Observers\\route_exists')) {
    function route_exists(string $name): bool
    {
        try {
            return app('router')->has($name);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
PHP;

$middleware = <<<'PHP'
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

        try {
            if (!auth()->check() || !$request->hasSession()) {
                return $response;
            }

            $routeName = optional($request->route())->getName();
            $path = $request->path();

            // Avoid duplicating document/attachment notifications handled by observers.
            if (str_starts_with($path, 'documents') || str_starts_with($path, 'attachments')) {
                return $response;
            }

            $flashTypes = [
                'success' => 'success',
                'status' => 'success',
                'warning' => 'warning',
                'error' => 'error',
                'info' => 'info',
            ];

            foreach ($flashTypes as $sessionKey => $type) {
                $message = session($sessionKey);
                if (!$message || !is_string($message)) {
                    continue;
                }

                $title = $this->titleFor($type, $routeName, $path);
                $dedupeKey = 'persisted_notification_' . sha1($sessionKey . '|' . $message . '|' . $path);

                if (session($dedupeKey)) {
                    continue;
                }

                session([$dedupeKey => true]);

                SystemNotificationService::toCurrentUser(
                    $type,
                    $title,
                    $message,
                    url($path),
                    [
                        'event' => 'flash.persisted',
                        'route' => $routeName,
                        'path' => $path,
                    ]
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    protected function titleFor(string $type, ?string $routeName, string $path): string
    {
        if (str_contains($path, 'backups')) {
            return $type === 'error' ? 'تنبيه النسخ الاحتياطي' : 'إشعار النسخ الاحتياطي';
        }

        if (str_contains($path, 'reports')) {
            return 'إشعار التقارير';
        }

        if (str_contains($path, 'settings')) {
            return 'إشعار الإعدادات';
        }

        if (str_contains($path, 'users')) {
            return 'إشعار المستخدمين';
        }

        return match ($type) {
            'success' => 'تمت العملية بنجاح',
            'warning' => 'تنبيه من النظام',
            'error' => 'حدث خطأ',
            default => 'إشعار من النظام',
        };
    }
}
PHP;

$migration = <<<'PHP'
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
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 30)->default('info');
                $table->string('icon', 20)->nullable();
                $table->string('title');
                $table->text('message')->nullable();
                $table->string('url')->nullable();
                $table->string('dedupe_key', 80)->nullable()->index();
                $table->json('meta')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('hidden_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'read_at', 'hidden_at']);
                $table->index(['type', 'created_at']);
            });

            return;
        }

        Schema::table('system_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('system_notifications', 'icon')) {
                $table->string('icon', 20)->nullable()->after('type');
            }
            if (!Schema::hasColumn('system_notifications', 'dedupe_key')) {
                $table->string('dedupe_key', 80)->nullable()->index()->after('url');
            }
            if (!Schema::hasColumn('system_notifications', 'meta')) {
                $table->json('meta')->nullable()->after('dedupe_key');
            }
            if (!Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->after('read_at');
            }
        });
    }

    public function down(): void
    {
        // Do not drop the table automatically to preserve existing notifications.
    }
};
PHP;

write_file($root . '/app/Services/SystemNotificationService.php', $service);
write_file($root . '/app/Observers/DocumentObserver.php', $documentObserver);
write_file($root . '/app/Observers/DocumentAttachmentObserver.php', $attachmentObserver);
write_file($root . '/app/Http/Middleware/PersistImportantFlashNotifications.php', $middleware);
write_file($root . '/database/migrations/2026_06_28_000010_create_system_notifications_table_if_not_exists.php', $migration);

// Patch AppServiceProvider to register observers.
$appServiceProvider = $root . '/app/Providers/AppServiceProvider.php';
patch_file_once($appServiceProvider, function (string $content): string {
    $uses = [
        'use App\\Models\\Document;',
        'use App\\Models\\DocumentAttachment;',
        'use App\\Observers\\DocumentObserver;',
        'use App\\Observers\\DocumentAttachmentObserver;',
    ];

    foreach ($uses as $use) {
        if (!str_contains($content, $use)) {
            $content = preg_replace('/namespace\s+App\\Providers;\s*/', "namespace App\\Providers;\n\n{$use}\n", $content, 1);
        }
    }

    $observerBlock = <<<'PHP'
        if (class_exists(Document::class) && class_exists(DocumentObserver::class)) {
            Document::observe(DocumentObserver::class);
        }

        if (class_exists(DocumentAttachment::class) && class_exists(DocumentAttachmentObserver::class)) {
            DocumentAttachment::observe(DocumentAttachmentObserver::class);
        }
PHP;

    if (str_contains($content, 'Document::observe(DocumentObserver::class)')) {
        return $content;
    }

    if (preg_match('/public\s+function\s+boot\s*\([^)]*\)\s*:\s*void\s*\{/', $content, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($content, 0, $pos) . "\n" . $observerBlock . "\n" . substr($content, $pos);
    }

    if (preg_match('/class\s+AppServiceProvider\s+extends\s+ServiceProvider\s*\{/', $content, $m, PREG_OFFSET_CAPTURE)) {
        $insert = <<<'PHP'

    public function boot(): void
    {
        if (class_exists(Document::class) && class_exists(DocumentObserver::class)) {
            Document::observe(DocumentObserver::class);
        }

        if (class_exists(DocumentAttachment::class) && class_exists(DocumentAttachmentObserver::class)) {
            DocumentAttachment::observe(DocumentAttachmentObserver::class);
        }
    }
PHP;
        $lastBrace = strrpos($content, '}');
        if ($lastBrace !== false) {
            return substr($content, 0, $lastBrace) . $insert . "\n" . substr($content, $lastBrace);
        }
    }

    return $content;
});

// Register middleware in bootstrap/app.php if possible (Laravel 11/12/13 style).
$bootstrapApp = $root . '/bootstrap/app.php';
patch_file_once($bootstrapApp, function (string $content): string {
    if (str_contains($content, 'PersistImportantFlashNotifications::class')) {
        return $content;
    }

    if (!str_contains($content, 'use App\\Http\\Middleware\\PersistImportantFlashNotifications;')) {
        $content = preg_replace('/<\?php\s*/', "<?php\n\nuse App\\Http\\Middleware\\PersistImportantFlashNotifications;\n", $content, 1);
    }

    // Add to web group if withMiddleware closure exists.
    $pattern = '/->withMiddleware\(function \(Middleware \$middleware\) \{(.*?)\}\)/s';
    if (preg_match($pattern, $content, $m)) {
        $body = $m[1];
        $newBody = $body . "\n        $" . "middleware->web(append: [PersistImportantFlashNotifications::class]);\n    ";
        return preg_replace($pattern, '->withMiddleware(function (Middleware $middleware) {' . $newBody . '})', $content, 1);
    }

    return $content;
});

// Laravel 10 style fallback: app/Http/Kernel.php
$kernel = $root . '/app/Http/Kernel.php';
patch_file_once($kernel, function (string $content): string {
    if (str_contains($content, 'PersistImportantFlashNotifications::class')) {
        return $content;
    }

    if (!str_contains($content, 'use App\\Http\\Middleware\\PersistImportantFlashNotifications;')) {
        $content = preg_replace('/namespace\s+App\\Http;\s*/', "namespace App\\Http;\n\nuse App\\Http\\Middleware\\PersistImportantFlashNotifications;\n", $content, 1);
    }

    $needle = "\\Illuminate\\Routing\\Middleware\\SubstituteBindings::class,";
    if (str_contains($content, $needle)) {
        return str_replace($needle, $needle . "\n            PersistImportantFlashNotifications::class,", $content);
    }

    return $content;
});

echo "OK: تم تركيب ربط الإشعارات بعمليات الكتب والمرفقات ورسائل النظام المهمة.\n";
echo "NEXT: شغّل php artisan migrate ثم check_notification_events_update.php\n";
PHP
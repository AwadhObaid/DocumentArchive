<?php
/**
 * DocumentArchive - Notification Events V3 Compatibility Fix
 * Fixes: Call to undefined method SystemNotificationService::syncForCurrentUser()
 * Unifies the notification-center service/model with notification-events observers.
 */

declare(strict_types=1);

function project_root(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 10; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
    throw new RuntimeException('تعذر تحديد جذر مشروع Laravel. شغّل السكربت من داخل مجلد المشروع الذي يحتوي ملف artisan.');
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException('تعذر إنشاء المجلد: ' . $dir);
    }
}

function write_file(string $path, string $content): void
{
    ensure_dir(dirname($path));
    if (is_file($path) && file_get_contents($path) === $content) {
        echo "UNCHANGED: {$path}\n";
        return;
    }
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('تعذر كتابة الملف: ' . $path);
    }
    echo "WROTE: {$path}\n";
}

function patch_file(string $path, callable $callback): void
{
    if (!is_file($path)) {
        echo "SKIP: file not found {$path}\n";
        return;
    }
    $old = file_get_contents($path);
    $new = $callback($old);
    if ($new !== $old) {
        file_put_contents($path, $new);
        echo "UPDATED: {$path}\n";
    } else {
        echo "UNCHANGED: {$path}\n";
    }
}

function ensure_use_line(string $content, string $useLine): string
{
    if (str_contains($content, $useLine)) {
        return $content;
    }
    if (preg_match_all('/^use\s+[^;]+;\s*$/m', $content, $matches, PREG_OFFSET_CAPTURE) && !empty($matches[0])) {
        $last = end($matches[0]);
        $pos = $last[1] + strlen($last[0]);
        return substr($content, 0, $pos) . "\n" . $useLine . substr($content, $pos);
    }
    $classPos = strpos($content, "\nclass ");
    if ($classPos !== false) {
        return substr($content, 0, $classPos) . "\n" . $useLine . substr($content, $classPos);
    }
    return $content . "\n" . $useLine . "\n";
}

$root = project_root();
echo "Project root: {$root}\n";

$model = <<<'PHP_MODEL'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'unique_key',
        'type',
        'title',
        'message',
        'body',
        'url',
        'link',
        'source',
        'payload',
        'data',
        'read_at',
        'dismissed_at',
        'hidden_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'data' => 'array',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'hidden_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisible($query)
    {
        return $query
            ->where(function ($q) {
                $q->whereNull('dismissed_at')->orWhereDoesntHaveColumnFallback('dismissed_at');
            })
            ->where(function ($q) {
                $q->whereNull('hidden_at')->orWhereDoesntHaveColumnFallback('hidden_at');
            });
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at')->visible();
    }

    public function getBodyTextAttribute(): string
    {
        return (string) ($this->body ?? $this->message ?? '');
    }

    public function getBodyAttribute($value): ?string
    {
        return $value ?? ($this->attributes['message'] ?? null);
    }

    public function getLinkAttribute($value): ?string
    {
        return $value ?? ($this->attributes['url'] ?? null);
    }
}
PHP_MODEL;

// The model scope above uses a macro-like fallback name that may not exist; replace it with schema-free simple scopes.
$model = str_replace(<<<'BAD'
    public function scopeVisible($query)
    {
        return $query
            ->where(function ($q) {
                $q->whereNull('dismissed_at')->orWhereDoesntHaveColumnFallback('dismissed_at');
            })
            ->where(function ($q) {
                $q->whereNull('hidden_at')->orWhereDoesntHaveColumnFallback('hidden_at');
            });
    }
BAD, <<<'GOOD'
    public function scopeVisible($query)
    {
        $table = $this->getTable();

        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'dismissed_at')) {
                $query->whereNull('dismissed_at');
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'hidden_at')) {
                $query->whereNull('hidden_at');
            }
        } catch (\Throwable $e) {
            // If schema inspection fails, return the base query instead of breaking the page.
        }

        return $query;
    }
GOOD, $model);

$service = <<<'PHP_SERVICE'
<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemNotificationService
{
    /**
     * Compatible creator for both:
     * - Notification Center calls: createForUser(user: $user, ..., uniqueKey: ...)
     * - Event Observer calls: createForUser($userId, $title, $body, $type, $url, $data)
     */
    public static function createForUser(User|int $user, string $title, ?string $body = null, string $type = 'info', ?string $link = null, mixed $uniqueKey = null, string $source = 'manual', array $payload = []): ?SystemNotification
    {
        if (! self::tableReady()) {
            return null;
        }

        $userId = $user instanceof User ? (int) $user->id : (int) $user;
        if ($userId <= 0) {
            return null;
        }

        // Old event integration passed the payload array as the 6th argument.
        if (is_array($uniqueKey)) {
            $payload = $uniqueKey;
            $uniqueKey = null;
        }

        try {
            if ($uniqueKey && self::hasColumn('unique_key')) {
                $existing = SystemNotification::query()
                    ->where('user_id', $userId)
                    ->where('unique_key', (string) $uniqueKey)
                    ->first();

                $data = self::payloadForCreate($userId, $title, $body, $type, $link, (string) $uniqueKey, $source, $payload);

                if ($existing) {
                    $existing->fill($data);
                    if (self::hasColumn('read_at') && $existing->isDirty(['title', 'body', 'message', 'type', 'link', 'url', 'payload', 'data'])) {
                        $existing->read_at = null;
                    }
                    if (self::hasColumn('dismissed_at')) {
                        $existing->dismissed_at = null;
                    }
                    if (self::hasColumn('hidden_at')) {
                        $existing->hidden_at = null;
                    }
                    $existing->save();
                    return $existing;
                }

                return SystemNotification::create($data);
            }

            if (self::recentDuplicateExists($userId, $title, $body, $type)) {
                return null;
            }

            return SystemNotification::create(self::payloadForCreate($userId, $title, $body, $type, $link, null, $source, $payload));
        } catch (Throwable $exception) {
            report($exception);
            return null;
        }
    }

    public static function notifyAdmins(string $title, string $body, string $type = 'info', ?string $url = null, array $data = []): void
    {
        if (! self::tableReady()) {
            return;
        }

        $adminIds = self::adminUserIds();
        if (empty($adminIds) && Auth::id()) {
            $adminIds = [(int) Auth::id()];
        }

        foreach (array_unique($adminIds) as $userId) {
            self::createForUser((int) $userId, $title, $body, $type, $url, null, 'event', $data);
        }
    }

    public static function notifyCurrentUser(string $title, string $body, string $type = 'info', ?string $url = null, array $data = []): void
    {
        $userId = Auth::id();
        if (! $userId || ! self::tableReady()) {
            return;
        }

        self::createForUser((int) $userId, $title, $body, $type, $url, null, 'flash', $data);
    }

    public static function syncForCurrentUser(?User $user): void
    {
        if (! $user || ! self::tableReady()) {
            return;
        }

        if (! self::isAdmin($user)) {
            return;
        }

        try {
            $activeKeys = [];

            foreach (self::buildSystemAlerts() as $alert) {
                $key = 'system:' . $alert['key'];
                $activeKeys[] = $key;

                self::createForUser(
                    user: $user,
                    title: $alert['title'],
                    body: $alert['body'] ?? null,
                    type: $alert['type'] ?? 'warning',
                    link: $alert['link'] ?? null,
                    uniqueKey: $key,
                    source: 'system_monitor',
                    payload: $alert['payload'] ?? []
                );
            }

            if (self::hasColumn('source') && self::hasColumn('unique_key')) {
                $query = SystemNotification::query()
                    ->where('user_id', $user->id)
                    ->where('source', 'system_monitor');

                if (! empty($activeKeys)) {
                    $query->whereNotIn('unique_key', $activeKeys);
                }

                $updates = [];
                if (self::hasColumn('dismissed_at')) {
                    $updates['dismissed_at'] = now();
                }
                if (self::hasColumn('hidden_at')) {
                    $updates['hidden_at'] = now();
                }
                if (self::hasColumn('read_at')) {
                    $updates['read_at'] = now();
                }

                if (! empty($updates)) {
                    $query->update($updates);
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public static function buildSystemAlerts(): array
    {
        $alerts = [];

        if (Schema::hasTable('documents') && Schema::hasTable('document_attachments')) {
            $withoutAttachments = DB::table('documents')
                ->whereNull('documents.deleted_at')
                ->leftJoin('document_attachments', 'documents.id', '=', 'document_attachments.document_id')
                ->whereNull('document_attachments.id')
                ->count();

            if ($withoutAttachments > 0) {
                $alerts[] = [
                    'key' => 'documents_without_attachments',
                    'type' => 'warning',
                    'title' => 'كتب بدون مرفقات',
                    'body' => "يوجد {$withoutAttachments} كتاب لم يتم رفع مرفقات لها بعد.",
                    'link' => url('/data-quality'),
                    'payload' => ['count' => $withoutAttachments],
                ];
            }
        }

        if (Schema::hasTable('documents')) {
            foreach ([
                'main_policy_number' => ['key' => 'duplicate_main_policy', 'title' => 'بوالص رئيسية مكررة'],
                'sub_policy_number' => ['key' => 'duplicate_sub_policy', 'title' => 'بوالص فرعية مكررة'],
            ] as $column => $meta) {
                if (! Schema::hasColumn('documents', $column)) {
                    continue;
                }

                $duplicates = DB::table('documents')
                    ->select($column)
                    ->whereNull('deleted_at')
                    ->whereNotNull($column)
                    ->where($column, '<>', '')
                    ->groupBy($column)
                    ->havingRaw('COUNT(*) > 1')
                    ->count();

                if ($duplicates > 0) {
                    $alerts[] = [
                        'key' => $meta['key'],
                        'type' => 'warning',
                        'title' => $meta['title'],
                        'body' => "يوجد {$duplicates} رقم بوليصة مكرر يحتاج مراجعة.",
                        'link' => url('/data-quality'),
                        'payload' => ['count' => $duplicates],
                    ];
                }
            }

            if (Schema::hasColumn('documents', 'deleted_at')) {
                $trashed = DB::table('documents')->whereNotNull('deleted_at')->count();
                if ($trashed > 0) {
                    $alerts[] = [
                        'key' => 'trashed_documents',
                        'type' => 'info',
                        'title' => 'كتب في سلة المحذوفات',
                        'body' => "يوجد {$trashed} كتاب في سلة المحذوفات.",
                        'link' => url('/documents/trash'),
                        'payload' => ['count' => $trashed],
                    ];
                }
            }
        }

        $backupDir = storage_path('app/private/backups');
        if (! is_dir($backupDir)) {
            $alerts[] = [
                'key' => 'backup_folder_missing',
                'type' => 'danger',
                'title' => 'مجلد النسخ الاحتياطي غير موجود',
                'body' => 'يرجى مراجعة صفحة النسخ الاحتياطي وإنشاء نسخة جديدة.',
                'link' => url('/backups'),
            ];
        } else {
            $files = collect(File::glob($backupDir . DIRECTORY_SEPARATOR . '*.zip'))
                ->filter(fn ($file) => is_file($file));

            if ($files->isEmpty()) {
                $alerts[] = [
                    'key' => 'no_backup_found',
                    'type' => 'danger',
                    'title' => 'لا توجد نسخة احتياطية',
                    'body' => 'لم يتم العثور على أي نسخة احتياطية. يفضل إنشاء نسخة كاملة الآن.',
                    'link' => url('/backups'),
                ];
            } else {
                $latest = $files->sortByDesc(fn ($file) => filemtime($file))->first();
                $latestTime = Carbon::createFromTimestamp(filemtime($latest));
                if ($latestTime->lt(now()->subDays(7))) {
                    $alerts[] = [
                        'key' => 'old_backup_found',
                        'type' => 'warning',
                        'title' => 'آخر نسخة احتياطية قديمة',
                        'body' => 'آخر نسخة احتياطية أقدم من 7 أيام. يفضل إنشاء نسخة حديثة.',
                        'link' => url('/backups'),
                        'payload' => ['latest_backup_at' => $latestTime->toDateTimeString()],
                    ];
                }
            }
        }

        if (config('app.debug') === true) {
            $alerts[] = [
                'key' => 'app_debug_enabled',
                'type' => 'info',
                'title' => 'وضع التطوير مفعل',
                'body' => 'APP_DEBUG=true مناسب للتطوير فقط، ويجب جعله false عند التشغيل النهائي.',
                'link' => url('/system-health'),
            ];
        }

        return $alerts;
    }

    private static function payloadForCreate(int $userId, string $title, ?string $body, string $type, ?string $link, ?string $uniqueKey, string $source, array $payload): array
    {
        $data = [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
        ];

        if ($uniqueKey !== null && self::hasColumn('unique_key')) {
            $data['unique_key'] = $uniqueKey;
        }
        if (self::hasColumn('body')) {
            $data['body'] = $body;
        }
        if (self::hasColumn('message')) {
            $data['message'] = $body;
        }
        if (self::hasColumn('link')) {
            $data['link'] = $link;
        }
        if (self::hasColumn('url')) {
            $data['url'] = $link;
        }
        if (self::hasColumn('source')) {
            $data['source'] = $source;
        }
        if (self::hasColumn('payload')) {
            $data['payload'] = $payload;
        }
        if (self::hasColumn('data')) {
            $data['data'] = $payload;
        }
        if (self::hasColumn('dismissed_at')) {
            $data['dismissed_at'] = null;
        }
        if (self::hasColumn('hidden_at')) {
            $data['hidden_at'] = null;
        }

        return $data;
    }

    private static function tableReady(): bool
    {
        try {
            return Schema::hasTable('system_notifications')
                && Schema::hasColumn('system_notifications', 'user_id')
                && Schema::hasColumn('system_notifications', 'title');
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function hasColumn(string $column): bool
    {
        try {
            return Schema::hasColumn('system_notifications', $column);
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function adminUserIds(): array
    {
        try {
            if (! Schema::hasTable('users')) {
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
        } catch (Throwable $e) {
            report($e);
            return [];
        }
    }

    private static function recentDuplicateExists(int $userId, string $title, ?string $body, string $type): bool
    {
        try {
            $query = DB::table('system_notifications')
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('title', $title)
                ->where('created_at', '>=', now()->subMinutes(2));

            if (self::hasColumn('body')) {
                $query->where('body', $body);
            } elseif (self::hasColumn('message')) {
                $query->where('message', $body);
            }

            return $query->exists();
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function isAdmin(User $user): bool
    {
        $role = (string) ($user->role ?? '');

        return $role === 'admin'
            || str_contains($role, 'مدير')
            || (method_exists($user, 'hasPermission') && $user->hasPermission('backups.view'));
    }
}
PHP_SERVICE;

$migration = <<<'PHP_MIGRATION'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_notifications')) {
            Schema::create('system_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('unique_key')->nullable();
                $table->string('type')->default('info');
                $table->string('title');
                $table->text('message')->nullable();
                $table->text('body')->nullable();
                $table->string('url')->nullable();
                $table->string('link')->nullable();
                $table->string('source')->nullable();
                $table->json('payload')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('dismissed_at')->nullable();
                $table->timestamp('hidden_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('system_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('system_notifications', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('system_notifications', 'unique_key')) {
                $table->string('unique_key')->nullable()->index();
            }
            if (! Schema::hasColumn('system_notifications', 'type')) {
                $table->string('type')->default('info')->index();
            }
            if (! Schema::hasColumn('system_notifications', 'title')) {
                $table->string('title')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'message')) {
                $table->text('message')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'body')) {
                $table->text('body')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'url')) {
                $table->string('url')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'link')) {
                $table->string('link')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'source')) {
                $table->string('source')->nullable()->index();
            }
            if (! Schema::hasColumn('system_notifications', 'payload')) {
                $table->json('payload')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'data')) {
                $table->json('data')->nullable();
            }
            if (! Schema::hasColumn('system_notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->index();
            }
            if (! Schema::hasColumn('system_notifications', 'dismissed_at')) {
                $table->timestamp('dismissed_at')->nullable()->index();
            }
            if (! Schema::hasColumn('system_notifications', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->index();
            }
            if (! Schema::hasColumn('system_notifications', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // لا نحذف الجدول حتى لا نفقد الإشعارات المحفوظة.
    }
};
PHP_MIGRATION;

$check = <<<'PHP_CHECK'
<?php

declare(strict_types=1);

function project_root_check(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 10; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
    throw new RuntimeException('تعذر تحديد جذر مشروع Laravel.');
}

$root = project_root_check();
$errors = [];

$service = $root . '/app/Services/SystemNotificationService.php';
$model = $root . '/app/Models/SystemNotification.php';
$migration = $root . '/database/migrations/2026_06_28_000012_patch_system_notifications_compatibility.php';

foreach ([$service, $model, $migration] as $file) {
    if (! is_file($file)) {
        $errors[] = 'الملف غير موجود: ' . str_replace($root . '/', '', $file);
    }
}

if (is_file($service)) {
    $content = file_get_contents($service);
    foreach (['syncForCurrentUser', 'notifyAdmins', 'notifyCurrentUser', 'buildSystemAlerts', 'createForUser'] as $method) {
        if (! str_contains($content, 'function ' . $method)) {
            $errors[] = "الدالة غير موجودة في SystemNotificationService: {$method}";
        }
    }
    exec('php -l ' . escapeshellarg($service), $out, $code);
    if ($code !== 0) {
        $errors[] = 'يوجد خطأ syntax في SystemNotificationService.php: ' . implode(' ', $out);
    }
}

if (is_file($model)) {
    $content = file_get_contents($model);
    foreach (['scopeVisible', 'scopeUnread', 'getLinkAttribute', 'getBodyAttribute'] as $method) {
        if (! str_contains($content, 'function ' . $method)) {
            $errors[] = "الدالة غير موجودة في SystemNotification model: {$method}";
        }
    }
    exec('php -l ' . escapeshellarg($model), $out2, $code2);
    if ($code2 !== 0) {
        $errors[] = 'يوجد خطأ syntax في SystemNotification.php: ' . implode(' ', $out2);
    }
}

try {
    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    if (! Illuminate\Support\Facades\Schema::hasTable('system_notifications')) {
        $errors[] = 'جدول system_notifications غير موجود. نفّذ: php artisan migrate';
    } else {
        foreach (['user_id', 'type', 'title', 'body', 'link', 'url', 'message', 'read_at'] as $column) {
            if (! Illuminate\Support\Facades\Schema::hasColumn('system_notifications', $column)) {
                $errors[] = "العمود غير موجود في system_notifications: {$column}. نفّذ: php artisan migrate";
            }
        }
    }

    if (! method_exists(App\Services\SystemNotificationService::class, 'syncForCurrentUser')) {
        $errors[] = 'Laravel لا يرى الدالة syncForCurrentUser بعد التحميل.';
    }
} catch (Throwable $e) {
    $errors[] = 'تعذر فحص Laravel runtime: ' . $e->getMessage();
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح توافق الإشعارات V3.\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "OK: إصلاح توافق الإشعارات V3 مكتمل.\n";
PHP_CHECK;

write_file($root . '/app/Models/SystemNotification.php', $model);
write_file($root . '/app/Services/SystemNotificationService.php', $service);
write_file($root . '/database/migrations/2026_06_28_000012_patch_system_notifications_compatibility.php', $migration);
write_file($root . '/scripts/check_notification_events_v3_compat_fix.php', $check);

// لا نحتاج تعديل NotificationCenterController؛ الخدمة أصبحت تحتوي syncForCurrentUser وتدعم الواجهتين القديمة والجديدة.

echo "DONE: طبّق الآن: php artisan migrate ثم composer dump-autoload ثم php artisan optimize:clear ثم سكربت الفحص.\n";

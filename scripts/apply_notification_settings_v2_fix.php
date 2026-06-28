<?php
/**
 * DocumentArchive - Notification Settings V2 Fix
 * Fixes:
 * 1) Missing navigation/button for /notification-settings.
 * 2) Disabled event preferences not respected because events may be saved as success/flash notifications.
 */

declare(strict_types=1);

function project_root(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 12; $i++) {
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
    if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
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
    if (! is_file($path)) {
        echo "SKIP: {$path}\n";
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
    if (preg_match_all('/^use\s+[^;]+;\s*$/m', $content, $matches, PREG_OFFSET_CAPTURE) && ! empty($matches[0])) {
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

$preferenceModel = <<<'PHP_MODEL'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'key',
        'label',
        'category',
        'enabled',
        'applies_to',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('user_id');
    }
}
PHP_MODEL;

$systemNotificationModel = <<<'PHP_MODEL'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Throwable;

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
        try {
            if (Schema::hasColumn($this->getTable(), 'dismissed_at')) {
                $query->whereNull('dismissed_at');
            }
            if (Schema::hasColumn($this->getTable(), 'hidden_at')) {
                $query->whereNull('hidden_at');
            }
        } catch (Throwable $e) {
            // لا نكسر صفحة الإشعارات إذا تعذر فحص الأعمدة.
        }

        return $query;
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

$migrationPreferences = <<<'PHP_MIGRATION'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('key')->index();
                $table->string('label')->nullable();
                $table->string('category')->nullable()->index();
                $table->boolean('enabled')->default(true)->index();
                $table->string('applies_to')->default('global');
                $table->timestamps();
                $table->index(['user_id', 'key']);
            });
            return;
        }

        Schema::table('notification_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_preferences', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('notification_preferences', 'key')) {
                $table->string('key')->index();
            }
            if (! Schema::hasColumn('notification_preferences', 'label')) {
                $table->string('label')->nullable();
            }
            if (! Schema::hasColumn('notification_preferences', 'category')) {
                $table->string('category')->nullable()->index();
            }
            if (! Schema::hasColumn('notification_preferences', 'enabled')) {
                $table->boolean('enabled')->default(true)->index();
            }
            if (! Schema::hasColumn('notification_preferences', 'applies_to')) {
                $table->string('applies_to')->default('global');
            }
            if (! Schema::hasColumn('notification_preferences', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // لا نحذف تفضيلات الإشعارات عند الرجوع حتى لا تضيع إعدادات المدير.
    }
};
PHP_MIGRATION;

$controller = <<<'PHP_CONTROLLER'
<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $this->ensureCanManage($request);

        $types = SystemNotificationService::availableNotificationTypes();
        $preferences = NotificationPreference::query()
            ->global()
            ->get()
            ->keyBy('key');

        return view('notifications.settings', [
            'types' => $types,
            'preferences' => $preferences,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $types = SystemNotificationService::availableNotificationTypes();
        $enabled = (array) $request->input('enabled', []);

        foreach ($types as $category => $items) {
            foreach ($items as $key => $meta) {
                $preference = NotificationPreference::query()
                    ->whereNull('user_id')
                    ->where('key', $key)
                    ->first();

                if (! $preference) {
                    $preference = new NotificationPreference();
                    $preference->user_id = null;
                    $preference->key = $key;
                }

                $preference->label = $meta['label'] ?? $key;
                $preference->category = $category;
                $preference->enabled = array_key_exists($key, $enabled);
                $preference->applies_to = 'global';
                $preference->save();
            }
        }

        return back()->with('success', 'تم حفظ إعدادات الإشعارات بنجاح.');
    }

    private function ensureCanManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        $role = (string) ($user->role ?? '');
        $allowed = $role === 'admin'
            || str_contains($role, 'مدير')
            || (method_exists($user, 'hasPermission') && (
                $user->hasPermission('settings.manage')
                || $user->hasPermission('notifications.manage')
                || $user->hasPermission('backups.view')
            ));

        abort_unless($allowed, 403, 'ليست لديك صلاحية إدارة إعدادات الإشعارات.');
    }
}
PHP_CONTROLLER;

$settingsView = <<<'BLADE'
@extends('layouts.app')

@section('title', 'إعدادات الإشعارات')

@section('content')
<style>
    .notification-settings-page { direction: rtl; }
    .notification-settings-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; flex-wrap: wrap; }
    .notification-settings-title h1 { margin: 0 0 6px; font-size: 1.55rem; }
    .notification-settings-title p { margin: 0; color: var(--muted-color, #64748b); }
    .notification-settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); gap: 16px; }
    .notification-settings-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #e5e7eb); border-radius: 18px; padding: 18px; box-shadow: 0 10px 24px rgba(15, 23, 42, .06); }
    .notification-settings-card h2 { margin: 0 0 14px; font-size: 1.08rem; }
    .notification-option { display: flex; align-items: flex-start; gap: 10px; padding: 12px 0; border-top: 1px dashed var(--border-color, #e5e7eb); cursor: pointer; }
    .notification-option:first-of-type { border-top: 0; }
    .notification-option input { margin-top: 4px; width: 18px; height: 18px; }
    .notification-option strong { display: block; margin-bottom: 4px; }
    .notification-option span { display: block; color: var(--muted-color, #64748b); font-size: .9rem; line-height: 1.6; }
    .notification-settings-actions { position: sticky; bottom: 16px; display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; background: color-mix(in srgb, var(--body-bg, #f8fafc) 78%, transparent); backdrop-filter: blur(8px); padding: 12px; border-radius: 16px; }
    .notification-settings-actions .btn, .notification-settings-actions button, .notification-settings-header .btn { border: 0; border-radius: 12px; padding: 10px 16px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .notification-settings-actions button { background: #2563eb; color: #fff; }
    .notification-settings-actions .btn-secondary, .notification-settings-header .btn-secondary { background: #e5e7eb; color: #111827; }
    html.dark .notification-settings-card, body.dark .notification-settings-card, [data-theme="dark"] .notification-settings-card { background: #111827; border-color: #374151; color: #e5e7eb; }
    html.dark .notification-settings-actions, body.dark .notification-settings-actions, [data-theme="dark"] .notification-settings-actions { background: rgba(17, 24, 39, .82); }
    html.dark .notification-settings-actions .btn-secondary, body.dark .notification-settings-actions .btn-secondary, [data-theme="dark"] .notification-settings-actions .btn-secondary,
    html.dark .notification-settings-header .btn-secondary, body.dark .notification-settings-header .btn-secondary, [data-theme="dark"] .notification-settings-header .btn-secondary { background: #374151; color: #e5e7eb; }
    @media print { .notification-settings-actions, .notification-settings-header .btn { display: none !important; } }
</style>

<div class="notification-settings-page">
    <div class="notification-settings-header">
        <div class="notification-settings-title">
            <h1>⚙️ إعدادات الإشعارات</h1>
            <p>حدد أنواع الإشعارات التي تريد أن ينشئها النظام ويحفظها داخل مركز الإشعارات.</p>
        </div>
        <a href="{{ route('notifications.index') }}" class="btn btn-secondary">🔔 مركز الإشعارات</a>
    </div>

    <form method="POST" action="{{ route('notification-settings.update') }}">
        @csrf

        <div class="notification-settings-grid">
            @foreach($types as $category => $items)
                <section class="notification-settings-card">
                    <h2>
                        @switch($category)
                            @case('system') تنبيهات النظام @break
                            @case('events') أحداث الكتب والمرفقات @break
                            @case('flash') رسائل العمليات @break
                            @default {{ $category }}
                        @endswitch
                    </h2>

                    @foreach($items as $key => $meta)
                        @php
                            $pref = $preferences->get($key);
                            $checked = $pref ? (bool) $pref->enabled : true;
                        @endphp

                        <label class="notification-option">
                            <input type="checkbox" name="enabled[{{ $key }}]" value="1" @checked($checked)>
                            <div>
                                <strong>{{ $meta['label'] ?? $key }}</strong>
                                <span>{{ $meta['description'] ?? 'إشعار داخل مركز الإشعارات.' }}</span>
                            </div>
                        </label>
                    @endforeach
                </section>
            @endforeach
        </div>

        <div class="notification-settings-actions">
            <a href="{{ route('notifications.index') }}" class="btn btn-secondary">رجوع</a>
            <button type="submit">💾 حفظ إعدادات الإشعارات</button>
        </div>
    </form>
</div>
@endsection
BLADE;

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
    public static function availableNotificationTypes(): array
    {
        return [
            'system' => [
                'system.documents_without_attachments' => ['label' => 'كتب بدون مرفقات', 'description' => 'تنبيه عندما توجد كتب لم يتم رفع مرفقات لها بعد.'],
                'system.duplicate_main_policy' => ['label' => 'بوالص رئيسية مكررة', 'description' => 'تنبيه عند وجود أرقام بوالص رئيسية مكررة.'],
                'system.duplicate_sub_policy' => ['label' => 'بوالص فرعية مكررة', 'description' => 'تنبيه عند وجود أرقام بوالص فرعية مكررة.'],
                'system.trashed_documents' => ['label' => 'كتب في سلة المحذوفات', 'description' => 'تنبيه عند وجود كتب محذوفة حذفاً مؤقتاً.'],
                'system.no_backup_found' => ['label' => 'لا توجد نسخة احتياطية', 'description' => 'تنبيه إذا لم يجد النظام أي نسخة احتياطية.'],
                'system.old_backup_found' => ['label' => 'آخر نسخة احتياطية قديمة', 'description' => 'تنبيه إذا كانت آخر نسخة احتياطية أقدم من 7 أيام.'],
                'system.backup_folder_missing' => ['label' => 'مجلد النسخ الاحتياطي غير موجود', 'description' => 'تنبيه عند فقدان مجلد النسخ الاحتياطي.'],
                'system.app_debug_enabled' => ['label' => 'وضع التطوير APP_DEBUG', 'description' => 'تنبيه عند بقاء APP_DEBUG=true.'],
            ],
            'events' => [
                'events.document_created' => ['label' => 'إضافة كتاب جديد', 'description' => 'إشعار عند إنشاء كتاب جديد.'],
                'events.document_updated' => ['label' => 'تعديل كتاب', 'description' => 'إشعار عند تعديل بيانات كتاب.'],
                'events.document_deleted' => ['label' => 'حذف كتاب', 'description' => 'إشعار عند نقل كتاب إلى سلة المحذوفات.'],
                'events.document_restored' => ['label' => 'استعادة كتاب', 'description' => 'إشعار عند استعادة كتاب من سلة المحذوفات.'],
                'events.attachment_uploaded' => ['label' => 'رفع مرفق', 'description' => 'إشعار عند رفع مرفق جديد لكتاب.'],
                'events.attachment_deleted' => ['label' => 'حذف مرفق', 'description' => 'إشعار عند حذف مرفق من كتاب.'],
            ],
            'flash' => [
                'flash.success' => ['label' => 'رسائل النجاح العامة', 'description' => 'حفظ رسائل النجاح العامة داخل مركز الإشعارات. ملاحظة: رسائل الكتب التي يمكن تمييزها تخضع لإعداد أحداث الكتب.'],
                'flash.warning' => ['label' => 'رسائل التحذير', 'description' => 'حفظ رسائل التحذير المهمة.'],
                'flash.danger' => ['label' => 'رسائل الأخطاء', 'description' => 'حفظ رسائل الأخطاء المهمة.'],
                'flash.info' => ['label' => 'رسائل المعلومات', 'description' => 'حفظ رسائل المعلومات العامة.'],
                'validation' => ['label' => 'أخطاء النماذج', 'description' => 'إشعار عند وجود أخطاء تحقق في النماذج.'],
            ],
        ];
    }

    /**
     * Compatible creator for Notification Center, observers, and flash middleware.
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

        // Some older code passed payload as the 6th argument.
        if (is_array($uniqueKey)) {
            $payload = $uniqueKey;
            $uniqueKey = null;
        }

        $preferenceKey = self::preferenceKeyFrom($type, $source, $uniqueKey, $title, $body, $payload);
        if (! self::notificationEnabled($preferenceKey, $userId)) {
            return null;
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
        if (! $user || ! self::tableReady() || ! self::isAdmin($user)) {
            return;
        }

        try {
            $activeKeys = [];

            foreach (self::buildSystemAlerts() as $alert) {
                $preferenceKey = 'system.' . $alert['key'];
                if (! self::notificationEnabled($preferenceKey, (int) $user->id)) {
                    continue;
                }

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
                if (self::hasColumn('dismissed_at')) { $updates['dismissed_at'] = now(); }
                if (self::hasColumn('hidden_at')) { $updates['hidden_at'] = now(); }
                if (self::hasColumn('read_at')) { $updates['read_at'] = now(); }

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
                    'key' => 'documents_without_attachments', 'type' => 'warning',
                    'title' => 'كتب بدون مرفقات',
                    'body' => "يوجد {$withoutAttachments} كتاب لم يتم رفع مرفقات لها بعد.",
                    'link' => url('/data-quality'), 'payload' => ['count' => $withoutAttachments],
                ];
            }
        }

        if (Schema::hasTable('documents')) {
            foreach ([
                'main_policy_number' => ['key' => 'duplicate_main_policy', 'title' => 'بوالص رئيسية مكررة'],
                'sub_policy_number' => ['key' => 'duplicate_sub_policy', 'title' => 'بوالص فرعية مكررة'],
            ] as $column => $meta) {
                if (! Schema::hasColumn('documents', $column)) { continue; }
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
                        'key' => $meta['key'], 'type' => 'warning', 'title' => $meta['title'],
                        'body' => "يوجد {$duplicates} رقم بوليصة مكرر يحتاج مراجعة.",
                        'link' => url('/data-quality'), 'payload' => ['count' => $duplicates],
                    ];
                }
            }

            if (Schema::hasColumn('documents', 'deleted_at')) {
                $trashed = DB::table('documents')->whereNotNull('deleted_at')->count();
                if ($trashed > 0) {
                    $alerts[] = [
                        'key' => 'trashed_documents', 'type' => 'info', 'title' => 'كتب في سلة المحذوفات',
                        'body' => "يوجد {$trashed} كتاب في سلة المحذوفات.",
                        'link' => url('/documents/trash'), 'payload' => ['count' => $trashed],
                    ];
                }
            }
        }

        $backupDir = storage_path('app/private/backups');
        if (! is_dir($backupDir)) {
            $alerts[] = [
                'key' => 'backup_folder_missing', 'type' => 'danger', 'title' => 'مجلد النسخ الاحتياطي غير موجود',
                'body' => 'يرجى مراجعة صفحة النسخ الاحتياطي وإنشاء نسخة جديدة.', 'link' => url('/backups'),
            ];
        } else {
            $files = collect(File::glob($backupDir . DIRECTORY_SEPARATOR . '*.zip'))->filter(fn ($file) => is_file($file));
            if ($files->isEmpty()) {
                $alerts[] = [
                    'key' => 'no_backup_found', 'type' => 'danger', 'title' => 'لا توجد نسخة احتياطية',
                    'body' => 'لم يتم العثور على أي نسخة احتياطية. يفضل إنشاء نسخة كاملة الآن.', 'link' => url('/backups'),
                ];
            } else {
                $latest = $files->sortByDesc(fn ($file) => filemtime($file))->first();
                $latestTime = Carbon::createFromTimestamp(filemtime($latest));
                if ($latestTime->lt(now()->subDays(7))) {
                    $alerts[] = [
                        'key' => 'old_backup_found', 'type' => 'warning', 'title' => 'آخر نسخة احتياطية قديمة',
                        'body' => 'آخر نسخة احتياطية أقدم من 7 أيام. يفضل إنشاء نسخة حديثة.',
                        'link' => url('/backups'), 'payload' => ['latest_backup_at' => $latestTime->toDateTimeString()],
                    ];
                }
            }
        }

        if (config('app.debug') === true) {
            $alerts[] = [
                'key' => 'app_debug_enabled', 'type' => 'info', 'title' => 'وضع التطوير مفعل',
                'body' => 'APP_DEBUG=true مناسب للتطوير فقط، ويجب جعله false عند التشغيل النهائي.',
                'link' => url('/system-health'),
            ];
        }

        return $alerts;
    }

    public static function notificationEnabled(?string $key, ?int $userId = null): bool
    {
        if (! $key) { return true; }

        try {
            if (! Schema::hasTable('notification_preferences') || ! Schema::hasColumn('notification_preferences', 'key')) {
                return true;
            }

            if ($userId && Schema::hasColumn('notification_preferences', 'user_id')) {
                $userSpecific = DB::table('notification_preferences')
                    ->where('key', $key)
                    ->where('user_id', $userId)
                    ->orderByDesc('id')
                    ->value('enabled');
                if ($userSpecific !== null) { return (bool) $userSpecific; }
            }

            $global = DB::table('notification_preferences')
                ->where('key', $key)
                ->whereNull('user_id')
                ->orderByDesc('id')
                ->value('enabled');

            return $global === null ? true : (bool) $global;
        } catch (Throwable $e) {
            return true;
        }
    }

    private static function preferenceKeyFrom(string $type, string $source, mixed $uniqueKey, string $title, ?string $body, array $payload): ?string
    {
        $all = mb_strtolower(trim($title . ' ' . (string) $body), 'UTF-8');

        foreach (['notification_key', 'preference_key', 'event_key', 'key', 'event', 'action', 'source'] as $payloadKey) {
            $value = $payload[$payloadKey] ?? null;
            if (! is_string($value) || $value === '') { continue; }
            $normalized = str_replace(['document.', 'attachment.', 'documents.', 'attachments.'], ['', '', '', ''], $value);
            $normalized = str_replace(['-', ' '], '_', $normalized);
            if (str_starts_with($value, 'events.')) { return $value; }
            if (in_array($normalized, ['document_created', 'created', 'create_document', 'store_document'], true)) { return 'events.document_created'; }
            if (in_array($normalized, ['document_updated', 'updated', 'update_document'], true)) { return 'events.document_updated'; }
            if (in_array($normalized, ['document_deleted', 'deleted', 'delete_document', 'trashed'], true)) { return 'events.document_deleted'; }
            if (in_array($normalized, ['document_restored', 'restored', 'restore_document'], true)) { return 'events.document_restored'; }
            if (in_array($normalized, ['attachment_uploaded', 'uploaded', 'upload_attachment'], true)) { return 'events.attachment_uploaded'; }
            if (in_array($normalized, ['attachment_deleted', 'deleted_attachment', 'delete_attachment'], true)) { return 'events.attachment_deleted'; }
        }

        if (($payload['source'] ?? null) === 'validation' || str_contains($all, 'أخطاء في النموذج')) {
            return 'validation';
        }

        if ($source === 'system_monitor' && is_string($uniqueKey) && str_starts_with($uniqueKey, 'system:')) {
            return 'system.' . substr($uniqueKey, strlen('system:'));
        }

        // Exact event type support.
        $eventTypes = ['document_created', 'document_updated', 'document_deleted', 'document_restored', 'attachment_uploaded', 'attachment_deleted'];
        if (in_array($type, $eventTypes, true)) {
            return 'events.' . $type;
        }
        if (str_starts_with($type, 'events.')) {
            return $type;
        }

        // Infer document/attachment events even if they were saved as success/flash messages.
        if ((str_contains($all, 'كتاب') || str_contains($all, 'الكتاب')) && (str_contains($all, 'إنشاء') || str_contains($all, 'اضافة') || str_contains($all, 'إضافة') || str_contains($all, 'حفظ') || str_contains($all, 'جديد'))) {
            return 'events.document_created';
        }
        if ((str_contains($all, 'كتاب') || str_contains($all, 'الكتاب')) && (str_contains($all, 'تعديل') || str_contains($all, 'تحديث'))) {
            return 'events.document_updated';
        }
        if ((str_contains($all, 'كتاب') || str_contains($all, 'الكتاب')) && (str_contains($all, 'حذف') || str_contains($all, 'سلة'))) {
            return 'events.document_deleted';
        }
        if ((str_contains($all, 'كتاب') || str_contains($all, 'الكتاب')) && str_contains($all, 'استعادة')) {
            return 'events.document_restored';
        }
        if ((str_contains($all, 'مرفق') || str_contains($all, 'المرفق')) && (str_contains($all, 'رفع') || str_contains($all, 'إرفاق') || str_contains($all, 'اضافة') || str_contains($all, 'إضافة'))) {
            return 'events.attachment_uploaded';
        }
        if ((str_contains($all, 'مرفق') || str_contains($all, 'المرفق')) && str_contains($all, 'حذف')) {
            return 'events.attachment_deleted';
        }

        if ($source === 'flash' || in_array($type, ['success', 'warning', 'danger', 'error', 'info'], true)) {
            return match ($type) {
                'success' => 'flash.success',
                'warning' => 'flash.warning',
                'danger', 'error' => 'flash.danger',
                'info' => 'flash.info',
                default => null,
            };
        }

        return null;
    }

    private static function payloadForCreate(int $userId, string $title, ?string $body, string $type, ?string $link, ?string $uniqueKey, string $source, array $payload): array
    {
        $data = ['user_id' => $userId, 'type' => $type, 'title' => $title];
        if ($uniqueKey !== null && self::hasColumn('unique_key')) { $data['unique_key'] = $uniqueKey; }
        if (self::hasColumn('body')) { $data['body'] = $body; }
        if (self::hasColumn('message')) { $data['message'] = $body; }
        if (self::hasColumn('link')) { $data['link'] = $link; }
        if (self::hasColumn('url')) { $data['url'] = $link; }
        if (self::hasColumn('source')) { $data['source'] = $source; }
        if (self::hasColumn('payload')) { $data['payload'] = $payload; }
        if (self::hasColumn('data')) { $data['data'] = $payload; }
        if (self::hasColumn('dismissed_at')) { $data['dismissed_at'] = null; }
        if (self::hasColumn('hidden_at')) { $data['hidden_at'] = null; }
        return $data;
    }

    private static function tableReady(): bool
    {
        try { return Schema::hasTable('system_notifications') && Schema::hasColumn('system_notifications', 'user_id') && Schema::hasColumn('system_notifications', 'title'); }
        catch (Throwable $e) { return false; }
    }

    private static function hasColumn(string $column): bool
    {
        try { return Schema::hasColumn('system_notifications', $column); }
        catch (Throwable $e) { return false; }
    }

    private static function adminUserIds(): array
    {
        try {
            if (! Schema::hasTable('users')) { return []; }
            $query = User::query();
            if (Schema::hasColumn('users', 'is_active')) { $query->where('is_active', 1); }
            if (Schema::hasColumn('users', 'role')) {
                $query->where(function ($q) {
                    $q->where('role', 'admin')->orWhere('role', 'مدير النظام')->orWhere('role', 'like', '%admin%')->orWhere('role', 'like', '%مدير%');
                });
            }
            return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        } catch (Throwable $e) { report($e); return []; }
    }

    private static function recentDuplicateExists(int $userId, string $title, ?string $body, string $type): bool
    {
        try {
            $query = DB::table('system_notifications')
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('title', $title)
                ->where('created_at', '>=', now()->subMinutes(2));
            if (self::hasColumn('body')) { $query->where('body', $body); }
            elseif (self::hasColumn('message')) { $query->where('message', $body); }
            return $query->exists();
        } catch (Throwable $e) { return false; }
    }

    private static function isAdmin(User $user): bool
    {
        $role = (string) ($user->role ?? '');
        return $role === 'admin' || str_contains($role, 'مدير') || (method_exists($user, 'hasPermission') && $user->hasPermission('backups.view'));
    }
}
PHP_SERVICE;

$check = <<<'PHP_CHECK'
<?php

declare(strict_types=1);

function project_root_check(): string
{
    $dir = getcwd();
    for ($i = 0; $i < 12; $i++) {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'artisan')) { return $dir; }
        $parent = dirname($dir);
        if ($parent === $dir) { break; }
        $dir = $parent;
    }
    throw new RuntimeException('تعذر تحديد جذر مشروع Laravel.');
}

$root = project_root_check();
$errors = [];

$files = [
    'app/Models/NotificationPreference.php',
    'app/Models/SystemNotification.php',
    'app/Http/Controllers/NotificationSettingsController.php',
    'app/Services/SystemNotificationService.php',
    'resources/views/notifications/settings.blade.php',
    'database/migrations/2026_06_28_000014_create_notification_preferences_table_if_missing.php',
];

foreach ($files as $relative) {
    if (! is_file($root . '/' . $relative)) { $errors[] = 'الملف غير موجود: ' . $relative; }
}

foreach (['app/Models/NotificationPreference.php', 'app/Models/SystemNotification.php', 'app/Http/Controllers/NotificationSettingsController.php', 'app/Services/SystemNotificationService.php'] as $relative) {
    $file = $root . '/' . $relative;
    if (is_file($file)) {
        exec('php -l ' . escapeshellarg($file), $out, $code);
        if ($code !== 0) { $errors[] = 'خطأ syntax في ' . $relative . ': ' . implode(' ', $out); }
    }
}

$service = $root . '/app/Services/SystemNotificationService.php';
if (is_file($service)) {
    $content = file_get_contents($service);
    foreach (['availableNotificationTypes', 'notificationEnabled', 'preferenceKeyFrom', 'syncForCurrentUser', 'notifyAdmins', 'notifyCurrentUser', 'createForUser'] as $method) {
        if (! str_contains($content, 'function ' . $method)) { $errors[] = 'الدالة غير موجودة في SystemNotificationService: ' . $method; }
    }
    foreach (['events.document_created', 'events.attachment_uploaded', 'flash.success'] as $key) {
        if (! str_contains($content, $key)) { $errors[] = 'مفتاح الإشعار غير موجود داخل الخدمة: ' . $key; }
    }
}

$routes = $root . '/routes/web.php';
if (! is_file($routes) || ! str_contains(file_get_contents($routes), 'notification-settings')) { $errors[] = 'مسارات notification-settings غير موجودة في routes/web.php'; }

$hasButton = false;
foreach (glob($root . '/resources/views/**/*.blade.php') ?: [] as $view) {
    if (str_contains(file_get_contents($view), "notification-settings.edit")) { $hasButton = true; break; }
}
if (! $hasButton) { $errors[] = 'لا يوجد زر أو رابط لإعدادات الإشعارات داخل ملفات الواجهة.'; }

try {
    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    if (! Illuminate\Support\Facades\Schema::hasTable('notification_preferences')) { $errors[] = 'جدول notification_preferences غير موجود. نفّذ: php artisan migrate'; }
    if (! method_exists(App\Services\SystemNotificationService::class, 'syncForCurrentUser')) { $errors[] = 'Laravel لا يرى syncForCurrentUser بعد التحميل.'; }
} catch (Throwable $e) {
    $errors[] = 'تعذر فحص Laravel runtime: ' . $e->getMessage();
}

if ($errors) {
    echo "ERROR: لم يكتمل إصلاح إعدادات الإشعارات V2.\n";
    foreach ($errors as $error) { echo "- {$error}\n"; }
    exit(1);
}

echo "OK: إصلاح إعدادات الإشعارات V2 مكتمل.\n";
PHP_CHECK;

write_file($root . '/app/Models/NotificationPreference.php', $preferenceModel);
write_file($root . '/app/Models/SystemNotification.php', $systemNotificationModel);
write_file($root . '/database/migrations/2026_06_28_000014_create_notification_preferences_table_if_missing.php', $migrationPreferences);
write_file($root . '/app/Http/Controllers/NotificationSettingsController.php', $controller);
write_file($root . '/resources/views/notifications/settings.blade.php', $settingsView);
write_file($root . '/app/Services/SystemNotificationService.php', $service);
write_file($root . '/scripts/check_notification_settings_v2_fix.php', $check);

// routes/web.php
patch_file($root . '/routes/web.php', function (string $content): string {
    $content = ensure_use_line($content, 'use App\\Http\\Controllers\\NotificationSettingsController;');

    if (str_contains($content, "notification-settings.edit") && str_contains($content, "notification-settings.update")) {
        return $content;
    }

    $block = <<<'ROUTES'

Route::middleware(['auth'])->group(function () {
    Route::get('/notification-settings', [NotificationSettingsController::class, 'edit'])->name('notification-settings.edit');
    Route::post('/notification-settings', [NotificationSettingsController::class, 'update'])->name('notification-settings.update');
});
ROUTES;

    return rtrim($content) . "\n" . $block . "\n";
});

// Add a visible button in notifications pages.
foreach (glob($root . '/resources/views/notifications/*.blade.php') ?: [] as $viewPath) {
    if (basename($viewPath) === 'settings.blade.php') { continue; }
    patch_file($viewPath, function (string $content): string {
        if (str_contains($content, "notification-settings.edit")) { return $content; }

        $button = <<<'BLADE_BUTTON'

<div class="notification-settings-shortcut" style="display:flex;justify-content:flex-end;margin:0 0 14px;direction:rtl;">
    <a href="{{ route('notification-settings.edit') }}" class="btn btn-secondary" style="text-decoration:none;border-radius:12px;padding:9px 14px;background:#e5e7eb;color:#111827;display:inline-flex;gap:8px;align-items:center;">⚙️ إعدادات الإشعارات</a>
</div>
BLADE_BUTTON;

        if (preg_match('/@section\([\'\"]content[\'\"]\)/', $content, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);
            return substr($content, 0, $pos) . $button . substr($content, $pos);
        }

        if (preg_match('/<h1[^>]*>.*?<\/h1>/is', $content, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);
            return substr($content, 0, $pos) . $button . substr($content, $pos);
        }

        return $button . "\n" . $content;
    });
}

// Add sidebar/layout link if possible.
foreach (glob($root . '/resources/views/layouts/*.blade.php') ?: [] as $layout) {
    patch_file($layout, function (string $content): string {
        if (str_contains($content, "notification-settings.edit")) { return $content; }

        $link = <<<'BLADE_LINK'

                    @if(auth()->check() && ((auth()->user()->role ?? '') === 'admin' || str_contains((string) (auth()->user()->role ?? ''), 'مدير')))
                        <a href="{{ route('notification-settings.edit') }}" class="nav-link {{ request()->routeIs('notification-settings.*') ? 'active' : '' }}">⚙️ إعدادات الإشعارات</a>
                    @endif
BLADE_LINK;

        foreach (["route('notifications.index')", 'notifications.index', '/notifications'] as $needle) {
            $pos = strpos($content, $needle);
            if ($pos !== false) {
                $lineEnd = strpos($content, "\n", $pos);
                if ($lineEnd !== false) {
                    return substr($content, 0, $lineEnd + 1) . $link . substr($content, $lineEnd + 1);
                }
            }
        }

        return $content;
    });
}

echo "DONE: نفّذ php artisan migrate ثم composer dump-autoload ثم php scripts/check_notification_settings_v2_fix.php\n";

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
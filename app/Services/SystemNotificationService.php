<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemNotificationService
{
    public static function createForUser(User $user, string $title, ?string $body = null, string $type = 'info', ?string $link = null, ?string $uniqueKey = null, string $source = 'manual', array $payload = []): ?SystemNotification
    {
        if (! Schema::hasTable('system_notifications')) {
            return null;
        }

        $data = [
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'source' => $source,
            'payload' => $payload,
            'dismissed_at' => null,
        ];

        if ($uniqueKey) {
            $notification = SystemNotification::updateOrCreate(
                ['user_id' => $user->id, 'unique_key' => $uniqueKey],
                $data + ['unique_key' => $uniqueKey]
            );

            if ($notification->wasChanged(['title', 'body', 'type', 'link', 'payload', 'dismissed_at'])) {
                $notification->read_at = null;
                $notification->save();
            }

            return $notification;
        }

        return SystemNotification::create($data);
    }

    public static function syncForCurrentUser(?User $user): void
    {
        if (! $user || ! Schema::hasTable('system_notifications')) {
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

            SystemNotification::where('user_id', $user->id)
                ->where('source', 'system_monitor')
                ->whereNotIn('unique_key', $activeKeys)
                ->whereNull('dismissed_at')
                ->update([
                    'dismissed_at' => now(),
                    'read_at' => now(),
                ]);
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

    private static function isAdmin(User $user): bool
    {
        $role = (string) ($user->role ?? '');

        return $role === 'admin'
            || str_contains($role, 'مدير')
            || method_exists($user, 'hasPermission') && $user->hasPermission('backups.view');
    }
}
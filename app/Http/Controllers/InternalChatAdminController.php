<?php

namespace App\Http\Controllers;

use App\Models\InternalChatAttachment;
use App\Models\InternalChatConversation;
use App\Models\InternalChatMessage;
use App\Models\InternalChatParticipant;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class InternalChatAdminController extends Controller
{
    private const BACKUP_DISK = 'local';
    private const BACKUP_DIR = 'backups/internal-chat';
    private const BACKUP_TYPE = 'internal_chat_backup_v56';

    public function backup(Request $request): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeInternalChatAdmin($request, 'internal_chat.backup');
        $this->ensureInternalChatTables();

        $backup = $this->buildBackupPayload($request);
        $fileName = 'internal-chat-backup-' . now()->format('Ymd-His') . '.json';
        $relativePath = self::BACKUP_DIR . '/' . $fileName;

        Storage::disk(self::BACKUP_DISK)->put(
            $relativePath,
            json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        $this->logAction('internal_chat.backup_created', 'تم إنشاء نسخة احتياطية للدردشات الداخلية.', [
            'file' => $relativePath,
            'summary' => $backup['summary'],
        ]);

        return response()->download(Storage::disk(self::BACKUP_DISK)->path($relativePath), $fileName, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ])->deleteFileAfterSend(false);
    }

    public function restoreBackup(Request $request): RedirectResponse
    {
        $this->authorizeInternalChatAdmin($request, 'internal_chat.restore_backup');
        $this->ensureInternalChatTables();

        $validated = $request->validate([
            'backup_file' => ['required', 'file', 'max:20480'],
        ], [
            'backup_file.required' => 'اختر ملف نسخة احتياطية للدردشات قبل الاستعادة.',
            'backup_file.file' => 'ملف النسخة الاحتياطية غير صالح.',
            'backup_file.max' => 'حجم ملف النسخة الاحتياطية كبير جدًا.',
        ]);

        $uploaded = $validated['backup_file'];
        $raw = file_get_contents($uploaded->getRealPath());
        $backup = json_decode((string) $raw, true);

        if (! is_array($backup) || (($backup['metadata']['type'] ?? null) !== self::BACKUP_TYPE)) {
            return back()->withErrors(['backup_file' => 'ملف النسخة الاحتياطية لا يطابق صيغة نسخ الدردشة V56.']);
        }

        $tables = $backup['tables'] ?? [];
        if (! is_array($tables)) {
            return back()->withErrors(['backup_file' => 'ملف النسخة الاحتياطية لا يحتوي بيانات الجداول المطلوبة.']);
        }

        $restored = DB::transaction(function () use ($tables) {
            $counts = [
                'conversations' => 0,
                'participants' => 0,
                'messages' => 0,
                'attachments' => 0,
            ];

            $this->upsertRows('internal_chat_conversations', $tables['internal_chat_conversations'] ?? [], $counts['conversations']);
            $this->upsertRows('internal_chat_participants', $tables['internal_chat_participants'] ?? [], $counts['participants']);
            $this->upsertRows('internal_chat_messages', $tables['internal_chat_messages'] ?? [], $counts['messages']);

            if (Schema::hasTable('internal_chat_attachments')) {
                $this->upsertRows('internal_chat_attachments', $tables['internal_chat_attachments'] ?? [], $counts['attachments']);
            }

            return $counts;
        });

        $this->logAction('internal_chat.backup_restored', 'تمت استعادة نسخة احتياطية للدردشات الداخلية.', [
            'summary' => $restored,
        ]);

        return back()->with('success', 'تمت استعادة نسخة الدردشات بنجاح. تمت معالجة ' . array_sum($restored) . ' سجل.')->with('internal_chat_admin_success', 'تمت استعادة نسخة الدردشات بنجاح.');
    }

    public function restoreDeleted(Request $request): RedirectResponse
    {
        $this->authorizeInternalChatAdmin($request, 'internal_chat.restore_deleted');
        $this->ensureInternalChatTables();

        $affected = InternalChatParticipant::query()
            ->where(function ($query) {
                $query->whereNotNull('cleared_at')
                    ->orWhereNotNull('deleted_at')
                    ->orWhereNotNull('archived_at');
            })
            ->update([
                'cleared_at' => null,
                'deleted_at' => null,
                'archived_at' => null,
                'updated_at' => now(),
            ]);

        $this->logAction('internal_chat.deleted_restored', 'تمت استعادة الدردشات المحذوفة أو المؤرشفة ظاهريًا.', [
            'participants_restored' => $affected,
        ]);

        return back()->with('success', 'تمت استعادة الدردشات المحذوفة/المؤرشفة ظاهريًا. عدد السجلات المتأثرة: ' . $affected . '.')->with('internal_chat_admin_success', 'تمت استعادة الدردشات المحذوفة/المؤرشفة ظاهريًا.');
    }

    public function purgeDeleted(Request $request): RedirectResponse
    {
        $this->authorizeInternalChatAdmin($request, 'internal_chat.force_delete');
        $this->ensureInternalChatTables();

        $validated = $request->validate([
            'confirmation' => ['required', 'string'],
        ], [
            'confirmation.required' => 'اكتب عبارة التأكيد قبل الحذف النهائي.',
        ]);

        $confirmation = preg_replace('/\s+/u', ' ', trim((string) $validated['confirmation']));
        if ($confirmation !== 'حذف نهائي') {
            return back()
                ->withInput()
                ->withErrors(['confirmation' => 'عبارة التأكيد غير صحيحة. اكتب العبارة كما هي: حذف نهائي'])
                ->with('internal_chat_admin_error', 'لم يتم تنفيذ التفريغ النهائي لأن عبارة التأكيد غير صحيحة. اكتب: حذف نهائي');
        }

        $backup = $this->buildBackupPayload($request, 'auto_before_purge');
        $fileName = 'internal-chat-auto-before-purge-' . now()->format('Ymd-His') . '.json';
        $relativePath = self::BACKUP_DIR . '/' . $fileName;
        Storage::disk(self::BACKUP_DISK)->put(
            $relativePath,
            json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        $purgeableIds = $this->purgeableConversationIds();

        $deleted = DB::transaction(function () use ($purgeableIds) {
            $counts = [
                'conversations' => 0,
                'participants' => 0,
                'messages' => 0,
                'attachments' => 0,
            ];

            if ($purgeableIds->isEmpty()) {
                return $counts;
            }

            $messageIds = InternalChatMessage::query()
                ->whereIn('conversation_id', $purgeableIds)
                ->pluck('id');

            if (Schema::hasTable('internal_chat_attachments') && $messageIds->isNotEmpty()) {
                $counts['attachments'] = InternalChatAttachment::query()
                    ->whereIn('internal_chat_message_id', $messageIds)
                    ->delete();
            }

            $counts['messages'] = InternalChatMessage::query()
                ->whereIn('conversation_id', $purgeableIds)
                ->delete();

            $counts['participants'] = InternalChatParticipant::query()
                ->whereIn('conversation_id', $purgeableIds)
                ->delete();

            $counts['conversations'] = InternalChatConversation::query()
                ->whereIn('id', $purgeableIds)
                ->delete();

            return $counts;
        });

        $this->logAction('internal_chat.deleted_purged', 'تم تفريغ الدردشات المحذوفة نهائيًا بعد إنشاء نسخة احتياطية تلقائية.', [
            'backup_file' => $relativePath,
            'purged' => $deleted,
        ]);

        return back()->with('success', 'تم تفريغ الدردشات المحذوفة نهائيًا. تم إنشاء نسخة احتياطية تلقائية قبل الحذف: ' . $fileName)->with('internal_chat_admin_success', 'تم تفريغ الدردشات المحذوفة نهائيًا بعد إنشاء نسخة احتياطية تلقائية.');
    }

    private function authorizeInternalChatAdmin(Request $request, string $permission): void
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'hasPermission') || ! $user->hasPermission($permission)) {
            abort(403, 'ليست لديك صلاحية تنفيذ هذا الإجراء على الدردشة الداخلية.');
        }
    }

    private function ensureInternalChatTables(): void
    {
        if (! Schema::hasTable('internal_chat_conversations') || ! Schema::hasTable('internal_chat_participants') || ! Schema::hasTable('internal_chat_messages')) {
            abort(422, 'تحديث الدردشة الداخلية غير مكتمل. نفّذ php artisan migrate ثم أعد المحاولة.');
        }
    }

    private function buildBackupPayload(Request $request, string $mode = 'manual'): array
    {
        return [
            'metadata' => [
                'type' => self::BACKUP_TYPE,
                'version' => 'v56',
                'mode' => $mode,
                'created_at' => now()->toDateTimeString(),
                'created_by' => $request->user()?->only(['id', 'name', 'username', 'role']),
                'app' => config('app.name'),
            ],
            'summary' => [
                'conversations' => $this->tableCount('internal_chat_conversations'),
                'participants' => $this->tableCount('internal_chat_participants'),
                'messages' => $this->tableCount('internal_chat_messages'),
                'attachments' => $this->tableCount('internal_chat_attachments'),
                'visually_deleted_participants' => $this->visuallyDeletedParticipantsCount(),
                'purgeable_conversations' => $this->purgeableConversationIds()->count(),
            ],
            'tables' => [
                'internal_chat_conversations' => $this->tableRows('internal_chat_conversations'),
                'internal_chat_participants' => $this->tableRows('internal_chat_participants'),
                'internal_chat_messages' => $this->tableRows('internal_chat_messages'),
                'internal_chat_attachments' => $this->tableRows('internal_chat_attachments'),
            ],
        ];
    }

    private function tableRows(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->values()
            ->all();
    }

    private function tableCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    private function visuallyDeletedParticipantsCount(): int
    {
        return (int) InternalChatParticipant::query()
            ->where(function ($query) {
                $query->whereNotNull('cleared_at')
                    ->orWhereNotNull('deleted_at');
            })
            ->count();
    }

    private function purgeableConversationIds()
    {
        return InternalChatConversation::query()
            ->whereHas('participants')
            ->whereDoesntHave('participants', function ($query) {
                $query->whereNull('cleared_at')->whereNull('deleted_at');
            })
            ->pluck('id');
    }

    private function upsertRows(string $table, mixed $rows, int &$count): void
    {
        if (! Schema::hasTable($table) || ! is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (! is_array($row) || ! array_key_exists('id', $row)) {
                continue;
            }

            DB::table($table)->updateOrInsert(['id' => $row['id']], $row);
            $count++;
        }
    }

    private function logAction(string $action, string $description, array $properties = []): void
    {
        try {
            ActivityLogger::log($action, $description, null, $properties);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

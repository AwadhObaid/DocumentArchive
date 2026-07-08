<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\InternalChatConversation;
use App\Models\InternalChatMessage;
use App\Models\InternalChatParticipant;
use App\Models\Memo;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

class InternalChatController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUserId = (int) $request->user()->id;
        $this->touchPresence($currentUserId);

        return response()->json($this->basePayload($currentUserId) + [
            'enabled' => true,
            'poll_seconds' => $this->pollSeconds(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUserId = (int) $request->user()->id;
        $this->touchPresence($currentUserId);

        return response()->json($this->basePayload($currentUserId));
    }

    public function messages(Request $request, User $user): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUserId = (int) $request->user()->id;
        $otherUserId = (int) $user->id;
        if ($otherUserId === $currentUserId || ! $this->userCanChat($user)) {
            return response()->json(['message' => 'المستخدم المطلوب غير متاح للدردشة.'], 422);
        }

        $conversation = $this->resolveDirectConversation($currentUserId, $otherUserId, true);
        if (! $conversation) {
            return response()->json(['message' => 'تعذر فتح المحادثة المباشرة.'], 422);
        }

        $messages = $this->loadConversationMessages($conversation, $currentUserId, $request);
        $this->markConversationAsRead($conversation, $currentUserId);
        $this->touchPresence($currentUserId);

        return response()->json($this->basePayload($currentUserId) + [
            'conversation' => $this->conversationPayload($conversation->fresh(['participants.user']), $currentUserId),
            'messages' => $messages->map(fn (InternalChatMessage $message) => $this->messagePayload($message, $currentUserId))->values(),
            'last_id' => (int) ($messages->max('id') ?? max(0, (int) $request->integer('after_id', 0))),
        ]);
    }

    public function conversationMessages(Request $request, InternalChatConversation $conversation): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUserId = (int) $request->user()->id;
        if (! $this->participant($conversation->id, $currentUserId)) {
            return response()->json(['message' => 'ليست لديك صلاحية الوصول لهذه المحادثة.'], 403);
        }

        $messages = $this->loadConversationMessages($conversation, $currentUserId, $request);
        $this->markConversationAsRead($conversation, $currentUserId);
        $this->touchPresence($currentUserId);

        return response()->json($this->basePayload($currentUserId) + [
            'conversation' => $this->conversationPayload($conversation->fresh(['participants.user']), $currentUserId),
            'messages' => $messages->map(fn (InternalChatMessage $message) => $this->messagePayload($message, $currentUserId))->values(),
            'last_id' => (int) ($messages->max('id') ?? max(0, (int) $request->integer('after_id', 0))),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUser = $request->user();
        $currentUserId = (int) $currentUser->id;
        if (! $currentUser->hasPermission('internal_chat.send')) {
            return response()->json(['message' => 'ليست لديك صلاحية إرسال رسائل الدردشة.'], 403);
        }

        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer', Rule::exists('internal_chat_conversations', 'id')],
            'receiver_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'body' => ['nullable', 'string', 'max:2000'],
            'document_id' => ['nullable', 'integer', Rule::exists('documents', 'id')],
            'memo_id' => ['nullable', 'integer', Rule::exists('memos', 'id')],
        ], [
            'receiver_id.exists' => 'المستخدم المستلم غير موجود أو غير نشط.',
            'body.max' => 'نص الرسالة طويل جداً. الحد الأقصى 2000 حرف.',
            'document_id.exists' => 'الكتاب المحدد غير موجود.',
            'memo_id.exists' => 'المذكرة المحددة غير موجودة.',
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $documentId = isset($validated['document_id']) ? (int) $validated['document_id'] : null;
        $memoId = isset($validated['memo_id']) ? (int) $validated['memo_id'] : null;

        if ($body === '' && ! $documentId && ! $memoId) {
            return response()->json(['message' => 'اكتب نص الرسالة أو أرفق كتابًا/مذكرة قبل الإرسال.'], 422);
        }

        if ($documentId && ! $currentUser->hasPermission('documents.view')) {
            return response()->json(['message' => 'ليست لديك صلاحية إرفاق الكتب.'], 403);
        }
        if ($memoId && ! $currentUser->hasPermission('memos.view')) {
            return response()->json(['message' => 'ليست لديك صلاحية إرفاق المذكرات.'], 403);
        }

        $conversation = null;
        if (! empty($validated['conversation_id'])) {
            $conversation = InternalChatConversation::query()->find((int) $validated['conversation_id']);
            if (! $conversation || ! $this->participant((int) $conversation->id, $currentUserId)) {
                return response()->json(['message' => 'ليست لديك صلاحية الإرسال في هذه المحادثة.'], 403);
            }
        } elseif (! empty($validated['receiver_id'])) {
            $receiverId = (int) $validated['receiver_id'];
            if ($receiverId === $currentUserId) {
                return response()->json(['message' => 'لا يمكن إرسال رسالة لنفس المستخدم.'], 422);
            }
            $receiver = User::query()->find($receiverId);
            if (! $receiver || ! $this->userCanChat($receiver)) {
                return response()->json(['message' => 'المستخدم المستلم غير متاح للدردشة.'], 422);
            }
            $conversation = $this->resolveDirectConversation($currentUserId, $receiverId, true);
        }

        if (! $conversation) {
            return response()->json(['message' => 'اختر مستخدمًا أو محادثة قبل الإرسال.'], 422);
        }

        $message = DB::transaction(function () use ($conversation, $currentUserId, $body, $documentId, $memoId) {
            $receiverId = $this->primaryReceiverId($conversation, $currentUserId);

            $message = InternalChatMessage::query()->create([
                'conversation_id' => (int) $conversation->id,
                'sender_id' => $currentUserId,
                'receiver_id' => $receiverId,
                'body' => $body,
                'document_id' => $documentId,
                'memo_id' => $memoId,
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();

            InternalChatParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->update(['deleted_at' => null, 'archived_at' => null, 'updated_at' => now()]);

            InternalChatParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $currentUserId)
                ->update([
                    'last_read_message_id' => $message->id,
                    'last_read_at' => now(),
                    'updated_at' => now(),
                ]);

            return $message;
        });

        $message->load(['sender:id,name,username', 'receiver:id,name,username', 'document:id,reference_number,subject,title', 'memo:id,memo_number,subject']);
        $this->touchPresence($currentUserId);

        try {
            ActivityLogger::log('internal_chat.message_sent', 'تم إرسال رسالة دردشة داخلية.', null, [
                'conversation_id' => $conversation->id,
                'receiver_id' => $message->receiver_id,
                'document_id' => $documentId,
                'memo_id' => $memoId,
                'message_id' => $message->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return response()->json($this->basePayload($currentUserId) + [
            'conversation' => $this->conversationPayload($conversation->fresh(['participants.user']), $currentUserId),
            'message' => $this->messagePayload($message, $currentUserId),
        ], 201);
    }

    public function poll(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUserId = (int) $request->user()->id;
        $conversation = null;
        $afterId = max(0, (int) $request->integer('after_id', 0));
        $messages = collect();

        if ($request->filled('conversation_id')) {
            $conversation = InternalChatConversation::query()->find((int) $request->integer('conversation_id'));
            if ($conversation && $this->participant((int) $conversation->id, $currentUserId)) {
                $messages = $this->conversationMessageQuery($conversation, $currentUserId)
                    ->where('id', '>', $afterId)
                    ->orderBy('id')
                    ->limit(100)
                    ->get();
                $this->markConversationAsRead($conversation, $currentUserId);
            }
        } elseif ($request->filled('with_user_id')) {
            $withUserId = max(0, (int) $request->integer('with_user_id', 0));
            if ($withUserId > 0 && $withUserId !== $currentUserId) {
                $other = User::query()->find($withUserId);
                if ($other && $this->userCanChat($other)) {
                    $conversation = $this->resolveDirectConversation($currentUserId, $withUserId, true);
                    if ($conversation) {
                        $messages = $this->conversationMessageQuery($conversation, $currentUserId)
                            ->where('id', '>', $afterId)
                            ->orderBy('id')
                            ->limit(100)
                            ->get();
                        $this->markConversationAsRead($conversation, $currentUserId);
                    }
                }
            }
        }

        $this->touchPresence($currentUserId);

        return response()->json($this->basePayload($currentUserId) + [
            'messages' => $messages->map(fn (InternalChatMessage $message) => $this->messagePayload($message, $currentUserId))->values(),
            'last_id' => (int) ($messages->max('id') ?? $afterId),
        ]);
    }

    public function markRead(Request $request, User $user): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUserId = (int) $request->user()->id;
        if ((int) $user->id !== $currentUserId && $this->userCanChat($user)) {
            $conversation = $this->resolveDirectConversation($currentUserId, (int) $user->id, false);
            if ($conversation) {
                $this->markConversationAsRead($conversation, $currentUserId);
            }
        }

        $this->touchPresence($currentUserId);

        return response()->json($this->basePayload($currentUserId));
    }

    public function createGroup(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUser = $request->user();
        $currentUserId = (int) $currentUser->id;
        if (! $currentUser->hasPermission('internal_chat.send')) {
            return response()->json(['message' => 'ليست لديك صلاحية إنشاء محادثة جماعية.'], 403);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true))],
        ], [
            'user_ids.required' => 'اختر مستخدمًا واحدًا على الأقل للمجموعة.',
            'user_ids.min' => 'اختر مستخدمًا واحدًا على الأقل للمجموعة.',
        ]);

        $participantIds = collect($validated['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $currentUserId)
            ->unique()
            ->values();

        if ($participantIds->isEmpty()) {
            return response()->json(['message' => 'اختر مستخدمًا آخر غير حسابك.'], 422);
        }

        $title = trim((string) ($validated['title'] ?? ''));
        if ($title === '') {
            $names = User::query()->whereIn('id', $participantIds)->pluck('name')->filter()->take(3)->implode('، ');
            $title = $names ? ('مجموعة: ' . $names) : 'محادثة جماعية';
        }

        $conversation = DB::transaction(function () use ($currentUserId, $participantIds, $title) {
            $conversation = InternalChatConversation::query()->create([
                'type' => 'group',
                'title' => $title,
                'created_by' => $currentUserId,
            ]);

            $allIds = $participantIds->push($currentUserId)->unique()->values();
            foreach ($allIds as $userId) {
                InternalChatParticipant::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => (int) $userId,
                    'role' => (int) $userId === $currentUserId ? 'owner' : 'member',
                    'last_read_at' => (int) $userId === $currentUserId ? now() : null,
                ]);
            }

            return $conversation;
        });

        $this->touchPresence($currentUserId);

        return response()->json($this->basePayload($currentUserId) + [
            'conversation' => $this->conversationPayload($conversation->fresh(['participants.user']), $currentUserId),
        ], 201);
    }

    public function archiveConversation(Request $request, InternalChatConversation $conversation): JsonResponse
    {
        return $this->hideConversation($request, $conversation, 'archive');
    }

    public function deleteConversation(Request $request, InternalChatConversation $conversation): JsonResponse
    {
        return $this->hideConversation($request, $conversation, 'delete');
    }

    public function archivedConversations(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUserId = (int) $request->user()->id;
        $this->touchPresence($currentUserId);

        return response()->json([
            'conversations' => $this->archivedConversationsPayload($currentUserId),
        ]);
    }

    public function restoreConversation(Request $request, InternalChatConversation $conversation): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUserId = (int) $request->user()->id;
        $participant = $this->participant((int) $conversation->id, $currentUserId);
        if (! $participant) {
            return response()->json(['message' => 'ليست لديك صلاحية استعادة هذه المحادثة.'], 403);
        }

        $participant->forceFill([
            'archived_at' => null,
            'deleted_at' => null,
            'updated_at' => now(),
        ])->save();

        $this->touchPresence($currentUserId);

        return response()->json($this->basePayload($currentUserId) + [
            'conversation' => $this->conversationPayload($conversation->fresh(['participants.user']), $currentUserId),
            'archived_conversations' => $this->archivedConversationsPayload($currentUserId),
            'message' => 'تمت استعادة المحادثة إلى القائمة الرئيسية.',
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUserId = (int) $request->user()->id;
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['messages' => [], 'message' => 'اكتب حرفين على الأقل للبحث.']);
        }

        $conversation = null;
        if ($request->filled('conversation_id')) {
            $conversation = InternalChatConversation::query()->find((int) $request->integer('conversation_id'));
        } elseif ($request->filled('user_id')) {
            $otherUserId = (int) $request->integer('user_id');
            if ($otherUserId > 0 && $otherUserId !== $currentUserId) {
                $conversation = $this->resolveDirectConversation($currentUserId, $otherUserId, false);
            }
        }

        if (! $conversation || ! $this->participant((int) $conversation->id, $currentUserId)) {
            return response()->json(['messages' => [], 'message' => 'اختر محادثة صحيحة قبل البحث.'], 422);
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $messages = $this->conversationMessageQuery($conversation, $currentUserId)
            ->where(function (Builder $query) use ($like) {
                $query->where('body', 'like', $like)
                    ->orWhereHas('document', function (Builder $documentQuery) use ($like) {
                        $documentQuery->where('reference_number', 'like', $like)
                            ->orWhere('subject', 'like', $like)
                            ->orWhere('title', 'like', $like);
                    })
                    ->orWhereHas('memo', function (Builder $memoQuery) use ($like) {
                        $memoQuery->where('memo_number', 'like', $like)
                            ->orWhere('subject', 'like', $like);
                    });
            })
            ->latest('id')
            ->limit(40)
            ->get()
            ->reverse()
            ->values();

        $this->touchPresence($currentUserId);

        return response()->json([
            'messages' => $messages->map(fn (InternalChatMessage $message) => $this->messagePayload($message, $currentUserId))->values(),
        ]);
    }

    public function lookupDocuments(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        if (! $request->user()->hasPermission('documents.view')) {
            return response()->json(['items' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2 || ! Schema::hasTable('documents')) {
            return response()->json(['items' => []]);
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $items = Document::query()
            ->select(['id', 'reference_number', 'subject', 'title'])
            ->where(function (Builder $query) use ($like) {
                $query->where('reference_number', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('title', 'like', $like);
            })
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Document $document) => [
                'id' => (int) $document->id,
                'label' => $this->documentLabel($document),
            ])
            ->values();

        return response()->json(['items' => $items]);
    }

    public function lookupMemos(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        if (! $request->user()->hasPermission('memos.view')) {
            return response()->json(['items' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2 || ! Schema::hasTable('memos')) {
            return response()->json(['items' => []]);
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $items = Memo::query()
            ->select(['id', 'memo_number', 'subject'])
            ->where(function (Builder $query) use ($like) {
                $query->where('memo_number', 'like', $like)
                    ->orWhere('subject', 'like', $like);
            })
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Memo $memo) => [
                'id' => (int) $memo->id,
                'label' => $this->memoLabel($memo),
            ])
            ->values();

        return response()->json(['items' => $items]);
    }

    private function hideConversation(Request $request, InternalChatConversation $conversation, string $mode): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUserId = (int) $request->user()->id;
        $participant = $this->participant((int) $conversation->id, $currentUserId);
        if (! $participant) {
            return response()->json(['message' => 'ليست لديك صلاحية تعديل هذه المحادثة.'], 403);
        }

        if ($mode === 'delete') {
            $latestMessageId = (int) InternalChatMessage::query()
                ->where('conversation_id', $conversation->id)
                ->max('id');

            $participant->forceFill([
                // V39: الحذف الظاهري يعني إخفاء سجل الرسائل السابق من شاشة المستخدم فقط،
                // وليس حذف المحادثة من قائمة المستخدمين أو حذف الرسائل من قاعدة البيانات.
                'cleared_at' => now(),
                'deleted_at' => null,
                'archived_at' => null,
                'last_read_message_id' => $latestMessageId ?: null,
                'last_read_at' => now(),
            ])->save();
        } else {
            $participant->forceFill([
                'archived_at' => now(),
                'deleted_at' => null,
            ])->save();
        }

        $this->touchPresence($currentUserId);

        return response()->json($this->basePayload($currentUserId) + [
            'message' => $mode === 'delete'
                ? 'تم مسح سجل المحادثة ظاهريًا من شاشتك فقط، وبقي المستخدم/المجموعة في القائمة.'
                : 'تم أرشفة المحادثة من قائمتك.',
        ]);
    }

    private function guardAvailable(Request $request): ?JsonResponse
    {
        if (! $this->enabled()) {
            return response()->json(['message' => 'الدردشة الداخلية غير مفعلة حالياً من إعدادات النظام.'], 403);
        }

        if (! $this->advancedReady()) {
            return response()->json(['message' => 'تحديث الدردشة المتقدمة غير مكتمل. نفّذ php artisan migrate ثم حدّث الصفحة.'], 503);
        }

        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasPermission') || ! $user->hasPermission('internal_chat.view')) {
            return response()->json(['message' => 'ليست لديك صلاحية استخدام الدردشة الداخلية.'], 403);
        }

        return null;
    }

    private function advancedReady(): bool
    {
        return Schema::hasTable('internal_chat_messages')
            && Schema::hasTable('internal_chat_conversations')
            && Schema::hasTable('internal_chat_participants')
            && Schema::hasColumn('internal_chat_messages', 'conversation_id')
            && Schema::hasColumn('internal_chat_messages', 'document_id')
            && Schema::hasColumn('internal_chat_messages', 'memo_id')
            && Schema::hasColumn('internal_chat_participants', 'cleared_at');
    }

    private function enabled(): bool
    {
        return (string) Setting::getValue('internal_chat_enabled', '1') === '1';
    }

    private function pollSeconds(): int
    {
        return max(3, min(120, (int) Setting::getValue('internal_chat_poll_seconds', 5)));
    }

    private function userCanChat(User $user): bool
    {
        return (int) $user->id > 0 && (bool) $user->is_active;
    }

    private function basePayload(int $currentUserId): array
    {
        return [
            'unread_total' => $this->unreadTotal($currentUserId),
            'users' => $this->usersPayload($currentUserId),
            'conversations' => $this->conversationsPayload($currentUserId),
        ];
    }

    private function usersPayload(int $currentUserId): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $columns = ['id', 'name', 'username', 'role', 'is_active', 'last_login_at'];
        if (Schema::hasColumn('users', 'last_seen_at')) {
            $columns[] = 'last_seen_at';
        }

        $users = User::query()
            ->select($columns)
            ->where('id', '<>', $currentUserId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $users->map(function (User $user) use ($currentUserId) {
            $userId = (int) $user->id;
            $conversation = $this->resolveDirectConversation($currentUserId, $userId, false);
            $participant = $conversation ? $this->participant((int) $conversation->id, $currentUserId) : null;
            $latest = $conversation
                ? $this->visibleConversationMessageQuery($conversation, $currentUserId, $participant)
                    ->latest('id')
                    ->first()
                : null;
            return [
                'kind' => 'direct',
                'id' => $userId,
                'user_id' => $userId,
                'conversation_id' => $conversation ? (int) $conversation->id : null,
                'name' => $user->name ?: ('مستخدم #' . $userId),
                'username' => $user->username,
                'role_name' => $user->role_name ?? 'مستخدم',
                'status' => $this->presenceStatus($user),
                'status_label' => $this->presenceStatus($user) === 'online' ? 'متصل' : 'غير متصل',
                'unread_count' => $conversation ? $this->conversationUnreadCount($conversation, $currentUserId) : 0,
                'latest_message_id' => $latest ? (int) $latest->id : 0,
                'latest_message_at' => $latest?->created_at?->format('Y-m-d H:i'),
                'latest_preview' => $latest ? $this->messagePreview($latest) : null,
                'archived' => (bool) $participant?->archived_at,
                'deleted' => (bool) $participant?->deleted_at,
            ];
        })->sortByDesc('latest_message_id')->sortByDesc('unread_count')->values()->all();
    }

    private function conversationsPayload(int $currentUserId): array
    {
        if (! $this->advancedReady()) {
            return [];
        }

        $conversations = InternalChatConversation::query()
            ->with(['participants.user'])
            ->whereHas('participants', function (Builder $query) use ($currentUserId) {
                $query->where('user_id', $currentUserId)
                    ->whereNull('deleted_at')
                    ->whereNull('archived_at');
            })
            ->where(function (Builder $query) {
                $query->where('type', 'group')
                    ->orWhereHas('messages');
            })
            ->orderByDesc(DB::raw('COALESCE(last_message_at, updated_at, created_at)'))
            ->limit(50)
            ->get();

        return $conversations
            ->map(fn (InternalChatConversation $conversation) => $this->conversationPayload($conversation, $currentUserId))
            ->values()
            ->all();
    }

    private function archivedConversationsPayload(int $currentUserId): array
    {
        if (! $this->advancedReady()) {
            return [];
        }

        $conversations = InternalChatConversation::query()
            ->with(['participants.user'])
            ->whereHas('participants', function (Builder $query) use ($currentUserId) {
                $query->where('user_id', $currentUserId)
                    ->whereNull('deleted_at')
                    ->whereNotNull('archived_at');
            })
            ->where(function (Builder $query) {
                $query->where('type', 'group')
                    ->orWhereHas('messages');
            })
            ->orderByDesc(DB::raw('COALESCE(last_message_at, updated_at, created_at)'))
            ->limit(100)
            ->get();

        return $conversations
            ->map(fn (InternalChatConversation $conversation) => $this->conversationPayload($conversation, $currentUserId))
            ->filter()
            ->values()
            ->all();
    }

    private function conversationPayload(?InternalChatConversation $conversation, int $currentUserId): ?array
    {
        if (! $conversation) {
            return null;
        }

        $conversation->loadMissing(['participants.user']);
        $participant = $conversation->participants->firstWhere('user_id', $currentUserId);
        $latest = $this->visibleConversationMessageQuery($conversation, $currentUserId, $participant)
            ->latest('id')
            ->first();
        $otherParticipants = $conversation->participants->filter(fn ($participant) => (int) $participant->user_id !== $currentUserId)->values();
        $otherUser = $otherParticipants->first()?->user;
        $isGroup = (string) $conversation->type === 'group';
        $title = $isGroup
            ? ((string) $conversation->title ?: 'محادثة جماعية')
            : ($otherUser?->name ?: 'محادثة مباشرة');

        return [
            'kind' => $isGroup ? 'group' : 'direct_conversation',
            'id' => (int) $conversation->id,
            'conversation_id' => (int) $conversation->id,
            'type' => (string) $conversation->type,
            'title' => $title,
            'name' => $title,
            'user_id' => $isGroup ? null : ($otherUser ? (int) $otherUser->id : null),
            'participant_count' => (int) $conversation->participants->count(),
            'participants_label' => $otherParticipants->map(fn ($participant) => $participant->user?->name)->filter()->take(4)->implode('، '),
            'unread_count' => $this->conversationUnreadCount($conversation, $currentUserId),
            'latest_message_id' => $latest ? (int) $latest->id : 0,
            'latest_message_at' => $latest?->created_at?->format('Y-m-d H:i'),
            'latest_preview' => $latest ? $this->messagePreview($latest) : ($isGroup ? 'محادثة جماعية' : 'محادثة مباشرة'),
            'archived' => (bool) $participant?->archived_at,
            'deleted' => (bool) $participant?->deleted_at,
            'status' => $isGroup ? 'group' : ($otherUser ? $this->presenceStatus($otherUser) : 'offline'),
            'status_label' => $isGroup ? ((int) $conversation->participants->count() . ' أعضاء') : ($otherUser && $this->presenceStatus($otherUser) === 'online' ? 'متصل' : 'غير متصل'),
        ];
    }

    private function unreadTotal(int $currentUserId): int
    {
        if (! $this->advancedReady()) {
            return 0;
        }

        return (int) InternalChatConversation::query()
            ->whereHas('participants', function (Builder $query) use ($currentUserId) {
                $query->where('user_id', $currentUserId)->whereNull('deleted_at')->whereNull('archived_at');
            })
            ->get()
            ->sum(fn (InternalChatConversation $conversation) => $this->conversationUnreadCount($conversation, $currentUserId));
    }

    private function conversationUnreadCount(InternalChatConversation $conversation, int $currentUserId): int
    {
        $participant = $this->participant((int) $conversation->id, $currentUserId);
        if (! $participant || $participant->deleted_at || $participant->archived_at) {
            return 0;
        }

        if ((string) $conversation->type === 'direct') {
            return (int) $this->visibleConversationMessageQuery($conversation, $currentUserId, $participant)
                ->where('sender_id', '<>', $currentUserId)
                ->where('receiver_id', $currentUserId)
                ->whereNull('read_at')
                ->count();
        }

        $lastReadId = (int) ($participant->last_read_message_id ?? 0);

        return (int) $this->visibleConversationMessageQuery($conversation, $currentUserId, $participant)
            ->where('sender_id', '<>', $currentUserId)
            ->where('id', '>', $lastReadId)
            ->count();
    }

    private function markConversationAsRead(InternalChatConversation $conversation, int $currentUserId): void
    {
        $latestId = (int) InternalChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->max('id');

        InternalChatParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $currentUserId)
            ->update([
                'last_read_message_id' => $latestId ?: null,
                'last_read_at' => now(),
                'updated_at' => now(),
            ]);

        if ((string) $conversation->type === 'direct') {
            InternalChatMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('receiver_id', $currentUserId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }
    }

    private function loadConversationMessages(InternalChatConversation $conversation, int $currentUserId, Request $request)
    {
        $limit = max(10, min(100, (int) $request->integer('limit', 50)));
        $afterId = max(0, (int) $request->integer('after_id', 0));

        $query = $this->conversationMessageQuery($conversation, $currentUserId)->orderBy('id');

        if ($afterId > 0) {
            return $query->where('id', '>', $afterId)->limit($limit)->get();
        }

        return $query->latest('id')->limit($limit)->get()->reverse()->values();
    }

    private function conversationMessageQuery(InternalChatConversation $conversation, int $currentUserId): Builder
    {
        $participant = $this->participant((int) $conversation->id, $currentUserId);

        return $this->visibleConversationMessageQuery($conversation, $currentUserId, $participant)
            ->with(['sender:id,name,username', 'receiver:id,name,username', 'document:id,reference_number,subject,title', 'memo:id,memo_number,subject']);
    }

    private function visibleConversationMessageQuery(InternalChatConversation $conversation, int $currentUserId, ?InternalChatParticipant $participant = null): Builder
    {
        $query = InternalChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where(function (Builder $query) use ($currentUserId) {
                $query->where(function (Builder $senderQuery) use ($currentUserId) {
                    $senderQuery->where('sender_id', $currentUserId)->whereNull('sender_deleted_at');
                })->orWhere(function (Builder $receiverQuery) use ($currentUserId) {
                    $receiverQuery->where('sender_id', '<>', $currentUserId)->whereNull('receiver_deleted_at');
                });
            });

        $clearedAt = $participant?->cleared_at;
        if ($clearedAt) {
            $query->where('created_at', '>', $clearedAt);
        }

        return $query;
    }

    private function participant(int $conversationId, int $userId): ?InternalChatParticipant
    {
        return InternalChatParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->first();
    }

    private function resolveDirectConversation(int $firstUserId, int $secondUserId, bool $create): ?InternalChatConversation
    {
        $first = min($firstUserId, $secondUserId);
        $second = max($firstUserId, $secondUserId);

        $conversation = InternalChatConversation::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn (Builder $query) => $query->where('user_id', $first))
            ->whereHas('participants', fn (Builder $query) => $query->where('user_id', $second))
            ->withCount('participants')
            ->get()
            ->first(fn (InternalChatConversation $conversation) => (int) $conversation->participants_count === 2);

        if ($conversation) {
            return $conversation;
        }

        if (! $create) {
            return null;
        }

        return DB::transaction(function () use ($first, $second) {
            $conversation = InternalChatConversation::query()->create([
                'type' => 'direct',
                'created_by' => $first,
            ]);

            foreach ([$first, $second] as $userId) {
                InternalChatParticipant::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'role' => 'member',
                ]);
            }

            InternalChatMessage::query()
                ->whereNull('conversation_id')
                ->where(function (Builder $query) use ($first, $second) {
                    $query->where(function (Builder $forward) use ($first, $second) {
                        $forward->where('sender_id', $first)->where('receiver_id', $second);
                    })->orWhere(function (Builder $reverse) use ($first, $second) {
                        $reverse->where('sender_id', $second)->where('receiver_id', $first);
                    });
                })
                ->update(['conversation_id' => $conversation->id]);

            return $conversation;
        });
    }

    private function primaryReceiverId(InternalChatConversation $conversation, int $currentUserId): int
    {
        $conversation->loadMissing('participants');
        $other = $conversation->participants
            ->first(fn (InternalChatParticipant $participant) => (int) $participant->user_id !== $currentUserId);

        return (int) ($other?->user_id ?: $currentUserId);
    }

    private function messagePayload(InternalChatMessage $message, int $currentUserId): array
    {
        $outgoing = $message->isOutgoingFor($currentUserId);
        $message->loadMissing(['document:id,reference_number,subject,title', 'memo:id,memo_number,subject']);

        return [
            'id' => (int) $message->id,
            'conversation_id' => (int) $message->conversation_id,
            'sender_id' => (int) $message->sender_id,
            'receiver_id' => (int) $message->receiver_id,
            'body' => (string) $message->body,
            'direction' => $outgoing ? 'outgoing' : 'incoming',
            'sender_name' => $message->sender?->name,
            'receiver_name' => $message->receiver?->name,
            'read' => (bool) $message->read_at,
            'document' => $message->document ? [
                'id' => (int) $message->document->id,
                'label' => $this->documentLabel($message->document),
                'url' => Route::has('documents.show') ? route('documents.show', $message->document) : null,
            ] : null,
            'memo' => $message->memo ? [
                'id' => (int) $message->memo->id,
                'label' => $this->memoLabel($message->memo),
                'url' => Route::has('memos.show') ? route('memos.show', $message->memo) : null,
            ] : null,
            'created_at' => $message->created_at?->format('Y-m-d H:i'),
            'created_at_timestamp' => $message->created_at?->getTimestamp() ?? 0,
            'date_label' => $message->created_at?->format('Y-m-d'),
            'time' => $message->created_at?->format('H:i'),
            'full_time' => $message->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function messagePreview(InternalChatMessage $message): string
    {
        $body = trim((string) $message->body);
        if ($body !== '') {
            return $this->preview($body);
        }
        if ($message->document_id) {
            return '📘 كتاب مرفق';
        }
        if ($message->memo_id) {
            return '📝 مذكرة مرفقة';
        }

        return 'رسالة داخلية';
    }

    private function documentLabel(Document $document): string
    {
        $number = trim((string) ($document->reference_number ?? ''));
        $subject = trim((string) ($document->subject ?: $document->title ?: ''));

        return trim('كتاب ' . ($number !== '' ? $number : ('#' . $document->id)) . ($subject !== '' ? ' - ' . $subject : ''));
    }

    private function memoLabel(Memo $memo): string
    {
        $number = trim((string) ($memo->memo_number ?? ''));
        $subject = trim((string) ($memo->subject ?? ''));

        return trim('مذكرة ' . ($number !== '' ? $number : ('#' . $memo->id)) . ($subject !== '' ? ' - ' . $subject : ''));
    }

    private function preview(string $body): string
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?: '');

        return mb_strlen($body) > 70 ? mb_substr($body, 0, 70) . '…' : $body;
    }

    private function touchPresence(int $currentUserId): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'last_seen_at')) {
            User::query()->whereKey($currentUserId)->update(['last_seen_at' => now()]);
        }
    }

    private function presenceStatus(User $user): string
    {
        $lastSeen = null;
        if (Schema::hasColumn('users', 'last_seen_at')) {
            $lastSeen = $user->last_seen_at;
        }
        $lastSeen = $lastSeen ?: $user->last_login_at;

        return $lastSeen && $lastSeen->greaterThan(now()->subMinutes(2)) ? 'online' : 'offline';
    }
}

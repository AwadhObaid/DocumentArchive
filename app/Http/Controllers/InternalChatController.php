<?php

namespace App\Http\Controllers;

use App\Models\InternalChatMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json([
            'enabled' => true,
            'poll_seconds' => $this->pollSeconds(),
            'unread_total' => $this->unreadTotal((int) $request->user()->id),
            'users' => $this->usersPayload((int) $request->user()->id),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        return response()->json([
            'users' => $this->usersPayload((int) $request->user()->id),
            'unread_total' => $this->unreadTotal((int) $request->user()->id),
        ]);
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

        $limit = max(10, min(100, (int) $request->integer('limit', 50)));
        $afterId = max(0, (int) $request->integer('after_id', 0));

        $query = InternalChatMessage::query()
            ->between($currentUserId, $otherUserId)
            ->with(['sender:id,name,username', 'receiver:id,name,username'])
            ->orderBy('id');

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
            $messages = $query->limit($limit)->get();
        } else {
            $messages = $query->latest('id')->limit($limit)->get()->reverse()->values();
        }

        $this->markIncomingAsRead($currentUserId, $otherUserId);

        return response()->json([
            'messages' => $messages->map(fn (InternalChatMessage $message) => $this->messagePayload($message, $currentUserId))->values(),
            'last_id' => (int) ($messages->max('id') ?? $afterId),
            'unread_total' => $this->unreadTotal($currentUserId),
            'users' => $this->usersPayload($currentUserId),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUserId = (int) $request->user()->id;

        $validated = $request->validate([
            'receiver_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'receiver_id.required' => 'اختر المستخدم المستلم أولاً.',
            'receiver_id.exists' => 'المستخدم المستلم غير موجود أو غير نشط.',
            'body.required' => 'نص الرسالة مطلوب.',
            'body.max' => 'نص الرسالة طويل جداً. الحد الأقصى 2000 حرف.',
        ]);

        $receiverId = (int) $validated['receiver_id'];
        if ($receiverId === $currentUserId) {
            return response()->json(['message' => 'لا يمكن إرسال رسالة لنفس المستخدم.'], 422);
        }

        $body = trim((string) $validated['body']);
        if ($body === '') {
            return response()->json(['message' => 'نص الرسالة مطلوب.'], 422);
        }

        $message = InternalChatMessage::create([
            'sender_id' => $currentUserId,
            'receiver_id' => $receiverId,
            'body' => $body,
        ])->load(['sender:id,name,username', 'receiver:id,name,username']);

        try {
            ActivityLogger::log('internal_chat.message_sent', 'تم إرسال رسالة دردشة داخلية.', null, [
                'receiver_id' => $receiverId,
                'message_id' => $message->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'message' => $this->messagePayload($message, $currentUserId),
            'unread_total' => $this->unreadTotal($currentUserId),
            'users' => $this->usersPayload($currentUserId),
        ], 201);
    }

    public function poll(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUserId = (int) $request->user()->id;
        $withUserId = max(0, (int) $request->integer('with_user_id', 0));
        $afterId = max(0, (int) $request->integer('after_id', 0));
        $messages = collect();

        if ($withUserId > 0 && $withUserId !== $currentUserId) {
            $other = User::query()->find($withUserId);
            if ($other && $this->userCanChat($other)) {
                $messages = InternalChatMessage::query()
                    ->between($currentUserId, $withUserId)
                    ->with(['sender:id,name,username', 'receiver:id,name,username'])
                    ->where('id', '>', $afterId)
                    ->orderBy('id')
                    ->limit(100)
                    ->get();

                $this->markIncomingAsRead($currentUserId, $withUserId);
            }
        }

        return response()->json([
            'unread_total' => $this->unreadTotal($currentUserId),
            'users' => $this->usersPayload($currentUserId),
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
            $this->markIncomingAsRead($currentUserId, (int) $user->id);
        }

        return response()->json([
            'unread_total' => $this->unreadTotal($currentUserId),
            'users' => $this->usersPayload($currentUserId),
        ]);
    }

    private function guardAvailable(Request $request): ?JsonResponse
    {
        if (! $this->enabled()) {
            return response()->json(['message' => 'الدردشة الداخلية غير مفعلة حالياً من إعدادات النظام.'], 403);
        }

        if (! Schema::hasTable('internal_chat_messages')) {
            return response()->json(['message' => 'جدول الدردشة الداخلية غير موجود. نفّذ أمر php artisan migrate.'], 503);
        }

        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasPermission') || ! $user->hasPermission('internal_chat.view')) {
            return response()->json(['message' => 'ليست لديك صلاحية استخدام الدردشة الداخلية.'], 403);
        }

        return null;
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

    private function usersPayload(int $currentUserId): array
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('internal_chat_messages')) {
            return [];
        }

        $users = User::query()
            ->select(['id', 'name', 'username', 'role', 'is_active', 'last_login_at'])
            ->where('id', '<>', $currentUserId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $payload = $users->map(function (User $user) use ($currentUserId) {
            $userId = (int) $user->id;
            $latest = InternalChatMessage::query()
                ->between($currentUserId, $userId)
                ->latest('id')
                ->first();

            $unread = InternalChatMessage::query()
                ->where('sender_id', $userId)
                ->where('receiver_id', $currentUserId)
                ->whereNull('read_at')
                ->whereNull('receiver_deleted_at')
                ->count();

            return [
                'id' => $userId,
                'name' => $user->name ?: ('مستخدم #' . $userId),
                'username' => $user->username,
                'role_name' => $user->role_name ?? 'مستخدم',
                'unread_count' => (int) $unread,
                'latest_message_id' => $latest ? (int) $latest->id : 0,
                'latest_message_at' => $latest?->created_at?->format('Y-m-d H:i'),
                'latest_preview' => $latest ? $this->preview((string) $latest->body) : null,
            ];
        })->sortByDesc('latest_message_id')->sortByDesc('unread_count')->values();

        return $payload->all();
    }

    private function unreadTotal(int $currentUserId): int
    {
        if (! Schema::hasTable('internal_chat_messages')) {
            return 0;
        }

        return (int) InternalChatMessage::query()
            ->where('receiver_id', $currentUserId)
            ->whereNull('read_at')
            ->whereNull('receiver_deleted_at')
            ->count();
    }

    private function markIncomingAsRead(int $currentUserId, int $otherUserId): void
    {
        InternalChatMessage::query()
            ->where('sender_id', $otherUserId)
            ->where('receiver_id', $currentUserId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function messagePayload(InternalChatMessage $message, int $currentUserId): array
    {
        $outgoing = $message->isOutgoingFor($currentUserId);

        return [
            'id' => (int) $message->id,
            'sender_id' => (int) $message->sender_id,
            'receiver_id' => (int) $message->receiver_id,
            'body' => (string) $message->body,
            'direction' => $outgoing ? 'outgoing' : 'incoming',
            'sender_name' => $message->sender?->name,
            'receiver_name' => $message->receiver?->name,
            'read' => (bool) $message->read_at,
            'created_at' => $message->created_at?->format('Y-m-d H:i'),
            'time' => $message->created_at?->format('H:i'),
        ];
    }

    private function preview(string $body): string
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?: '');

        return mb_strlen($body) > 70 ? mb_substr($body, 0, 70) . '…' : $body;
    }
}

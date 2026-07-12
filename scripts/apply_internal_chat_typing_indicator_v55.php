<?php

$root = dirname(__DIR__);

function fail_v55(string $message): void
{
    echo "[FAIL] {$message}\n";
    exit(1);
}

function ok_v55(string $message): void
{
    echo "[OK] {$message}\n";
}

function path_v55(string $relative): string
{
    global $root;
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function read_file_v55(string $relative): string
{
    $path = path_v55($relative);
    if (! file_exists($path)) {
        fail_v55("File not found: {$relative}");
    }
    $content = file_get_contents($path);
    if ($content === false) {
        fail_v55("Unable to read: {$relative}");
    }
    return $content;
}

function write_file_v55(string $relative, string $content): void
{
    $path = path_v55($relative);
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }
    if (file_put_contents($path, $content) === false) {
        fail_v55("Unable to write: {$relative}");
    }
}

function replace_once_v55(string $content, string $search, string $replace, string $label): string
{
    if (str_contains($content, $replace)) {
        ok_v55("Already applied: {$label}");
        return $content;
    }

    $pos = strpos($content, $search);
    if ($pos === false) {
        fail_v55("Marker not found for {$label}");
    }

    ok_v55("Applied: {$label}");
    return substr_replace($content, $replace, $pos, strlen($search));
}

// -----------------------------------------------------------------------------
// Controller patch
// -----------------------------------------------------------------------------
$controllerFile = 'app/Http/Controllers/InternalChatController.php';
$controller = read_file_v55($controllerFile);

if (! str_contains($controller, 'public function typing(Request $request): JsonResponse')) {
    $typingMethod = <<<'PHP_CODE'
    public function typing(Request $request): JsonResponse
    {
        $guard = $this->guardAvailable($request);
        if ($guard) {
            return $guard;
        }

        $currentUser = $request->user();
        $currentUserId = (int) $currentUser->id;
        if (! $currentUser->hasPermission('internal_chat.send')) {
            return response()->json(['message' => 'ليست لديك صلاحية إرسال حالة الكتابة.'], 403);
        }

        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer', Rule::exists('internal_chat_conversations', 'id')],
            'receiver_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'is_typing' => ['required', 'boolean'],
        ]);

        $conversation = null;
        if (! empty($validated['conversation_id'])) {
            $conversation = InternalChatConversation::query()->find((int) $validated['conversation_id']);
            if (! $conversation || ! $this->participant((int) $conversation->id, $currentUserId)) {
                return response()->json(['message' => 'ليست لديك صلاحية تحديث حالة الكتابة لهذه المحادثة.'], 403);
            }
        } elseif (! empty($validated['receiver_id'])) {
            $receiverId = (int) $validated['receiver_id'];
            if ($receiverId !== $currentUserId) {
                $receiver = User::query()->find($receiverId);
                if ($receiver && $this->userCanChat($receiver)) {
                    $conversation = $this->resolveDirectConversation($currentUserId, $receiverId, true);
                }
            }
        }

        if (! $conversation) {
            return response()->json($this->basePayload($currentUserId) + [
                'typing_users' => [],
            ]);
        }

        if ((bool) $validated['is_typing']) {
            \Illuminate\Support\Facades\Cache::put(
                $this->typingCacheKey((int) $conversation->id, $currentUserId),
                now()->timestamp,
                now()->addSeconds(8)
            );
        } else {
            $this->clearTyping((int) $conversation->id, $currentUserId);
        }

        $this->touchPresence($currentUserId);

        return response()->json($this->basePayload($currentUserId) + [
            'typing_users' => $this->typingUsersPayload($conversation, $currentUserId),
        ]);
    }

PHP_CODE;

    $marker = '    public function poll(Request $request): JsonResponse';
    if (! str_contains($controller, $marker)) {
        fail_v55('Could not find poll method marker in InternalChatController.php');
    }
    $controller = str_replace($marker, $typingMethod . $marker, $controller);
    ok_v55('InternalChatController typing method inserted');
} else {
    ok_v55('InternalChatController typing method already exists');
}

$sendSearch = "        \$message->load(['sender:id,name,username', 'receiver:id,name,username', 'document:id,reference_number,subject,title', 'memo:id,memo_number,subject']);\n        \$this->touchPresence(\$currentUserId);\n";
$sendReplace = "        \$message->load(['sender:id,name,username', 'receiver:id,name,username', 'document:id,reference_number,subject,title', 'memo:id,memo_number,subject']);\n        \$this->touchPresence(\$currentUserId);\n        \$this->clearTyping((int) \$conversation->id, \$currentUserId);\n";
if (str_contains($controller, $sendReplace)) {
    ok_v55('InternalChatController send already clears typing state');
} else {
    if (! str_contains($controller, $sendSearch)) {
        fail_v55('Could not find send touchPresence marker in InternalChatController.php');
    }
    $controller = str_replace($sendSearch, $sendReplace, $controller);
    ok_v55('InternalChatController send clears typing state');
}

$pollSearch = "            'last_id' => (int) (\$messages->max('id') ?? \$afterId),\n";
$pollReplace = "            'last_id' => (int) (\$messages->max('id') ?? \$afterId),\n            'typing_users' => \$conversation ? \$this->typingUsersPayload(\$conversation, \$currentUserId) : [],\n";
if (! str_contains($controller, "'typing_users' => \$conversation ? \$this->typingUsersPayload")) {
    if (! str_contains($controller, $pollSearch)) {
        fail_v55('Could not find poll last_id marker in InternalChatController.php');
    }
    $controller = str_replace($pollSearch, $pollReplace, $controller);
    ok_v55('InternalChatController poll returns typing users');
} else {
    ok_v55('InternalChatController poll already returns typing users');
}

if (! str_contains($controller, 'private function typingUsersPayload(InternalChatConversation $conversation, int $currentUserId): array')) {
    $privateMethods = <<<'PHP_CODE'
    private function clearTyping(int $conversationId, int $userId): void
    {
        \Illuminate\Support\Facades\Cache::forget($this->typingCacheKey($conversationId, $userId));
    }

    private function typingCacheKey(int $conversationId, int $userId): string
    {
        return 'documentarchive:internal_chat:typing:' . $conversationId . ':' . $userId;
    }

    private function typingUsersPayload(InternalChatConversation $conversation, int $currentUserId): array
    {
        $conversation->loadMissing(['participants.user']);

        return $conversation->participants
            ->filter(function (InternalChatParticipant $participant) use ($conversation, $currentUserId) {
                $userId = (int) $participant->user_id;

                return $userId > 0
                    && $userId !== $currentUserId
                    && ! $participant->deleted_at
                    && $participant->user
                    && \Illuminate\Support\Facades\Cache::has($this->typingCacheKey((int) $conversation->id, $userId));
            })
            ->map(fn (InternalChatParticipant $participant) => [
                'id' => (int) $participant->user_id,
                'name' => $participant->user?->name ?: ('مستخدم #' . $participant->user_id),
            ])
            ->values()
            ->all();
    }

PHP_CODE;
    $marker = '    private function guardAvailable(Request $request): ?JsonResponse';
    if (! str_contains($controller, $marker)) {
        fail_v55('Could not find guardAvailable marker in InternalChatController.php');
    }
    $controller = str_replace($marker, $privateMethods . $marker, $controller);
    ok_v55('InternalChatController typing helper methods inserted');
} else {
    ok_v55('InternalChatController typing helper methods already exist');
}

write_file_v55($controllerFile, $controller);

// -----------------------------------------------------------------------------
// Routes patch
// -----------------------------------------------------------------------------
$routesFile = 'routes/web.php';
$routes = read_file_v55($routesFile);
if (! str_contains($routes, "->name('typing')")) {
    $routeInserted = false;
    foreach ([
        "        Route::post('/send', [InternalChatController::class, 'send'])->name('send');\n",
        "        Route::post('/messages', [InternalChatController::class, 'send'])->name('send');\n",
    ] as $search) {
        if (str_contains($routes, $search)) {
            $routes = str_replace($search, $search . "        Route::post('/typing', [InternalChatController::class, 'typing'])->name('typing');\n", $routes);
            $routeInserted = true;
            break;
        }
    }
    if (! $routeInserted) {
        fail_v55('Could not find internal chat send/messages route marker in routes/web.php');
    }
    ok_v55('Internal chat typing route inserted');
} else {
    ok_v55('Internal chat typing route already exists');
}
write_file_v55($routesFile, $routes);

// -----------------------------------------------------------------------------
// Permission middleware patch
// -----------------------------------------------------------------------------
$permissionFile = 'app/Http/Middleware/ApplyRoutePermissions.php';
if (file_exists(path_v55($permissionFile))) {
    $permission = read_file_v55($permissionFile);
    if (! str_contains($permission, "'internal-chat.typing'")) {
        $search = "        'internal-chat.send' => 'internal_chat.send',\n";
        $replace = $search . "        'internal-chat.typing' => 'internal_chat.send',\n";
        if (str_contains($permission, $search)) {
            $permission = str_replace($search, $replace, $permission);
            write_file_v55($permissionFile, $permission);
            ok_v55('Permission middleware updated for internal-chat.typing');
        } else {
            ok_v55('Permission middleware send marker not found; route remains protected by controller');
        }
    } else {
        ok_v55('Permission middleware already contains internal-chat.typing');
    }
}

// -----------------------------------------------------------------------------
// Widget patch
// -----------------------------------------------------------------------------
$widgetFile = 'resources/views/partials/internal-chat-widget.blade.php';
$widget = read_file_v55($widgetFile);
if (! str_contains($widget, 'data-typing-url=')) {
    $search = '        data-memo-lookup-url="{{ route(\'internal-chat.lookup.memos\') }}"' . "\n";
    $replace = $search . '        data-typing-url="{{ route(\'internal-chat.typing\') }}"' . "\n";
    if (! str_contains($widget, $search)) {
        fail_v55('Could not find memo lookup data attribute marker in internal-chat-widget.blade.php');
    }
    $widget = str_replace($search, $replace, $widget);
    ok_v55('Typing data URL added to widget');
} else {
    ok_v55('Typing data URL already exists in widget');
}

if (! str_contains($widget, 'data-chat-typing-indicator')) {
    $search = <<<'BLADE'
                    <form class="internal-chat-form" data-chat-form autocomplete="off">
BLADE;
    $replace = <<<'BLADE'
                    <div class="internal-chat-typing-indicator" data-chat-typing-indicator hidden></div>

                    <form class="internal-chat-form" data-chat-form autocomplete="off">
BLADE;
    if (! str_contains($widget, $search)) {
        fail_v55('Could not find chat form marker in internal-chat-widget.blade.php');
    }
    $widget = str_replace($search, $replace, $widget);
    ok_v55('Typing indicator element inserted');
} else {
    ok_v55('Typing indicator element already exists');
}
write_file_v55($widgetFile, $widget);

// -----------------------------------------------------------------------------
// JavaScript patch
// -----------------------------------------------------------------------------
$jsFile = 'public/js/internal-chat.js';
$js = read_file_v55($jsFile);

if (! str_contains($js, 'typing: widget.dataset.typingUrl')) {
    $search = "            memoLookup: widget.dataset.memoLookupUrl\n";
    $replace = "            memoLookup: widget.dataset.memoLookupUrl,\n            typing: widget.dataset.typingUrl\n";
    if (! str_contains($js, $search)) {
        fail_v55('Could not find urls memoLookup marker in internal-chat.js');
    }
    $js = str_replace($search, $replace, $js);
    ok_v55('Typing URL added to JavaScript urls');
} else {
    ok_v55('Typing URL already exists in JavaScript');
}

if (! str_contains($js, 'const typingIndicator =')) {
    $search = "        const messagesBox = widget.querySelector('[data-chat-messages]');\n";
    $replace = $search . "        const typingIndicator = widget.querySelector('[data-chat-typing-indicator]');\n";
    if (! str_contains($js, $search)) {
        fail_v55('Could not find messagesBox marker in internal-chat.js');
    }
    $js = str_replace($search, $replace, $js);
    ok_v55('Typing indicator DOM reference added');
} else {
    ok_v55('Typing indicator DOM reference already exists');
}

if (! str_contains($js, 'typingStopTimer: null')) {
    $search = "            searchTimer: null,\n            selectedReference: null\n";
    $replace = "            searchTimer: null,\n            selectedReference: null,\n            typingStopTimer: null,\n            typingActive: false,\n            lastTypingSignalAt: 0\n";
    if (! str_contains($js, $search)) {
        fail_v55('Could not find state selectedReference marker in internal-chat.js');
    }
    $js = str_replace($search, $replace, $js);
    ok_v55('Typing state added');
} else {
    ok_v55('Typing state already exists');
}

if (! str_contains($js, 'function updateTypingIndicator(typingUsers)')) {
    $functions = <<<'JS_CODE'

        function updateTypingIndicator(typingUsers) {
            if (!typingIndicator) return;
            const users = Array.isArray(typingUsers) ? typingUsers.filter(Boolean) : [];
            if (!users.length) {
                typingIndicator.hidden = true;
                typingIndicator.textContent = '';
                return;
            }

            const names = users.map(function (user) {
                return String(user.name || 'مستخدم').trim();
            }).filter(Boolean).slice(0, 3);

            let text = '';
            if (names.length === 1) {
                text = names[0] + ' يكتب الآن...';
            } else if (names.length === 2) {
                text = names[0] + ' و' + names[1] + ' يكتبان الآن...';
            } else {
                text = names.join('، ') + ' يكتبون الآن...';
            }

            typingIndicator.hidden = false;
            typingIndicator.textContent = text;
        }

        async function sendTypingSignal(isTyping) {
            if (!urls.typing || !canSend || state.activeArchived) return;
            if (!state.activeConversationId && !state.activeUserId) return;

            const now = Date.now();
            if (isTyping && (now - state.lastTypingSignalAt) < 1800) return;
            if (isTyping) state.lastTypingSignalAt = now;

            const payload = { is_typing: !!isTyping };
            if (state.activeConversationId) payload.conversation_id = state.activeConversationId;
            else payload.receiver_id = state.activeUserId;

            try {
                await postJson(urls.typing, payload);
            } catch (error) {
                // Typing state is a soft UI hint; ignore failures silently.
            }
        }

        function queueTypingSignal() {
            if (!input || !canSend || state.activeArchived) return;
            const value = String(input.value || '').trim();
            if (!value) {
                clearTypingSignal(true);
                return;
            }

            state.typingActive = true;
            sendTypingSignal(true);

            window.clearTimeout(state.typingStopTimer);
            state.typingStopTimer = window.setTimeout(function () {
                clearTypingSignal(true);
            }, 4500);
        }

        function clearTypingSignal(force) {
            window.clearTimeout(state.typingStopTimer);
            state.typingStopTimer = null;
            if (force || state.typingActive) {
                state.typingActive = false;
                sendTypingSignal(false);
            }
        }
JS_CODE;
    $marker = "\n        function setUnreadTotal(total) {";
    if (! str_contains($js, $marker)) {
        fail_v55('Could not find setUnreadTotal marker in internal-chat.js');
    }
    $js = str_replace($marker, $functions . $marker, $js);
    ok_v55('Typing JavaScript functions inserted');
} else {
    ok_v55('Typing JavaScript functions already exist');
}

if (! str_contains($js, 'if (Array.isArray(data.typing_users)) updateTypingIndicator(data.typing_users);')) {
    $search = "            if (typeof data.unread_total !== 'undefined') {\n                state.lastUnreadTotal = parseInt(data.unread_total || 0, 10);\n                setUnreadTotal(state.lastUnreadTotal);\n            }\n";
    $replace = $search . "            if (Array.isArray(data.typing_users)) updateTypingIndicator(data.typing_users);\n";
    if (! str_contains($js, $search)) {
        fail_v55('Could not find updatePayload unread marker in internal-chat.js');
    }
    $js = str_replace($search, $replace, $js);
    ok_v55('Typing users handled in updatePayload');
} else {
    ok_v55('Typing users already handled in updatePayload');
}

if (! str_contains($js, 'clearTypingSignal(true);\n            state.loadingMessages = true;')) {
    $search = "            if (!thread || state.loadingMessages) return;\n            state.loadingMessages = true;\n";
    $replace = "            if (!thread || state.loadingMessages) return;\n            clearTypingSignal(true);\n            updateTypingIndicator([]);\n            state.loadingMessages = true;\n";
    if (! str_contains($js, $search)) {
        fail_v55('Could not find selectThread loading marker in internal-chat.js');
    }
    $js = str_replace($search, $replace, $js);
    ok_v55('Typing state cleared on thread switch');
} else {
    ok_v55('Typing state already cleared on thread switch');
}

if (! str_contains($js, 'clearTypingSignal(true);\n\n            const submitButton = form?.querySelector')) {
    $search = "            const body = String(input?.value || '').trim();\n            const reference = state.selectedReference;\n            if (!body && !reference) return;\n\n            const submitButton = form?.querySelector('button[type=\"submit\"]');\n";
    $replace = "            const body = String(input?.value || '').trim();\n            const reference = state.selectedReference;\n            if (!body && !reference) return;\n            clearTypingSignal(true);\n\n            const submitButton = form?.querySelector('button[type=\"submit\"]');\n";
    if (! str_contains($js, $search)) {
        fail_v55('Could not find sendMessage submit marker in internal-chat.js');
    }
    $js = str_replace($search, $replace, $js);
    ok_v55('Typing state cleared before send');
} else {
    ok_v55('Typing state already cleared before send');
}

if (! str_contains($js, 'else if (!shouldFetchActiveConversation) updateTypingIndicator([]);')) {
    $search = "                updatePayload(data);\n                if (shouldFetchActiveConversation && Array.isArray(data.messages) && data.messages.length) {\n";
    $replace = "                updatePayload(data);\n                if (shouldFetchActiveConversation && Array.isArray(data.typing_users)) updateTypingIndicator(data.typing_users);\n                else if (!shouldFetchActiveConversation) updateTypingIndicator([]);\n                if (shouldFetchActiveConversation && Array.isArray(data.messages) && data.messages.length) {\n";
    if (! str_contains($js, $search)) {
        fail_v55('Could not find poll updatePayload marker in internal-chat.js');
    }
    $js = str_replace($search, $replace, $js);
    ok_v55('Typing users handled in poll');
} else {
    ok_v55('Typing users already handled in poll');
}

if (! str_contains($js, 'clearTypingSignal(true);\n            state.open = false;')) {
    $search = "        function closePanel() {\n            state.open = false;\n";
    $replace = "        function closePanel() {\n            clearTypingSignal(true);\n            state.open = false;\n";
    if (! str_contains($js, $search)) {
        fail_v55('Could not find closePanel marker in internal-chat.js');
    }
    $js = str_replace($search, $replace, $js);
    ok_v55('Typing state cleared on close');
} else {
    ok_v55('Typing state already cleared on close');
}

if (! str_contains($js, "input.addEventListener('input', queueTypingSignal);")) {
    $search = "        if (input) {\n            input.addEventListener('keydown', function (event) {\n";
    $replace = "        if (input) {\n            input.addEventListener('input', queueTypingSignal);\n            input.addEventListener('keydown', function (event) {\n";
    if (! str_contains($js, $search)) {
        fail_v55('Could not find input keydown marker in internal-chat.js');
    }
    $js = str_replace($search, $replace, $js);
    ok_v55('Typing input event registered');
} else {
    ok_v55('Typing input event already registered');
}

write_file_v55($jsFile, $js);

// -----------------------------------------------------------------------------
// CSS patch
// -----------------------------------------------------------------------------
$cssFile = 'public/css/internal-chat.css';
$css = read_file_v55($cssFile);
if (! str_contains($css, 'DocumentArchive Internal Chat Typing Indicator V55')) {
    $css .= <<<'CSS'

/* DocumentArchive Internal Chat Typing Indicator V55 */
.internal-chat-typing-indicator {
    min-height: 24px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 18px 8px;
    color: rgba(96, 165, 250, .95);
    font-size: 12px;
    font-weight: 700;
    line-height: 1.7;
    letter-spacing: 0;
    white-space: nowrap;
}

.internal-chat-typing-indicator[hidden] {
    display: none !important;
}

.internal-chat-typing-indicator::before {
    content: '•••';
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 18px;
    border-radius: 999px;
    background: rgba(37, 99, 235, .12);
    color: currentColor;
    font-size: 13px;
    line-height: 1;
    animation: internal-chat-typing-pulse-v55 1.15s ease-in-out infinite;
}

@keyframes internal-chat-typing-pulse-v55 {
    0%, 100% { opacity: .45; transform: translateY(0); }
    50% { opacity: 1; transform: translateY(-1px); }
}

html[data-theme="dark"] .internal-chat-typing-indicator {
    color: rgba(147, 197, 253, .98);
}
CSS;
    ok_v55('Typing CSS appended to internal-chat.css');
} else {
    ok_v55('Typing CSS already exists');
}
write_file_v55($cssFile, $css);

// -----------------------------------------------------------------------------
// Syntax quick checks
// -----------------------------------------------------------------------------
$phpFiles = [
    $controllerFile,
    $routesFile,
    $widgetFile,
    $permissionFile,
];

foreach ($phpFiles as $file) {
    $path = path_v55($file);
    if (! file_exists($path)) {
        continue;
    }
    $cmd = PHP_BINARY . ' -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $output, $code);
    if ($code !== 0) {
        echo implode("\n", $output) . "\n";
        fail_v55("Syntax check failed: {$file}");
    }
}

ok_v55('Internal chat typing indicator V55 applied successfully.');

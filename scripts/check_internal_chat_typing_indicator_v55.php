<?php

$root = dirname(__DIR__);
$failed = false;

function check_ok_v55(string $message): void
{
    echo "[OK] {$message}\n";
}

function check_fail_v55(string $message): void
{
    global $failed;
    $failed = true;
    echo "[FAIL] {$message}\n";
}

function check_path_v55(string $relative): string
{
    global $root;
    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function check_file_v55(string $relative): string
{
    $path = check_path_v55($relative);
    if (! file_exists($path)) {
        check_fail_v55("File missing: {$relative}");
        return '';
    }
    check_ok_v55("File exists: {$relative}");
    $content = file_get_contents($path);
    return $content === false ? '' : $content;
}

function contains_v55(string $content, string $needle, string $label): void
{
    if (str_contains($content, $needle)) {
        check_ok_v55($label);
    } else {
        check_fail_v55($label);
    }
}

$controller = check_file_v55('app/Http/Controllers/InternalChatController.php');
$routes = check_file_v55('routes/web.php');
$middleware = check_file_v55('app/Http/Middleware/ApplyRoutePermissions.php');
$widget = check_file_v55('resources/views/partials/internal-chat-widget.blade.php');
$js = check_file_v55('public/js/internal-chat.js');
$css = check_file_v55('public/css/internal-chat.css');

contains_v55($controller, 'public function typing(Request $request): JsonResponse', 'Controller has typing endpoint method');
contains_v55($controller, 'typingUsersPayload(InternalChatConversation $conversation, int $currentUserId)', 'Controller has typing users payload helper');
contains_v55($controller, 'typingCacheKey(int $conversationId, int $userId)', 'Controller has typing cache key helper');
contains_v55($controller, 'Cache::put(', 'Controller stores typing state in cache');
contains_v55($controller, "'typing_users' =>", 'Controller returns typing_users payload');
contains_v55($controller, '$this->clearTyping((int) $conversation->id, $currentUserId);', 'Controller clears typing state when sending');

contains_v55($routes, "Route::post('/typing'", 'Typing POST route exists');
contains_v55($routes, "->name('typing')", 'Typing route name exists');
contains_v55($middleware, "'internal-chat.typing'", 'Permission middleware contains internal-chat.typing');

contains_v55($widget, 'data-typing-url="{{ route(\'internal-chat.typing\') }}"', 'Widget contains typing URL data attribute');
contains_v55($widget, 'data-chat-typing-indicator', 'Widget contains typing indicator element');

contains_v55($js, 'typing: widget.dataset.typingUrl', 'JavaScript reads typing URL');
contains_v55($js, 'const typingIndicator =', 'JavaScript references typing indicator DOM');
contains_v55($js, 'function updateTypingIndicator(typingUsers)', 'JavaScript renders typing indicator');
contains_v55($js, 'function queueTypingSignal()', 'JavaScript queues typing signal');
contains_v55($js, 'sendTypingSignal(true)', 'JavaScript sends typing=true');
contains_v55($js, 'sendTypingSignal(false)', 'JavaScript sends typing=false');
contains_v55($js, 'input.addEventListener(\'input\', queueTypingSignal);', 'Input event is registered for typing');
contains_v55($js, 'data.typing_users', 'Poll/update handles typing_users');

contains_v55($css, 'DocumentArchive Internal Chat Typing Indicator V55', 'CSS marker exists');
contains_v55($css, '.internal-chat-typing-indicator', 'Typing indicator CSS exists');
contains_v55($css, 'internal-chat-typing-pulse-v55', 'Typing animation CSS exists');

foreach ([
    'app/Http/Controllers/InternalChatController.php',
    'routes/web.php',
    'resources/views/partials/internal-chat-widget.blade.php',
    'app/Http/Middleware/ApplyRoutePermissions.php',
] as $relative) {
    $path = check_path_v55($relative);
    if (! file_exists($path)) {
        continue;
    }
    exec(PHP_BINARY . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    if ($code === 0) {
        check_ok_v55("PHP syntax valid: {$relative}");
    } else {
        check_fail_v55("PHP syntax invalid: {$relative}");
        echo implode("\n", $output) . "\n";
    }
}

if ($failed) {
    echo "\nInternal chat typing indicator V55 check failed.\n";
    exit(1);
}

echo "\nInternal chat typing indicator V55 check passed.\n";

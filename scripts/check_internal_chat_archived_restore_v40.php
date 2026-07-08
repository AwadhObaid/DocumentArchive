<?php

$root = dirname(__DIR__);

$checks = [
    'controller' => $root . '/app/Http/Controllers/InternalChatController.php',
    'routes' => $root . '/routes/web.php',
    'widget' => $root . '/resources/views/partials/internal-chat-widget.blade.php',
    'js' => $root . '/public/js/internal-chat.js',
    'css' => $root . '/public/css/internal-chat.css',
];

$errors = [];

foreach ($checks as $name => $path) {
    if (! is_file($path)) {
        $errors[] = "Missing required file [$name]: $path";
    }
}

if (! $errors) {
    $controller = file_get_contents($checks['controller']);
    $routes = file_get_contents($checks['routes']);
    $widget = file_get_contents($checks['widget']);
    $js = file_get_contents($checks['js']);
    $css = file_get_contents($checks['css']);

    $expectations = [
        'routes include archived list endpoint' => str_contains($routes, "InternalChatController::class, 'archivedConversations'") && str_contains($routes, "->name('archived')"),
        'routes include restore endpoint' => str_contains($routes, "InternalChatController::class, 'restoreConversation'") && str_contains($routes, "->name('conversations.restore')"),
        'controller has archivedConversations action' => str_contains($controller, 'public function archivedConversations(Request $request): JsonResponse'),
        'controller has restoreConversation action' => str_contains($controller, 'public function restoreConversation(Request $request, InternalChatConversation $conversation): JsonResponse'),
        'controller restores archived_at without deleting messages' => str_contains($controller, "'archived_at' => null") && str_contains($controller, 'تمت استعادة المحادثة إلى القائمة الرئيسية'),
        'controller has archived payload query' => str_contains($controller, 'private function archivedConversationsPayload') && str_contains($controller, "->whereNotNull('archived_at')"),
        'unread total ignores archived conversations' => str_contains($controller, "whereNull('deleted_at')->whereNull('archived_at')") || str_contains($controller, "->whereNull('archived_at')"),
        'widget contains archived data URL' => str_contains($widget, 'data-archived-url') && str_contains($widget, "route('internal-chat.archived')"),
        'widget contains restore URL template' => str_contains($widget, 'data-restore-url-template') && str_contains($widget, "route('internal-chat.conversations.restore'"),
        'widget contains archived toggle button' => str_contains($widget, 'data-chat-archives-toggle'),
        'widget contains restore button' => str_contains($widget, 'data-chat-restore'),
        'widget readiness includes cleared_at' => str_contains($widget, "Schema::hasColumn('internal_chat_participants', 'cleared_at')"),
        'js has archived conversations state' => str_contains($js, 'archivedConversations') && str_contains($js, 'showArchived'),
        'js can load archived conversations' => str_contains($js, 'loadArchivedConversations') && str_contains($js, 'urls.archived'),
        'js can restore archived conversation' => str_contains($js, 'restoreCurrentConversation') && str_contains($js, 'urls.restoreTemplate'),
        'js blocks sending before restore' => str_contains($js, 'استعد المحادثة المؤرشفة قبل إرسال رسالة جديدة'),
        'css styles archived toggle' => str_contains($css, 'internal-chat-archives-toggle'),
        'css styles restore action' => str_contains($css, 'internal-chat-action-btn.success'),
    ];

    foreach ($expectations as $label => $passed) {
        if (! $passed) {
            $errors[] = "Failed check: $label";
        }
    }
}

if ($errors) {
    echo "Internal chat archived restore V40 check failed:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
    exit(1);
}

echo "Internal chat archived restore V40 check passed.\n";
echo "Archived conversations can now be viewed and restored without deleting messages.\n";

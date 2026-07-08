<?php

$root = dirname(__DIR__);
$checks = [
    'controller advanced routes/methods' => [
        'file' => 'app/Http/Controllers/InternalChatController.php',
        'needles' => [
            'createGroup(Request $request)',
            'archiveConversation(Request $request, InternalChatConversation $conversation)',
            'deleteConversation(Request $request, InternalChatConversation $conversation)',
            'lookupDocuments(Request $request)',
            'lookupMemos(Request $request)',
            'conversationMessages(Request $request, InternalChatConversation $conversation)',
            'search(Request $request)',
            'touchPresence(int $currentUserId)',
        ],
    ],
    'conversation model' => [
        'file' => 'app/Models/InternalChatConversation.php',
        'needles' => ['class InternalChatConversation', 'participants(): HasMany', 'messages(): HasMany'],
    ],
    'participant model' => [
        'file' => 'app/Models/InternalChatParticipant.php',
        'needles' => ['class InternalChatParticipant', 'last_read_message_id', 'archived_at', 'deleted_at'],
    ],
    'chat message model references' => [
        'file' => 'app/Models/InternalChatMessage.php',
        'needles' => ['conversation_id', 'document_id', 'memo_id', 'conversation(): BelongsTo', 'document(): BelongsTo', 'memo(): BelongsTo'],
    ],
    'migration advanced schema' => [
        'file' => 'database/migrations/2026_07_08_103000_upgrade_internal_chat_advanced_v38.php',
        'needles' => ['internal_chat_conversations', 'internal_chat_participants', 'internal_chat_attachments', 'last_seen_at', 'backfillDirectConversations'],
    ],
    'routes advanced endpoints' => [
        'file' => 'routes/web.php',
        'needles' => [
            "groups.store",
            "conversations.archive",
            "conversations.delete",
            "lookup.documents",
            "lookup.memos",
            "name('search')",
        ],
    ],
    'widget advanced data urls' => [
        'file' => 'resources/views/partials/internal-chat-widget.blade.php',
        'needles' => ['data-group-store-url', 'data-archive-url-template', 'data-delete-url-template', 'data-document-lookup-url', 'data-memo-lookup-url', 'data-chat-message-search', 'data-chat-attach-toggle'],
    ],
    'javascript advanced client' => [
        'file' => 'public/js/internal-chat.js',
        'needles' => ['createGroup(event)', 'searchMessages()', 'hideCurrentConversation(mode)', 'lookupReference(type)', 'selectedReference', 'conversationMessagesTemplate'],
    ],
    'css advanced styles' => [
        'file' => 'public/css/internal-chat.css',
        'needles' => ['v38: advanced chat', 'internal-chat-message-search', 'internal-chat-reference', 'internal-chat-group-form', 'internal-chat-avatar.online'],
    ],
    'user presence field' => [
        'file' => 'app/Models/User.php',
        'needles' => ['last_seen_at', 'internalChatParticipants'],
    ],
];

$failed = false;
foreach ($checks as $label => $check) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $check['file']);
    if (! is_file($path)) {
        echo "[FAIL] {$label}: missing file {$check['file']}\n";
        $failed = true;
        continue;
    }
    $content = file_get_contents($path);
    foreach ($check['needles'] as $needle) {
        if (strpos($content, $needle) === false) {
            echo "[FAIL] {$label}: missing marker {$needle}\n";
            $failed = true;
        }
    }
    if (! $failed) {
        echo "[OK] {$label}\n";
    }
}

if ($failed) {
    echo "\nInternal chat advanced V38 check failed.\n";
    exit(1);
}

echo "\nInternal chat advanced V38 check passed.\n";

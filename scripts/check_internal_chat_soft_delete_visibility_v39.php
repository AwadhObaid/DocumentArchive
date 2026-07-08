<?php

$root = dirname(__DIR__);

$checks = [
    'controller' => $root . '/app/Http/Controllers/InternalChatController.php',
    'participant_model' => $root . '/app/Models/InternalChatParticipant.php',
    'js' => $root . '/public/js/internal-chat.js',
    'migration' => $root . '/database/migrations/2026_07_08_111000_fix_internal_chat_soft_delete_visibility_v39.php',
];

$errors = [];

foreach ($checks as $name => $path) {
    if (! is_file($path)) {
        $errors[] = "Missing required file [$name]: $path";
    }
}

if (! $errors) {
    $controller = file_get_contents($checks['controller']);
    $model = file_get_contents($checks['participant_model']);
    $js = file_get_contents($checks['js']);
    $migration = file_get_contents($checks['migration']);

    $expectations = [
        'controller requires cleared_at in advancedReady' => str_contains($controller, "Schema::hasColumn('internal_chat_participants', 'cleared_at')"),
        'delete mode stores cleared_at instead of deleted_at' => str_contains($controller, "'cleared_at' => now()") && str_contains($controller, "'deleted_at' => null"),
        'visible messages respect participant cleared_at' => str_contains($controller, 'visibleConversationMessageQuery') && str_contains($controller, '$clearedAt = $participant?->cleared_at') && str_contains($controller, "->where('created_at', '>'"),
        'direct/user latest preview uses visible messages' => str_contains($controller, '$this->visibleConversationMessageQuery($conversation, $currentUserId, $participant)'),
        'participant model casts cleared_at' => str_contains($model, "'cleared_at'") && str_contains($model, "'cleared_at' => 'datetime'"),
        'migration adds cleared_at' => str_contains($migration, "Schema::hasColumn('internal_chat_participants', 'cleared_at')") && str_contains($migration, "->timestamp('cleared_at')"),
        'migration converts previous V38 visual deletes' => str_contains($migration, 'convertWrongVisualDeletes') && str_contains($migration, "'deleted_at' => null") && str_contains($migration, "'archived_at' => null"),
        'javascript no longer removes direct users on visual delete' => ! str_contains($js, 'if (!user || user.deleted) return;'),
        'javascript keeps active conversation after visual delete' => str_contains($js, "if (mode === 'delete')") && str_contains($js, 'تم مسح سجل المحادثة ظاهريًا'),
    ];

    foreach ($expectations as $label => $passed) {
        if (! $passed) {
            $errors[] = "Failed check: $label";
        }
    }
}

if ($errors) {
    echo "Internal chat soft delete visibility V39 check failed:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
    exit(1);
}

echo "Internal chat soft delete visibility V39 check passed.\n";
echo "Visual delete now clears old visible history only and keeps the user/group in the chat list.\n";

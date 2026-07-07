<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requiredFiles = [
    'app/Http/Controllers/InternalChatController.php',
    'app/Models/InternalChatMessage.php',
    'database/migrations/2026_07_07_140000_create_internal_chat_messages_table.php',
    'resources/views/partials/internal-chat-widget.blade.php',
    'public/css/internal-chat.css',
    'public/js/internal-chat.js',
];

$requiredSnippets = [
    'routes/web.php' => [
        "use App\\Http\\Controllers\\InternalChatController;",
        "Route::prefix('internal-chat')->name('internal-chat.')->group",
        "Route::get('/bootstrap', [InternalChatController::class, 'bootstrap'])->name('bootstrap')",
        "Route::post('/messages', [InternalChatController::class, 'send'])->name('send')",
    ],
    'app/Http/Middleware/ApplyRoutePermissions.php' => [
        "'internal-chat.bootstrap' => 'internal_chat.view'",
        "'internal-chat.send' => 'internal_chat.send'",
    ],
    'app/Support/PermissionRegistry.php' => [
        "'key' => 'internal_chat'",
        "'internal_chat.view' => 'عرض نافذة الدردشة الداخلية'",
        "'internal_chat.send' => 'إرسال رسائل دردشة داخلية'",
    ],
    'app/Http/Controllers/SettingsController.php' => [
        "'internal_chat_enabled' => '1'",
        "'internal_chat_poll_seconds' => '5'",
    ],
    'resources/views/settings/edit.blade.php' => [
        'إعدادات الدردشة الداخلية العائمة',
        'name="internal_chat_enabled"',
        'name="internal_chat_poll_seconds"',
    ],
    'resources/views/layouts/app.blade.php' => [
        "css/internal-chat.css",
        "partials.internal-chat-widget",
        "js/internal-chat.js",
    ],
];

$errors = [];

foreach ($requiredFiles as $file) {
    if (! is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file))) {
        $errors[] = "Missing file: {$file}";
    }
}

foreach ($requiredSnippets as $file => $snippets) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (! is_file($path)) {
        $errors[] = "Missing checked file: {$file}";
        continue;
    }

    $content = file_get_contents($path) ?: '';
    foreach ($snippets as $snippet) {
        if (! str_contains($content, $snippet)) {
            $errors[] = "Missing snippet in {$file}: {$snippet}";
        }
    }
}

if ($errors !== []) {
    echo "❌ فشل فحص تحديث الدردشة الداخلية العائمة V31\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
    exit(1);
}

echo "✅ تم فحص تحديث الدردشة الداخلية العائمة V31 بنجاح.\n";
echo "الخطوة التالية: php artisan migrate ثم تنظيف كاش Laravel.\n";

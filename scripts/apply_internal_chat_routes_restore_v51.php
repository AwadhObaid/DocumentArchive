<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$routesPath = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';

function v51_fail(string $message): void
{
    fwrite(STDERR, "[FAIL] {$message}" . PHP_EOL);
    exit(1);
}

function v51_ok(string $message): void
{
    echo "[OK] {$message}" . PHP_EOL;
}

if (!is_file($routesPath)) {
    v51_fail("routes/web.php not found: {$routesPath}");
}

$contents = file_get_contents($routesPath);
if ($contents === false) {
    v51_fail("Unable to read routes/web.php");
}

$routeLines = [
    'conversations.messages' => "        Route::get('/conversations/{conversation}/messages', [InternalChatController::class, 'conversationMessages'])->name('conversations.messages');",
    'groups.store' => "        Route::post('/groups', [InternalChatController::class, 'createGroup'])->name('groups.store');",
    'archived' => "        Route::get('/archived', [InternalChatController::class, 'archivedConversations'])->name('archived');",
    'conversations.archive' => "        Route::post('/conversations/{conversation}/archive', [InternalChatController::class, 'archiveConversation'])->name('conversations.archive');",
    'conversations.restore' => "        Route::post('/conversations/{conversation}/restore', [InternalChatController::class, 'restoreConversation'])->name('conversations.restore');",
    'conversations.delete' => "        Route::post('/conversations/{conversation}/delete', [InternalChatController::class, 'deleteConversation'])->name('conversations.delete');",
    'search' => "        Route::get('/search', [InternalChatController::class, 'search'])->name('search');",
    'lookup.documents' => "        Route::get('/lookup/documents', [InternalChatController::class, 'lookupDocuments'])->name('lookup.documents');",
    'lookup.memos' => "        Route::get('/lookup/memos', [InternalChatController::class, 'lookupMemos'])->name('lookup.memos');",
];

$missingLines = [];
foreach ($routeLines as $routeName => $line) {
    if (!str_contains($contents, "->name('{$routeName}')") && !str_contains($contents, '->name("' . $routeName . '")')) {
        $missingLines[] = $line;
    }
}

if ($missingLines === []) {
    v51_ok('Advanced internal chat routes already exist. Nothing to patch.');
    exit(0);
}

$anchor = "        Route::get('/messages/{user}', [InternalChatController::class, 'messages'])->name('messages');";

if (str_contains($contents, $anchor)) {
    $contents = str_replace($anchor, $anchor . PHP_EOL . implode(PHP_EOL, $missingLines), $contents);
} else {
    $groupStart = strpos($contents, "Route::prefix('internal-chat')->name('internal-chat.')->group(function () {");
    if ($groupStart === false) {
        $groupStart = strpos($contents, 'Route::prefix("internal-chat")->name("internal-chat.")->group(function () {');
    }

    if ($groupStart === false) {
        v51_fail('Could not find internal-chat route group in routes/web.php');
    }

    $nextGroupMarker = strpos($contents, "Route::get('/internal-messages'", $groupStart);
    if ($nextGroupMarker === false) {
        v51_fail('Could not find insertion boundary after internal-chat route group.');
    }

    $beforeNextGroup = substr($contents, 0, $nextGroupMarker);
    $lastClose = strrpos($beforeNextGroup, "    });");
    if ($lastClose === false || $lastClose < $groupStart) {
        v51_fail('Could not find internal-chat group closing line.');
    }

    $contents = substr($contents, 0, $lastClose)
        . implode(PHP_EOL, $missingLines) . PHP_EOL
        . substr($contents, $lastClose);
}

$backupPath = $routesPath . '.before-v51-' . date('Ymd_His') . '.bak';
if (!copy($routesPath, $backupPath)) {
    v51_fail("Unable to create backup: {$backupPath}");
}

if (file_put_contents($routesPath, $contents) === false) {
    v51_fail('Unable to write patched routes/web.php');
}

v51_ok('routes/web.php patched with advanced internal chat routes.');
v51_ok("Backup created: {$backupPath}");

require __DIR__ . DIRECTORY_SEPARATOR . 'check_internal_chat_routes_restore_v51.php';

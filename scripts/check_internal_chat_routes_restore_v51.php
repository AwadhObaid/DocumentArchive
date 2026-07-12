<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$routesPath = $root . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
$controllerPath = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'InternalChatController.php';
$widgetPath = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'internal-chat-widget.blade.php';

$failed = false;

function check_ok(string $message): void
{
    echo "[OK] {$message}" . PHP_EOL;
}

function check_fail(string $message): void
{
    global $failed;
    $failed = true;
    echo "[FAIL] {$message}" . PHP_EOL;
}

function file_contains(string $path, string $needle, string $label): void
{
    if (!is_file($path)) {
        check_fail("Missing file: {$path}");
        return;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        check_fail("Unable to read: {$path}");
        return;
    }

    if (str_contains($contents, $needle)) {
        check_ok("{$label} contains: {$needle}");
    } else {
        check_fail("{$label} missing: {$needle}");
    }
}

$requiredRoutes = [
    "->name('conversations.messages')",
    "->name('groups.store')",
    "->name('archived')",
    "->name('conversations.archive')",
    "->name('conversations.restore')",
    "->name('conversations.delete')",
    "->name('search')",
    "->name('lookup.documents')",
    "->name('lookup.memos')",
];

foreach ($requiredRoutes as $needle) {
    file_contains($routesPath, $needle, 'routes/web.php');
}

$requiredMethods = [
    'function conversationMessages',
    'function createGroup',
    'function archivedConversations',
    'function archiveConversation',
    'function restoreConversation',
    'function deleteConversation',
    'function search',
    'function lookupDocuments',
    'function lookupMemos',
];

foreach ($requiredMethods as $needle) {
    file_contains($controllerPath, $needle, 'InternalChatController.php');
}

file_contains($widgetPath, "internal-chat.conversations.messages", 'internal-chat-widget.blade.php');

if (is_file($routesPath)) {
    $lintCommand = 'php -l ' . escapeshellarg($routesPath) . ' 2>&1';
    $lintOutput = trim((string) shell_exec($lintCommand));
    if (str_contains($lintOutput, 'No syntax errors detected')) {
        check_ok('routes/web.php syntax is valid.');
    } else {
        check_fail('routes/web.php syntax check failed: ' . $lintOutput);
    }
}

if ($failed) {
    echo PHP_EOL . 'Internal chat routes restore V51 check failed.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Internal chat routes restore V51 check passed.' . PHP_EOL;

<?php

$root = realpath(__DIR__ . '/..');
$failed = false;

function ok($message) { echo "[OK] {$message}\n"; }
function fail_check($message) { global $failed; $failed = true; echo "[FAIL] {$message}\n"; }
function file_contains($path, $needle, $label) {
    if (!file_exists($path)) { fail_check("File missing: {$path}"); return; }
    $content = file_get_contents($path);
    if (str_contains($content, $needle)) { ok("{$label} contains: {$needle}"); }
    else { fail_check("{$label} missing: {$needle}"); }
}

$files = [
    'app/Http/Controllers/InternalChatAdminController.php',
    'routes/web.php',
    'app/Http/Middleware/ApplyRoutePermissions.php',
    'app/Support/PermissionRegistry.php',
    'resources/views/settings/edit.blade.php',
    'app/Models/ActivityLog.php',
];

foreach ($files as $file) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (file_exists($path)) ok("File exists: {$file}"); else fail_check("File missing: {$file}");
}

file_contains($root . '/app/Http/Controllers/InternalChatAdminController.php', 'function backup', 'InternalChatAdminController.php');
file_contains($root . '/app/Http/Controllers/InternalChatAdminController.php', 'function restoreBackup', 'InternalChatAdminController.php');
file_contains($root . '/app/Http/Controllers/InternalChatAdminController.php', 'function restoreDeleted', 'InternalChatAdminController.php');
file_contains($root . '/app/Http/Controllers/InternalChatAdminController.php', 'function purgeDeleted', 'InternalChatAdminController.php');
file_contains($root . '/app/Http/Controllers/InternalChatAdminController.php', 'internal_chat_backup_v56', 'InternalChatAdminController.php');
file_contains($root . '/app/Http/Controllers/InternalChatAdminController.php', 'حذف نهائي', 'InternalChatAdminController.php');
file_contains($root . '/app/Http/Controllers/InternalChatAdminController.php', 'auto_before_purge', 'InternalChatAdminController.php');

file_contains($root . '/routes/web.php', 'settings.internal-chat.', 'routes/web.php');
file_contains($root . '/routes/web.php', "->name('backup')", 'routes/web.php');
file_contains($root . '/routes/web.php', "->name('restore-backup')", 'routes/web.php');
file_contains($root . '/routes/web.php', "->name('restore-deleted')", 'routes/web.php');
file_contains($root . '/routes/web.php', "->name('purge-deleted')", 'routes/web.php');

file_contains($root . '/app/Http/Middleware/ApplyRoutePermissions.php', "'settings.internal-chat.backup' => 'internal_chat.backup'", 'ApplyRoutePermissions.php');
file_contains($root . '/app/Http/Middleware/ApplyRoutePermissions.php', "'settings.internal-chat.restore-backup' => 'internal_chat.restore_backup'", 'ApplyRoutePermissions.php');
file_contains($root . '/app/Http/Middleware/ApplyRoutePermissions.php', "'settings.internal-chat.restore-deleted' => 'internal_chat.restore_deleted'", 'ApplyRoutePermissions.php');
file_contains($root . '/app/Http/Middleware/ApplyRoutePermissions.php', "'settings.internal-chat.purge-deleted' => 'internal_chat.force_delete'", 'ApplyRoutePermissions.php');

file_contains($root . '/app/Support/PermissionRegistry.php', 'internal_chat.backup', 'PermissionRegistry.php');
file_contains($root . '/app/Support/PermissionRegistry.php', 'internal_chat.restore_backup', 'PermissionRegistry.php');
file_contains($root . '/app/Support/PermissionRegistry.php', 'internal_chat.restore_deleted', 'PermissionRegistry.php');
file_contains($root . '/app/Support/PermissionRegistry.php', 'internal_chat.force_delete', 'PermissionRegistry.php');

file_contains($root . '/resources/views/settings/edit.blade.php', 'إدارة الدردشة الداخلية', 'settings/edit.blade.php');
file_contains($root . '/resources/views/settings/edit.blade.php', "route('settings.internal-chat.backup')", 'settings/edit.blade.php');
file_contains($root . '/resources/views/settings/edit.blade.php', "route('settings.internal-chat.restore-backup')", 'settings/edit.blade.php');
file_contains($root . '/resources/views/settings/edit.blade.php', "route('settings.internal-chat.restore-deleted')", 'settings/edit.blade.php');
file_contains($root . '/resources/views/settings/edit.blade.php', "route('settings.internal-chat.purge-deleted')", 'settings/edit.blade.php');

file_contains($root . '/app/Models/ActivityLog.php', 'internal_chat.backup_created', 'ActivityLog.php');
file_contains($root . '/app/Models/ActivityLog.php', 'internal_chat.backup_restored', 'ActivityLog.php');
file_contains($root . '/app/Models/ActivityLog.php', 'internal_chat.deleted_restored', 'ActivityLog.php');
file_contains($root . '/app/Models/ActivityLog.php', 'internal_chat.deleted_purged', 'ActivityLog.php');

$phpFiles = [
    'app/Http/Controllers/InternalChatAdminController.php',
    'routes/web.php',
    'app/Http/Middleware/ApplyRoutePermissions.php',
    'app/Support/PermissionRegistry.php',
    'app/Models/ActivityLog.php',
];

foreach ($phpFiles as $file) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (file_exists($path)) {
        $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
        exec($cmd, $output, $code);
        if ($code === 0) ok("PHP syntax valid: {$file}");
        else fail_check("PHP syntax invalid: {$file} => " . implode(' ', $output));
    }
}

if ($failed) {
    echo "\nInternal chat admin management V56 check failed.\n";
    exit(1);
}

echo "\nInternal chat admin management V56 check passed.\n";

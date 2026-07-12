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

$controller = $root . '/app/Http/Controllers/InternalChatAdminController.php';
$view = $root . '/resources/views/settings/edit.blade.php';

foreach ([$controller => 'InternalChatAdminController.php', $view => 'settings/edit.blade.php'] as $path => $label) {
    if (file_exists($path)) ok("File exists: {$label}"); else fail_check("File missing: {$label}");
}

file_contains($controller, "with('internal_chat_admin_error'", 'InternalChatAdminController.php');
file_contains($controller, "withInput()", 'InternalChatAdminController.php');
file_contains($controller, "عبارة التأكيد غير صحيحة. اكتب العبارة كما هي: حذف نهائي", 'InternalChatAdminController.php');
file_contains($controller, "with('internal_chat_admin_success'", 'InternalChatAdminController.php');

file_contains($view, 'data-da-confirm-form', 'settings/edit.blade.php');
file_contains($view, 'daSettingsConfirmModal', 'settings/edit.blade.php');
file_contains($view, 'data-da-required-confirmation="حذف نهائي"', 'settings/edit.blade.php');
file_contains($view, 'internal-chat-admin-field-error', 'settings/edit.blade.php');
file_contains($view, 'internal-chat-admin-alert is-error', 'settings/edit.blade.php');
file_contains($view, 'عبارة التأكيد غير صحيحة. اكتب العبارة كما هي: حذف نهائي', 'settings/edit.blade.php');
file_contains($view, 'تأكيد وتنفيذ', 'settings/edit.blade.php');
file_contains($view, 'إلغاء', 'settings/edit.blade.php');

if (file_exists($view)) {
    $content = file_get_contents($view);
    if (str_contains($content, 'return confirm(') || str_contains($content, 'onclick="return confirm') || str_contains($content, "confirm('")) {
        fail_check('Browser confirm usage still exists in settings/edit.blade.php');
    } else {
        ok('Browser confirm usage removed from settings/edit.blade.php');
    }
}

if (file_exists($controller)) {
    $cmd = 'php -l ' . escapeshellarg($controller) . ' 2>&1';
    exec($cmd, $output, $code);
    if ($code === 0) ok('PHP syntax valid: InternalChatAdminController.php');
    else fail_check('PHP syntax invalid: InternalChatAdminController.php => ' . implode(' ', $output));
}

if ($failed) {
    echo "\nInternal chat admin confirm UI V57 check failed.\n";
    exit(1);
}

echo "\nInternal chat admin confirm UI V57 check passed.\n";

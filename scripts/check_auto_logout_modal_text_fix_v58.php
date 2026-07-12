<?php

$root = realpath(__DIR__ . '/..');
$appPath = $root . DIRECTORY_SEPARATOR . 'resources/views/layouts/app.blade.php';
$cssPath = $root . DIRECTORY_SEPARATOR . 'public/css/auto-logout-modal-text-fix-v58.css';

$failed = false;

function ar_check_v58(string $json): string
{
    return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
}

function check_ok_v58(string $message): void
{
    echo "[OK] {$message}\n";
}

function check_fail_v58(string $message): void
{
    global $failed;
    $failed = true;
    echo "[FAIL] {$message}\n";
}

function file_contains_v58(string $path, string $needle, string $label): void
{
    $content = file_exists($path) ? file_get_contents($path) : '';
    if ($content !== false && str_contains($content, $needle)) {
        check_ok_v58("{$label} contains: {$needle}");
    } else {
        check_fail_v58("{$label} missing: {$needle}");
    }
}

if (file_exists($appPath)) {
    check_ok_v58('app.blade.php exists');
} else {
    check_fail_v58('app.blade.php missing');
}

if (file_exists($cssPath)) {
    check_ok_v58('auto-logout-modal-text-fix-v58.css exists');
} else {
    check_fail_v58('auto-logout-modal-text-fix-v58.css missing');
}

$app = file_exists($appPath) ? file_get_contents($appPath) : '';
$css = file_exists($cssPath) ? file_get_contents($cssPath) : '';

$title = ar_check_v58('"\u062a\u0646\u0628\u064a\u0647 \u0627\u0646\u062a\u0647\u0627\u0621 \u0627\u0644\u062c\u0644\u0633\u0629"');
$stay = ar_check_v58('"\u0645\u062a\u0627\u0628\u0639\u0629 \u0627\u0644\u0639\u0645\u0644"');
$logoutNow = ar_check_v58('"\u062a\u0633\u062c\u064a\u0644 \u0627\u0644\u062e\u0631\u0648\u062c \u0627\u0644\u0622\u0646"');
$body = ar_check_v58('"\u0644\u0645 \u064a\u062a\u0645 \u0631\u0635\u062f \u0646\u0634\u0627\u0637 \u0641\u064a \u0627\u0644\u0646\u0638\u0627\u0645"');

foreach ([$title, $stay, $logoutNow, $body] as $text) {
    file_contains_v58($appPath, $text, 'app.blade.php');
}

file_contains_v58($appPath, 'id="autoLogoutTitle"', 'app.blade.php');
file_contains_v58($appPath, 'aria-labelledby="autoLogoutTitle"', 'app.blade.php');
file_contains_v58($appPath, 'data-auto-logout-stay', 'app.blade.php');
file_contains_v58($appPath, 'data-auto-logout-now', 'app.blade.php');
file_contains_v58($appPath, 'auto-logout-modal-text-fix-v58:start', 'app.blade.php');
file_contains_v58($appPath, 'css/auto-logout-modal-text-fix-v58.css', 'app.blade.php');
file_contains_v58($appPath, 'auto-logout-modal-text-fix-v58:end', 'app.blade.php');

$count = substr_count($app ?: '', 'css/auto-logout-modal-text-fix-v58.css');
if ($count === 1) {
    check_ok_v58('V58 CSS include appears exactly once');
} else {
    check_fail_v58("V58 CSS include count is {$count}, expected 1");
}

foreach (['word-break: keep-all !important', 'overflow-wrap: normal !important', 'white-space: nowrap !important', 'unicode-bidi: isolate'] as $needle) {
    file_contains_v58($cssPath, $needle, 'auto-logout-modal-text-fix-v58.css');
}

$badTokens = ['ط§', 'ظ„', 'ًں', 'âک', 'âڑ'];
$modalStart = strpos($app ?: '', '<div class="auto-logout-modal"');
$modalEnd = strpos($app ?: '', "@include('partials.internal-chat-widget')", $modalStart === false ? 0 : $modalStart);
$modalBlock = ($modalStart !== false && $modalEnd !== false) ? substr($app, $modalStart, $modalEnd - $modalStart) : '';

foreach ($badTokens as $token) {
    if ($modalBlock !== '' && str_contains($modalBlock, $token)) {
        check_fail_v58("Auto logout modal still contains mojibake token: {$token}");
    }
}

if (!$failed) {
    echo "\nAuto logout modal text fix V58 check passed.\n";
    exit(0);
}

echo "\nAuto logout modal text fix V58 check failed.\n";
exit(1);

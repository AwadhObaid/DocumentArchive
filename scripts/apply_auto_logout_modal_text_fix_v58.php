<?php

$root = realpath(__DIR__ . '/..');
$appPath = $root . DIRECTORY_SEPARATOR . 'resources/views/layouts/app.blade.php';
$cssPath = $root . DIRECTORY_SEPARATOR . 'public/css/auto-logout-modal-text-fix-v58.css';

function fail_v58(string $message): void
{
    echo "[FAIL] {$message}\n";
    exit(1);
}

function ok_v58(string $message): void
{
    echo "[OK] {$message}\n";
}

function ar_v58(string $json): string
{
    return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
}

if (!file_exists($appPath)) {
    fail_v58('app.blade.php not found');
}

if (!file_exists($cssPath)) {
    fail_v58('auto-logout-modal-text-fix-v58.css not found');
}

$content = file_get_contents($appPath);
if ($content === false) {
    fail_v58('Unable to read app.blade.php');
}

$title = ar_v58('"\u062a\u0646\u0628\u064a\u0647 \u0627\u0646\u062a\u0647\u0627\u0621 \u0627\u0644\u062c\u0644\u0633\u0629"');
$bodyBefore = ar_v58('"\u0644\u0645 \u064a\u062a\u0645 \u0631\u0635\u062f \u0646\u0634\u0627\u0637 \u0641\u064a \u0627\u0644\u0646\u0638\u0627\u0645. \u0633\u064a\u062a\u0645 \u062a\u0633\u062c\u064a\u0644 \u0627\u0644\u062e\u0631\u0648\u062c \u062a\u0644\u0642\u0627\u0626\u064a\u064b\u0627 \u062e\u0644\u0627\u0644"');
$seconds = ar_v58('"\u062b\u0627\u0646\u064a\u0629"');
$stay = ar_v58('"\u0645\u062a\u0627\u0628\u0639\u0629 \u0627\u0644\u0639\u0645\u0644"');
$logoutNow = ar_v58('"\u062a\u0633\u062c\u064a\u0644 \u0627\u0644\u062e\u0631\u0648\u062c \u0627\u0644\u0622\u0646"');

// Remove duplicated/older V58 include blocks if the script is run more than once.
$content = preg_replace(
    '/\s*\{\{--\s*auto-logout-modal-text-fix-v58:start\s*--\}\}.*?\{\{--\s*auto-logout-modal-text-fix-v58:end\s*--\}\}/s',
    '',
    $content
);

$includeBlock = <<<'BLADE'
    {{-- auto-logout-modal-text-fix-v58:start --}}
    <link rel="stylesheet" href="{{ asset('css/auto-logout-modal-text-fix-v58.css') }}?v=58">
    {{-- auto-logout-modal-text-fix-v58:end --}}
BLADE;

if (!str_contains($content, '</head>')) {
    fail_v58('</head> not found in app.blade.php');
}
$content = str_replace('</head>', $includeBlock . "\n</head>", $content);

$modal = <<<BLADE
<div class="auto-logout-modal" id="autoLogoutModal" aria-hidden="true">
    <div class="auto-logout-card" role="dialog" aria-modal="true" aria-labelledby="autoLogoutTitle">
        <div class="auto-logout-icon">🔒</div>
        <div>
            <h3 id="autoLogoutTitle">{$title}</h3>
            <p>{$bodyBefore} <strong data-auto-logout-countdown>60</strong> {$seconds}.</p>
            <div class="auto-logout-actions">
                <button type="button" class="btn btn-primary" data-auto-logout-stay>{$stay}</button>
                <button type="button" class="btn btn-secondary" data-auto-logout-now>{$logoutNow}</button>
            </div>
        </div>
    </div>
</div>
BLADE;

$includeNeedle = "@include('partials.internal-chat-widget')";
$modalStart = strpos($content, '<div class="auto-logout-modal"');
$includePos = strpos($content, $includeNeedle);

if ($modalStart === false) {
    fail_v58('auto logout modal start not found');
}

if ($includePos === false || $includePos <= $modalStart) {
    fail_v58("@include('partials.internal-chat-widget') not found after auto logout modal");
}

$content = substr($content, 0, $modalStart)
    . $modal . "\n\n"
    . substr($content, $includePos);

if (file_put_contents($appPath, $content) === false) {
    fail_v58('Unable to write app.blade.php');
}

ok_v58('app.blade.php updated with clean Arabic auto logout modal and V58 CSS include.');

require __DIR__ . '/check_auto_logout_modal_text_fix_v58.php';

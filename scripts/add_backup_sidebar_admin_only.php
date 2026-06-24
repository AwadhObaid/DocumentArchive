<?php

$projectRoot = realpath(__DIR__ . '/..');
$layoutPath = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.blade.php';

if (!file_exists($layoutPath)) {
    fwrite(STDERR, "Layout file not found: {$layoutPath}" . PHP_EOL);
    exit(1);
}

$content = file_get_contents($layoutPath);

if ($content === false) {
    fwrite(STDERR, "Unable to read layout file." . PHP_EOL);
    exit(1);
}

if (str_contains($content, 'BACKUP-SIDEBAR-ADMIN-ONLY')) {
    echo "Backup admin-only sidebar block already exists." . PHP_EOL;
    exit(0);
}

$backupPath = $layoutPath . '.bak_' . date('Ymd_His');
copy($layoutPath, $backupPath);

// Remove earlier backup sidebar attempts to avoid duplicate links.
$content = preg_replace(
    '/\s*\{\{--\s*BACKUP-SIDEBAR-[A-Z0-9_-]+\s*--\}\}\s*<a\b[^>]*(?:backups)[\s\S]*?<\/a>\s*/u',
    PHP_EOL,
    $content
) ?? $content;

$content = preg_replace(
    '/\s*<a\s+href="\{\{\s*(?:url\(\s*[\'\"]\/backups[\'\"]\s*\)|route\(\s*[\'\"]backups\.index[\'\"]\s*\))\s*\}\}"[\s\S]*?<\/a>\s*/u',
    PHP_EOL,
    $content
) ?? $content;

$backupBlock = <<<'BLADE'

            {{-- BACKUP-SIDEBAR-ADMIN-ONLY --}}
            @if(auth()->user()?->role === 'admin')
                <a href="{{ url('/backups') }}" class="{{ request()->is('backups*') ? 'active' : '' }}">💾 النسخ الاحتياطي</a>
            @endif
BLADE;

$inserted = false;

// Preferred: insert before the users admin block, so admin-only items stay together.
$usersPos = strpos($content, "route('users.index')");
if ($usersPos !== false) {
    $beforeUsers = substr($content, 0, $usersPos);
    $ifPos = strrpos($beforeUsers, "@if(auth()->user()?->role === 'admin')");
    if ($ifPos !== false) {
        $content = substr($content, 0, $ifPos) . $backupBlock . PHP_EOL . substr($content, $ifPos);
        $inserted = true;
    }
}

// Fallback: insert after settings link.
if (!$inserted) {
    $settingsPos = strpos($content, "route('settings.edit')");
    if ($settingsPos !== false) {
        $endLink = strpos($content, '</a>', $settingsPos);
        if ($endLink !== false) {
            $endLink += 4;
            $content = substr($content, 0, $endLink) . $backupBlock . substr($content, $endLink);
            $inserted = true;
        }
    }
}

// Fallback: insert before closing nav.
if (!$inserted) {
    $navEnd = strrpos($content, '</nav>');
    if ($navEnd !== false) {
        $content = substr($content, 0, $navEnd) . $backupBlock . PHP_EOL . substr($content, $navEnd);
        $inserted = true;
    }
}

if (!$inserted) {
    fwrite(STDERR, "Could not detect sidebar location. Backup was created at: {$backupPath}" . PHP_EOL);
    exit(1);
}

if (file_put_contents($layoutPath, $content) === false) {
    fwrite(STDERR, "Unable to write layout file." . PHP_EOL);
    exit(1);
}

echo "Backup sidebar admin-only link added successfully." . PHP_EOL;
echo "Backup copy created: {$backupPath}" . PHP_EOL;

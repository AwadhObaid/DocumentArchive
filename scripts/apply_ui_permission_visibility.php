<?php

/**
 * DocumentArchive - UI permission visibility patch
 *
 * This script hides sidebar links and action buttons from Blade views according
 * to the same permissions enforced by ApplyRoutePermissions middleware.
 */

$root = dirname(__DIR__);
$viewsRoot = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';

if (!is_dir($viewsRoot)) {
    fwrite(STDERR, "resources/views directory was not found. Run this script from the project root.\n");
    exit(1);
}

$bladeFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($viewsRoot, FilesystemIterator::SKIP_DOTS)
);

$changed = [];
$skipped = [];

foreach ($bladeFiles as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $path = $file->getPathname();
    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);

    // Avoid touching login/error/pdf print-only views unless they contain normal route actions.
    $content = file_get_contents($path);
    $original = $content;

    $content = normalizeOldAdminChecks($content);
    $content = wrapRouteDrivenElements($content);
    $content = wrapPrintOnlyButtons($content, $relative);

    if ($content !== $original) {
        $backupPath = $path . '.before-ui-permissions.bak';
        if (!file_exists($backupPath)) {
            file_put_contents($backupPath, $original);
        }
        file_put_contents($path, $content);
        $changed[] = $relative;
    } else {
        $skipped[] = $relative;
    }
}

ksort($changed);

echo "UI permission visibility patch completed.\n";
if ($changed !== []) {
    echo "Updated files:\n";
    foreach ($changed as $file) {
        echo "- {$file}\n";
    }
} else {
    echo "No Blade files needed changes.\n";
}

echo "\nNext commands:\n";
echo "php artisan view:clear\n";
echo "php artisan optimize:clear\n";

function normalizeOldAdminChecks(string $content): string
{
    $replacements = [
        "@if(auth()->user()?->role === 'admin')" => "@if(auth()->user()?->hasPermission('users.manage'))",
        "@if(auth()->user() && auth()->user()->role === 'admin')" => "@if(auth()->user()?->hasPermission('users.manage'))",
        "@if(auth()->check() && auth()->user()->role === 'admin')" => "@if(auth()->user()?->hasPermission('users.manage'))",
        "@if(auth()->user()?->isAdmin())" => "@if(auth()->user()?->hasPermission('users.manage'))",
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $content);
}

function wrapRouteDrivenElements(string $content): string
{
    $lines = preg_split('/(\r\n|\n|\r)/', $content);
    $lineEnding = str_contains($content, "\r\n") ? "\r\n" : "\n";
    $out = [];
    $count = count($lines);

    for ($i = 0; $i < $count; $i++) {
        $line = $lines[$i];
        $permission = resolvePermissionFromLine($line);

        if ($permission === null || isAlreadyPermissionWrapped($out, $permission)) {
            $out[] = $line;
            continue;
        }

        [$startIndex, $blockType] = findBlockStart($lines, $i);

        // If the route is embedded in a PHP/script area or a harmless URL assignment, keep it untouched.
        if ($blockType === null) {
            $out[] = $line;
            continue;
        }

        // Move previously emitted lines that are part of the same element block back into the block.
        $prefix = [];
        if ($startIndex < $i) {
            $takeBack = $i - $startIndex;
            $prefix = array_splice($out, -$takeBack);
        }

        $blockLines = $prefix;
        $blockLines[] = $line;

        $endTag = $blockType === 'form' ? '</form>' : '</a>';
        while (!str_contains(implode("\n", $blockLines), $endTag) && $i + 1 < $count) {
            $i++;
            $blockLines[] = $lines[$i];
        }

        $block = implode($lineEnding, $blockLines);
        if (str_contains($block, "hasPermission('{$permission}')") || str_contains($block, 'hasPermission("' . $permission . '")')) {
            foreach ($blockLines as $blockLine) {
                $out[] = $blockLine;
            }
            continue;
        }

        $indent = leadingWhitespace($blockLines[0]);
        $out[] = $indent . "@if(auth()->user()?->hasPermission('{$permission}'))";
        foreach ($blockLines as $blockLine) {
            $out[] = $blockLine;
        }
        $out[] = $indent . '@endif';
    }

    return implode($lineEnding, $out);
}

function wrapPrintOnlyButtons(string $content, string $relative): string
{
    // Reports often include a button that calls window.print() without a route.
    if (!str_contains($relative, 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'reports')) {
        return $content;
    }

    $lines = preg_split('/(\r\n|\n|\r)/', $content);
    $lineEnding = str_contains($content, "\r\n") ? "\r\n" : "\n";
    $out = [];
    $count = count($lines);

    for ($i = 0; $i < $count; $i++) {
        $line = $lines[$i];
        $lower = mb_strtolower($line);

        if ((str_contains($lower, 'window.print') || str_contains($lower, 'print()'))
            && str_contains($lower, '<button')
            && !isAlreadyPermissionWrapped($out, 'reports.print')) {
            $indent = leadingWhitespace($line);
            $out[] = $indent . "@if(auth()->user()?->hasPermission('reports.print'))";
            $out[] = $line;
            $out[] = $indent . '@endif';
            continue;
        }

        $out[] = $line;
    }

    return implode($lineEnding, $out);
}

function resolvePermissionFromLine(string $line): ?string
{
    if (!preg_match_all('/route\(\s*[\'\"]([^\'\"]+)[\'\"]/', $line, $matches)) {
        return null;
    }

    foreach ($matches[1] as $routeName) {
        $permission = permissionForRouteName($routeName);
        if ($permission !== null) {
            return $permission;
        }
    }

    return null;
}

function permissionForRouteName(string $name): ?string
{
    if (in_array($name, ['dashboard', 'logout', 'login', 'password.request', 'password.email'], true)) {
        return null;
    }

    if ($name === 'documents.activity') {
        return 'activity_logs.view';
    }

    if (str_starts_with($name, 'documents.')) {
        return match ($name) {
            'documents.index', 'documents.show' => 'documents.view',
            'documents.create', 'documents.store' => 'documents.create',
            'documents.edit', 'documents.update' => 'documents.update',
            'documents.destroy' => 'documents.delete',
            'documents.trash' => 'documents.restore',
            'documents.restore' => 'documents.restore',
            'documents.force-delete', 'documents.force_delete' => 'documents.force_delete',
            'documents.print-reference', 'documents.print_reference' => 'documents.print_reference',
            default => 'documents.view',
        };
    }

    if (str_starts_with($name, 'attachments.')) {
        return match ($name) {
            'attachments.preview', 'attachments.data', 'attachments.inline' => 'attachments.preview',
            'attachments.download' => 'attachments.download',
            'attachments.print' => 'attachments.print',
            'attachments.store', 'attachments.upload' => 'attachments.upload',
            default => 'attachments.preview',
        };
    }

    if (str_starts_with($name, 'reports.')) {
        if (str_contains($name, 'export') || str_contains($name, 'pdf') || str_contains($name, 'download')) {
            return 'reports.export';
        }
        if (str_contains($name, 'print')) {
            return 'reports.print';
        }
        return 'reports.view';
    }

    if (str_starts_with($name, 'backups.')) {
        return match ($name) {
            'backups.index', 'backups.inspect' => 'backups.view',
            'backups.database', 'backups.files', 'backups.full' => 'backups.create',
            'backups.download' => 'backups.download',
            'backups.destroy' => 'backups.delete',
            'backups.restore', 'backups.restore.database', 'backups.restore.files', 'backups.restore.full' => 'backups.restore',
            default => 'backups.view',
        };
    }

    if (str_starts_with($name, 'departments.')) {
        return 'departments.manage';
    }

    if (str_starts_with($name, 'document-types.') || str_starts_with($name, 'document_types.')) {
        return 'document_types.manage';
    }

    if (str_starts_with($name, 'settings.')) {
        return 'settings.manage';
    }

    if (str_starts_with($name, 'users.')) {
        return 'users.manage';
    }

    if (str_starts_with($name, 'activity-logs.') || str_starts_with($name, 'activity_logs.')) {
        return 'activity_logs.view';
    }

    return null;
}

function findBlockStart(array $lines, int $routeLineIndex): array
{
    $start = $routeLineIndex;
    $type = null;

    for ($j = $routeLineIndex; $j >= max(0, $routeLineIndex - 8); $j--) {
        $line = $lines[$j];

        if (str_contains($line, '<form')) {
            $start = $j;
            $type = 'form';
            break;
        }

        if (str_contains($line, '<a ')) {
            $start = $j;
            $type = 'a';
            break;
        }
    }

    if ($type === null && str_contains($lines[$routeLineIndex], '<button')) {
        $type = 'a';
    }

    return [$start, $type];
}

function isAlreadyPermissionWrapped(array $out, string $permission): bool
{
    $tail = implode("\n", array_slice($out, -8));

    return str_contains($tail, "hasPermission('{$permission}')")
        || str_contains($tail, 'hasPermission("' . $permission . '")');
}

function leadingWhitespace(string $line): string
{
    preg_match('/^\s*/', $line, $match);
    return $match[0] ?? '';
}

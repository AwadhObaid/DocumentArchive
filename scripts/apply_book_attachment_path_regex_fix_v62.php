<?php

/**
 * V62 - Fix Windows path validation regex for book attachment storage path.
 *
 * This patch removes fragile preg_match patterns that break with Windows paths
 * such as D:\DocumentArchiveFiles and replaces them with safe string checks.
 */

$root = dirname(__DIR__);

function v62_path(string $relative): string
{
    global $root;

    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
}

function v62_read(string $relative): string
{
    $path = v62_path($relative);

    if (! is_file($path)) {
        fwrite(STDERR, "[FAIL] Missing file: {$relative}\n");
        exit(1);
    }

    return file_get_contents($path);
}

function v62_write(string $relative, string $content): void
{
    $path = v62_path($relative);
    file_put_contents($path, $content);
}

function v62_replace_method(string $content, string $methodName, string $newMethod): string
{
    $needle = 'function ' . $methodName . '(';
    $functionPos = strpos($content, $needle);

    if ($functionPos === false) {
        fwrite(STDERR, "[FAIL] Method not found: {$methodName}\n");
        exit(1);
    }

    $start = strrpos(substr($content, 0, $functionPos), "\n");
    $start = ($start === false) ? 0 : $start + 1;

    $braceStart = strpos($content, '{', $functionPos);
    if ($braceStart === false) {
        fwrite(STDERR, "[FAIL] Opening brace not found for method: {$methodName}\n");
        exit(1);
    }

    $depth = 0;
    $length = strlen($content);
    $end = null;

    for ($i = $braceStart; $i < $length; $i++) {
        $char = $content[$i];

        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            if ($depth === 0) {
                $end = $i + 1;
                break;
            }
        }
    }

    if ($end === null) {
        fwrite(STDERR, "[FAIL] Closing brace not found for method: {$methodName}\n");
        exit(1);
    }

    return substr($content, 0, $start) . rtrim($newMethod) . "\n" . substr($content, $end);
}

function v62_insert_before_method_if_missing(string $content, string $markerMethod, string $missingNeedle, string $insertBlock): string
{
    if (str_contains($content, $missingNeedle)) {
        return $content;
    }

    $needle = 'function ' . $markerMethod . '(';
    $pos = strpos($content, $needle);

    if ($pos === false) {
        fwrite(STDERR, "[FAIL] Marker method not found: {$markerMethod}\n");
        exit(1);
    }

    $insertPos = strrpos(substr($content, 0, $pos), "\n");
    $insertPos = ($insertPos === false) ? $pos : $insertPos + 1;

    return substr($content, 0, $insertPos) . rtrim($insertBlock) . "\n\n" . substr($content, $insertPos);
}

$serviceRelative = 'app/Services/BookAttachmentSmartPathService.php';
$service = v62_read($serviceRelative);

$service = v62_replace_method($service, 'normalizeStorageRoot', <<<'PHP_METHOD'
    public function normalizeStorageRoot(?string $value): ?string
    {
        $value = trim((string) $value);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($value === '') {
            return null;
        }

        $value = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $value);

        if ($this->isWindowsDriveOnly($value)) {
            return strtoupper($value[0]) . ':' . DIRECTORY_SEPARATOR;
        }

        return rtrim($value, "\\/");
    }
PHP_METHOD);

$service = v62_replace_method($service, 'isAbsoluteStorageRoot', <<<'PHP_METHOD'
    public function isAbsoluteStorageRoot(string $path): bool
    {
        return $this->isAbsolutePath($path);
    }
PHP_METHOD);

$service = v62_replace_method($service, 'isAbsolutePath', <<<'PHP_METHOD'
    private function isAbsolutePath(string $path): bool
    {
        $path = trim($path);

        if ($path === '') {
            return false;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return $this->startsWithWindowsDriveRoot($normalized)
            || str_starts_with($path, '\\\\')
            || str_starts_with($path, '//')
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\');
    }
PHP_METHOD);

$service = v62_insert_before_method_if_missing($service, 'absoluteFolderPath', 'function isWindowsDriveOnly(', <<<'PHP_METHOD'
    private function isWindowsDriveOnly(string $path): bool
    {
        return strlen($path) >= 2
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && trim(substr($path, 2), "\\/") === '';
    }

    private function startsWithWindowsDriveRoot(string $path): bool
    {
        return strlen($path) >= 3
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && in_array($path[2], ['\\', '/'], true);
    }
PHP_METHOD);

v62_write($serviceRelative, $service);

$settingsRelative = 'app/Http/Controllers/SettingsController.php';
$settings = v62_read($settingsRelative);

if (str_contains($settings, 'function normalizeStorageBrowserPath(')) {
    $settings = v62_replace_method($settings, 'normalizeStorageBrowserPath', <<<'PHP_METHOD'
    private function normalizeStorageBrowserPath(?string $path): string
    {
        $path = trim((string) $path);
        $path = trim($path, " \t\n\r\0\x0B\"'");

        if ($path === '') {
            return storage_path('app/private');
        }

        if ($this->settingsBrowserIsWindowsDriveOnly($path)) {
            return strtoupper($path[0]) . ':\\';
        }

        if (str_starts_with($path, '\\\\')) {
            return rtrim($path, "\\/");
        }

        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if ($this->settingsBrowserIsWindowsDriveOnly($path)) {
            return strtoupper($path[0]) . ':' . DIRECTORY_SEPARATOR;
        }

        return rtrim($path, "\\/") ?: DIRECTORY_SEPARATOR;
    }
PHP_METHOD);

    $settings = v62_replace_method($settings, 'parentStorageBrowserPath', <<<'PHP_METHOD'
    private function parentStorageBrowserPath(string $path): ?string
    {
        $path = $this->normalizeStorageBrowserPath($path);

        if (DIRECTORY_SEPARATOR === '\\' && $this->settingsBrowserIsWindowsDriveOnly($path)) {
            return null;
        }

        if ($path === DIRECTORY_SEPARATOR) {
            return null;
        }

        $parent = dirname($path);

        if ($parent === $path || $parent === '.' || $parent === '') {
            return null;
        }

        return $this->normalizeStorageBrowserPath($parent);
    }
PHP_METHOD);

    $settings = v62_replace_method($settings, 'sanitizeStorageBrowserFolderName', <<<'PHP_METHOD'
    private function sanitizeStorageBrowserFolderName(string $name): string
    {
        $name = trim($name);
        $name = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ' ', $name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        $name = trim($name, " .\t\n\r\0\x0B");

        if (in_array($name, ['', '.', '..'], true)) {
            return '';
        }

        return mb_substr($name, 0, 100);
    }
PHP_METHOD);

    $settings = v62_insert_before_method_if_missing($settings, 'settingsForView', 'function settingsBrowserIsWindowsDriveOnly(', <<<'PHP_METHOD'
    private function settingsBrowserIsWindowsDriveOnly(string $path): bool
    {
        return strlen($path) >= 2
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && trim(substr($path, 2), "\\/") === '';
    }
PHP_METHOD);

    v62_write($settingsRelative, $settings);
}

echo "Book attachment path regex fix V62 applied successfully.\n";
echo "Run: php scripts/check_book_attachment_path_regex_fix_v62.php\n";

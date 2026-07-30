<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

class SmartAttachmentBrowserService
{
    public const SETTING_ENABLED = 'smart_attachment_browser_enabled';
    public const SETTING_OUTGOING_PATH = 'smart_attachment_outgoing_path';
    public const SETTING_INCOMING_PATH = 'smart_attachment_incoming_path';
    public const SETTING_GENERAL_PATH = 'smart_attachment_general_path';
    public const SETTING_RECURSIVE = 'smart_attachment_recursive';
    public const SETTING_RESULT_LIMIT = 'smart_attachment_result_limit';
    public const SETTING_TIMEOUT_SECONDS = 'smart_attachment_timeout_seconds';
    public const SETTING_MAX_FILE_MB = 'smart_attachment_max_file_mb';

    private const TOKEN_TTL_SECONDS = 900;
    private const MAX_RECURSION_DEPTH = 8;

    private const ALLOWED_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'bmp',
        'tif',
        'tiff',
        'doc',
        'docx',
        'xls',
        'xlsx',
    ];

    public function enabled(): bool
    {
        return (string) Setting::getValue(self::SETTING_ENABLED, '1') === '1';
    }

    public function recursiveByDefault(): bool
    {
        return (string) Setting::getValue(self::SETTING_RECURSIVE, '1') === '1';
    }

    public function resultLimit(): int
    {
        return max(10, min(200, Setting::getInt(self::SETTING_RESULT_LIMIT, 80)));
    }

    public function timeoutSeconds(): int
    {
        return max(2, min(30, Setting::getInt(self::SETTING_TIMEOUT_SECONDS, 8)));
    }

    public function maxFileMegabytes(): int
    {
        return max(1, min(100, Setting::getInt(self::SETTING_MAX_FILE_MB, 20)));
    }

    public function maxFileBytes(): int
    {
        return $this->maxFileMegabytes() * 1024 * 1024;
    }

    public function sourceDefinitions(): array
    {
        return [
            'outgoing' => [
                'label' => 'ملفات الصادر',
                'setting_key' => self::SETTING_OUTGOING_PATH,
            ],
            'incoming' => [
                'label' => 'ملفات الوارد',
                'setting_key' => self::SETTING_INCOMING_PATH,
            ],
            'general' => [
                'label' => 'مسار عام إضافي',
                'setting_key' => self::SETTING_GENERAL_PATH,
            ],
        ];
    }

    public function settingsForView(): array
    {
        return [
            self::SETTING_ENABLED => Setting::getValue(self::SETTING_ENABLED, '1'),
            self::SETTING_OUTGOING_PATH => Setting::getValue(self::SETTING_OUTGOING_PATH, ''),
            self::SETTING_INCOMING_PATH => Setting::getValue(self::SETTING_INCOMING_PATH, ''),
            self::SETTING_GENERAL_PATH => Setting::getValue(self::SETTING_GENERAL_PATH, ''),
            self::SETTING_RECURSIVE => Setting::getValue(self::SETTING_RECURSIVE, '1'),
            self::SETTING_RESULT_LIMIT => Setting::getValue(self::SETTING_RESULT_LIMIT, '80'),
            self::SETTING_TIMEOUT_SECONDS => Setting::getValue(self::SETTING_TIMEOUT_SECONDS, '8'),
            self::SETTING_MAX_FILE_MB => Setting::getValue(self::SETTING_MAX_FILE_MB, '20'),
        ];
    }

    public function normalizeConfiguredPath(?string $path): string
    {
        $path = (string) $path;

        // Remove bidi/direction marks that can be inserted when copying Arabic UNC paths.
        $path = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $path) ?? $path;
        $path = trim($path);
        $path = trim($path, " \t\n\r\0\x0B\"'");

        if ($path === '') {
            return '';
        }

        if (! $this->isAbsolutePath($path)) {
            throw new InvalidArgumentException(
                'مسار البحث يجب أن يكون كاملاً مثل D:\\Archive أو \\\\SERVER\\Share\\Archive.'
            );
        }

        if ($this->isWindowsDriveOnly($path)) {
            return strtoupper($path[0]) . ':\\';
        }

        if (str_starts_with($path, '\\\\') || str_starts_with($path, '//')) {
            $path = '\\\\' . ltrim(str_replace('/', '\\', $path), '\\');

            return rtrim($path, "\\/");
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            $path = strtoupper($path[0]) . ':' . substr($path, 2);
            $path = str_replace('/', '\\', $path);

            return rtrim($path, "\\/");
        }

        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), "\\/");
    }

    public function configuredSources(?int $year = null): array
    {
        $year = $this->normalizeYear($year);
        $sources = [];

        foreach ($this->sourceDefinitions() as $key => $definition) {
            $configured = $this->normalizeConfiguredPath(
                Setting::getString($definition['setting_key'], '')
            );

            if ($configured === '') {
                continue;
            }

            $resolved = $this->replaceYearPlaceholders($configured, $year);
            $sources[] = [
                'key' => $key,
                'label' => $definition['label'],
                'configured_path' => $configured,
                'resolved_path' => $resolved,
                'available' => @is_dir($resolved) && @is_readable($resolved),
                'recursive_default' => $this->recursiveByDefault(),
            ];
        }

        return $sources;
    }

    public function search(string $source, string $query, ?int $year = null, ?bool $recursive = null): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('البحث الذكي عن المرفقات غير مفعل من إعدادات النظام.');
        }

        $query = trim($query);
        if (mb_strlen($query) < 2) {
            throw new InvalidArgumentException('اكتب رقم الكتاب أو عبارتي بحث على الأقل.');
        }

        $year = $this->normalizeYear($year);
        $root = $this->resolveRoot($source, $year);

        if (! @is_dir($root)) {
            throw new RuntimeException('مسار البحث غير موجود أو غير متصل حالياً: ' . $root);
        }

        if (! @is_readable($root)) {
            throw new RuntimeException('لا يستطيع حساب تشغيل Laravel قراءة مسار البحث: ' . $root);
        }

        $recursive = $recursive ?? $this->recursiveByDefault();
        $limit = $this->resultLimit();
        $deadline = microtime(true) + $this->timeoutSeconds();
        $items = [];

        try {
            if ($recursive) {
                $directory = new RecursiveDirectoryIterator(
                    $root,
                    RecursiveDirectoryIterator::SKIP_DOTS
                        | RecursiveDirectoryIterator::CURRENT_AS_FILEINFO
                );

                $iterator = new RecursiveIteratorIterator(
                    $directory,
                    RecursiveIteratorIterator::LEAVES_ONLY,
                    RecursiveIteratorIterator::CATCH_GET_CHILD
                );
                $iterator->setMaxDepth(self::MAX_RECURSION_DEPTH);
            } else {
                $iterator = new \FilesystemIterator(
                    $root,
                    \FilesystemIterator::SKIP_DOTS
                        | \FilesystemIterator::CURRENT_AS_FILEINFO
                );
            }

            foreach ($iterator as $fileInfo) {
                if (microtime(true) >= $deadline || count($items) >= $limit) {
                    break;
                }

                try {
                    if (! $fileInfo->isFile() || $fileInfo->isLink()) {
                        continue;
                    }

                    $absolutePath = $fileInfo->getPathname();
                    $name = $fileInfo->getFilename();
                    $extension = strtolower($fileInfo->getExtension());

                    if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                        continue;
                    }

                    $relative = $this->relativePath($root, $absolutePath);
                    $searchable = $this->normalizeSearchText($name . ' ' . $relative);

                    if (! str_contains($searchable, $this->normalizeSearchText($query))) {
                        continue;
                    }

                    $size = max(0, (int) $fileInfo->getSize());
                    if ($size > $this->maxFileBytes()) {
                        continue;
                    }

                    $modifiedAt = max(0, (int) $fileInfo->getMTime());
                    $token = $this->createToken(
                        $source,
                        $year,
                        $root,
                        $relative,
                        $name,
                        $size,
                        $modifiedAt
                    );

                    $items[] = [
                        'name' => $name,
                        'relative_path' => $relative,
                        'extension' => $extension,
                        'size' => $size,
                        'size_human' => $this->formatBytes($size),
                        'modified_at' => $modifiedAt,
                        'modified_at_human' => $modifiedAt > 0 ? date('Y-m-d H:i', $modifiedAt) : '-',
                        'token' => $token,
                    ];
                } catch (Throwable) {
                    continue;
                }
            }
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'تعذر استعراض مسار البحث. تحقق من اتصال الشبكة وصلاحيات المشاركة. '
                . $exception->getMessage()
            );
        }

        usort(
            $items,
            static fn (array $a, array $b): int => ($b['modified_at'] <=> $a['modified_at'])
                ?: strnatcasecmp($a['name'], $b['name'])
        );

        return [
            'source' => $source,
            'root' => $root,
            'query' => $query,
            'recursive' => $recursive,
            'limit' => $limit,
            'timed_out' => microtime(true) >= $deadline,
            'items' => $items,
        ];
    }

    public function resolveToken(string $token): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new InvalidArgumentException('اختيار الملف غير صالح أو انتهت صلاحيته. أعد البحث واختر الملف مرة أخرى.');
        }

        if (! is_array($payload)) {
            throw new InvalidArgumentException('بيانات الملف المختار غير صالحة.');
        }

        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        if ($expiresAt < time()) {
            throw new InvalidArgumentException('انتهت صلاحية اختيار الملف. أعد البحث واختره مرة أخرى.');
        }

        $source = (string) ($payload['source'] ?? '');
        $year = $this->normalizeYear((int) ($payload['year'] ?? date('Y')));
        $relative = $this->normalizeRelativePath((string) ($payload['relative'] ?? ''));
        $root = $this->resolveRoot($source, $year);

        if (! hash_equals((string) ($payload['root_hash'] ?? ''), hash('sha256', $this->canonicalCase($root)))) {
            throw new InvalidArgumentException('تم تغيير مسار المصدر بعد اختيار الملف. أعد البحث مرة أخرى.');
        }

        $absolutePath = $this->joinRootAndRelative($root, $relative);

        if (! @is_file($absolutePath) || ! @is_readable($absolutePath)) {
            throw new RuntimeException('الملف المختار لم يعد موجوداً أو لا يمكن قراءته.');
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('نوع الملف المختار غير مسموح.');
        }

        $size = max(0, (int) @filesize($absolutePath));
        if ($size > $this->maxFileBytes()) {
            throw new InvalidArgumentException(
                'حجم الملف يتجاوز الحد المسموح وهو ' . $this->maxFileMegabytes() . ' MB.'
            );
        }

        $expectedSize = (int) ($payload['size'] ?? -1);
        $expectedModified = (int) ($payload['modified_at'] ?? -1);
        $currentModified = max(0, (int) @filemtime($absolutePath));

        if (($expectedSize >= 0 && $expectedSize !== $size)
            || ($expectedModified >= 0 && $expectedModified !== $currentModified)) {
            throw new RuntimeException('تم تعديل الملف بعد اختياره. أعد البحث واختر النسخة الحالية.');
        }

        $definitions = $this->sourceDefinitions();

        return [
            'source' => $source,
            'source_label' => $definitions[$source]['label'] ?? $source,
            'year' => $year,
            'root' => $root,
            'relative_path' => $relative,
            'absolute_path' => $absolutePath,
            'name' => basename($absolutePath),
            'extension' => $extension,
            'size' => $size,
            'modified_at' => $currentModified,
        ];
    }

    public function mimeType(string $path, string $extension): string
    {
        $detected = function_exists('mime_content_type') ? @mime_content_type($path) : null;

        if (is_string($detected) && $detected !== '') {
            return $detected;
        }

        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'tif', 'tiff' => 'image/tiff',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' Bytes';
    }

    private function resolveRoot(string $source, int $year): string
    {
        $definitions = $this->sourceDefinitions();

        if (! isset($definitions[$source])) {
            throw new InvalidArgumentException('مصدر البحث المحدد غير معروف.');
        }

        $configured = $this->normalizeConfiguredPath(
            Setting::getString($definitions[$source]['setting_key'], '')
        );

        if ($configured === '') {
            throw new InvalidArgumentException('لم يتم حفظ مسار لهذا المصدر في إعدادات النظام.');
        }

        return $this->replaceYearPlaceholders($configured, $year);
    }

    private function replaceYearPlaceholders(string $path, int $year): string
    {
        return str_replace(
            ['{year}', '{YEAR}', '{{year}}', '{{YEAR}}', '%YEAR%', '%year%'],
            (string) $year,
            $path
        );
    }

    private function createToken(
        string $source,
        int $year,
        string $root,
        string $relative,
        string $name,
        int $size,
        int $modifiedAt
    ): string {
        return Crypt::encryptString(json_encode([
            'version' => 1,
            'source' => $source,
            'year' => $year,
            'root_hash' => hash('sha256', $this->canonicalCase($root)),
            'relative' => $relative,
            'name' => $name,
            'size' => $size,
            'modified_at' => $modifiedAt,
            'expires_at' => time() + self::TOKEN_TTL_SECONDS,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function relativePath(string $root, string $absolutePath): string
    {
        $root = rtrim($root, "\\/");
        $relative = substr($absolutePath, strlen($root));
        $relative = ltrim((string) $relative, "\\/");

        return $this->normalizeRelativePath($relative);
    }

    private function normalizeRelativePath(string $relative): string
    {
        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($relative));
        $parts = [];

        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $part) {
            $part = trim($part);

            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..' || str_contains($part, "\0")) {
                throw new InvalidArgumentException('مسار الملف المختار غير صالح.');
            }

            $parts[] = $part;
        }

        if ($parts === []) {
            throw new InvalidArgumentException('مسار الملف المختار فارغ.');
        }

        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    private function joinRootAndRelative(string $root, string $relative): string
    {
        return rtrim($root, "\\/") . DIRECTORY_SEPARATOR . $this->normalizeRelativePath($relative);
    }

    private function normalizeSearchText(string $value): string
    {
        $value = Str::lower($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeYear(?int $year): int
    {
        $year = $year ?: (int) date('Y');

        return max(2000, min(2200, $year));
    }

    private function canonicalCase(string $path): string
    {
        $path = rtrim(str_replace('/', '\\', $path), "\\/");

        return DIRECTORY_SEPARATOR === '\\' ? Str::lower($path) : $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
            || str_starts_with($path, '\\\\')
            || str_starts_with($path, '//')
            || str_starts_with($path, '/');
    }

    private function isWindowsDriveOnly(string $path): bool
    {
        return strlen($path) >= 2
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && trim(substr($path, 2), "\\/") === '';
    }
}

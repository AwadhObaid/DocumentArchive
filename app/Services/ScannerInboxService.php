<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Str;

class ScannerInboxService
{
    public const SETTING_KEY = 'scanner_inbox_path';

    public function path(): string
    {
        $stored = Setting::getValue(self::SETTING_KEY, '');
        $path = $this->normalizePath(is_string($stored) ? $stored : '');

        return $path ?: storage_path('app/scanner-inbox');
    }

    public function preparePath(?string $path): string
    {
        $path = $this->normalizePath($path) ?: storage_path('app/scanner-inbox');

        if (! $this->isAbsolutePath($path)) {
            throw new \InvalidArgumentException('مسار صندوق الماسح يجب أن يكون مسارًا كاملاً مثل D:\\DocumentArchiveScannerInbox.');
        }

        if (! is_dir($path) && ! @mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new \InvalidArgumentException('تعذر إنشاء مجلد صندوق الماسح. تحقق من صحة المسار والصلاحيات.');
        }

        if (! is_readable($path)) {
            throw new \InvalidArgumentException('مجلد صندوق الماسح غير قابل للقراءة.');
        }

        if (! is_writable($path)) {
            throw new \InvalidArgumentException('مجلد صندوق الماسح غير قابل للكتابة.');
        }

        return $path;
    }

    public function savePath(string $path): string
    {
        $path = $this->preparePath($path);

        Setting::setValue(self::SETTING_KEY, $path, 'scanner', 'text', 'مجلد صندوق الماسح الضوئي الذي يقرأه النظام قبل ربط الملفات بالكتب.');

        return $path;
    }

    public function files(int $limit = 200): array
    {
        $path = $this->preparePath($this->path());
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'webp'];
        $items = [];

        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $full = rtrim($path, '\\/') . DIRECTORY_SEPARATOR . $name;
            if (! is_file($full)) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($extension, $allowed, true)) {
                continue;
            }

            $items[] = [
                'name' => $name,
                'path' => $full,
                'extension' => $extension,
                'size' => filesize($full) ?: 0,
                'modified_at' => filemtime($full) ?: time(),
            ];
        }

        usort($items, fn (array $a, array $b) => $b['modified_at'] <=> $a['modified_at']);

        return array_slice($items, 0, max(1, min(1000, $limit)));
    }

    public function absoluteFilePath(string $fileName): string
    {
        $fileName = $this->safeBaseName($fileName);
        if ($fileName === '') {
            throw new \InvalidArgumentException('اسم الملف غير صالح.');
        }

        return rtrim($this->path(), '\\/') . DIRECTORY_SEPARATOR . $fileName;
    }

    public function safeBaseName(string $fileName): string
    {
        $fileName = trim($fileName);
        $fileName = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $fileName));

        if ($fileName === '.' || $fileName === '..') {
            return '';
        }

        return $fileName;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' Bytes';
    }

    private function normalizePath(?string $path): ?string
    {
        $path = trim((string) $path);
        $path = trim($path, " \t\n\r\0\x0B\"'");

        if ($path === '') {
            return null;
        }

        if ($this->isWindowsDriveOnly($path)) {
            return strtoupper($path[0]) . ':\\';
        }

        if (str_starts_with($path, '\\\\')) {
            return rtrim($path, '\\/');
        }

        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if ($this->isWindowsDriveOnly($path)) {
            return strtoupper($path[0]) . ':' . DIRECTORY_SEPARATOR;
        }

        return rtrim($path, '\\/');
    }

    private function isAbsolutePath(string $path): bool
    {
        $path = trim($path);

        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
            || str_starts_with($path, '\\\\')
            || str_starts_with($path, '//')
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\');
    }

    private function isWindowsDriveOnly(string $path): bool
    {
        return strlen($path) >= 2
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && trim(substr($path, 2), '\\/') === '';
    }
}

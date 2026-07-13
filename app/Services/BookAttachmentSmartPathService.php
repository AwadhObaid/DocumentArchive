<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookAttachmentSmartPathService
{
    public const CUSTOM_DISK = 'book_attachment_custom_path';

    public function buildFolder(Document $document): array
    {
        $year = $this->resolveYear($document);
        $company = $this->sanitizeFolderName($document->attachment_company_name ?? null);
        $operation = $this->sanitizeFolderName($document->attachment_category_name ?? null);

        if ($company === null && $operation === null) {
            $folder = 'documents/' . $year . '/' . ($document->reference_number ?: $document->id);

            return [
                'folder' => $folder,
                'company' => null,
                'operation' => null,
                'year' => $year,
                'smart' => false,
            ];
        }

        $companyFolder = $company ?: 'غير محدد';
        $operationFolder = ($operation ?: 'عام') . ' ' . $year;
        $folder = 'Books/' . $companyFolder . '/' . $operationFolder . '/' . ($document->reference_number ?: $document->id);

        return [
            'folder' => $folder,
            'company' => $companyFolder,
            'operation' => $operation ?: 'عام',
            'year' => $year,
            'smart' => true,
        ];
    }

    public function storeUploadedFile(Document $document, UploadedFile $file, int $versionNo): array
    {
        $classification = $this->buildFolder($document);
        $safeName = $this->buildFileName($document, $file, $versionNo);
        $relativePath = $classification['folder'] . '/' . $safeName;
        $customRoot = $this->configuredStorageRoot();

        if ($customRoot !== null) {
            $absoluteFolder = $this->absoluteFolderPath($customRoot, $classification['folder']);
            $this->ensureDirectoryExists($absoluteFolder);
            $file->move($absoluteFolder, $safeName);

            return [
                'file_path' => $relativePath,
                'disk' => self::CUSTOM_DISK,
                'storage_root_path' => $customRoot,
                'file_name' => $safeName,
                'classification' => $classification,
                'absolute_path' => $this->joinRootAndRelative($customRoot, $relativePath),
            ];
        }

        $path = $file->storeAs($classification['folder'], $safeName, 'local');

        return [
            'file_path' => $path,
            'disk' => 'local',
            'storage_root_path' => null,
            'file_name' => $safeName,
            'classification' => $classification,
            'absolute_path' => $this->localAbsolutePath($path),
        ];
    }

    public function buildFileName(Document $document, UploadedFile $file, int $versionNo): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $reference = $this->sanitizeFilePart((string) ($document->reference_number ?: $document->id), 'book');
        $operation = $this->sanitizeFilePart((string) ($document->attachment_category_name ?: 'مرفق'), 'attachment');
        $version = str_pad((string) max(1, $versionNo), 3, '0', STR_PAD_LEFT);
        $date = $document->reference_date ? $document->reference_date->format('Y-m-d') : date('Y-m-d');

        return $reference . '_' . $operation . '_' . $date . '_' . $version . '_' . Str::random(8) . '.' . $extension;
    }

    public function configuredStorageRoot(): ?string
    {
        return $this->normalizeStorageRoot(Setting::getValue('book_attachment_storage_root', ''));
    }

    public function defaultStorageRootForView(): string
    {
        return storage_path('app/private');
    }

    public function effectiveStorageRootForView(): string
    {
        return $this->configuredStorageRoot() ?: $this->defaultStorageRootForView();
    }

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


    public function isAbsoluteStorageRoot(string $path): bool
    {
        return $this->isAbsolutePath($path);
    }


    public function prepareStorageRoot(?string $path): ?string
    {
        $path = $this->normalizeStorageRoot($path);

        if ($path === null) {
            return null;
        }

        if (! $this->isAbsoluteStorageRoot($path)) {
            throw new \InvalidArgumentException('مسار حفظ مرفقات الكتب يجب أن يكون مساراً كاملاً مثل D:\\DocumentArchiveFiles أو \\SERVER\\Share.');
        }

        if (! is_dir($path) && ! @mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new \InvalidArgumentException('تعذر إنشاء مجلد حفظ مرفقات الكتب. تأكد من صحة المسار وصلاحيات الكتابة.');
        }

        if (! is_writable($path)) {
            throw new \InvalidArgumentException('مجلد حفظ مرفقات الكتب غير قابل للكتابة. تأكد من صلاحيات المستخدم الذي يشغل Laragon/PHP.');
        }

        return $path;
    }

    public function attachmentExists(DocumentAttachment $attachment): bool
    {
        $path = (string) ($attachment->file_path ?? '');

        if ($path === '') {
            return false;
        }

        if ($this->usesCustomPath($attachment)) {
            $absolute = $this->absolutePathForAttachment($attachment);

            return $absolute !== null && is_file($absolute);
        }

        return Storage::disk($attachment->disk ?: 'local')->exists($path);
    }

    public function attachmentBinary(DocumentAttachment $attachment): ?string
    {
        if ($this->usesCustomPath($attachment)) {
            $absolute = $this->absolutePathForAttachment($attachment);

            return ($absolute && is_file($absolute)) ? @file_get_contents($absolute) : null;
        }

        $disk = Storage::disk($attachment->disk ?: 'local');

        return $disk->exists($attachment->file_path) ? $disk->get($attachment->file_path) : null;
    }

    public function absolutePathForAttachment(DocumentAttachment $attachment): ?string
    {
        $path = (string) ($attachment->file_path ?? '');

        if ($path === '') {
            return null;
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        if ($this->usesCustomPath($attachment)) {
            $root = $this->normalizeStorageRoot($attachment->storage_root_path ?? null);

            return $root ? $this->joinRootAndRelative($root, $path) : null;
        }

        $disk = Storage::disk($attachment->disk ?: 'local');

        return method_exists($disk, 'path') ? $disk->path($path) : null;
    }

    public function deleteAttachmentFile(DocumentAttachment $attachment): bool
    {
        if ($this->usesCustomPath($attachment)) {
            $absolute = $this->absolutePathForAttachment($attachment);

            return $absolute && is_file($absolute) ? @unlink($absolute) : false;
        }

        $disk = Storage::disk($attachment->disk ?: 'local');

        if ($disk->exists($attachment->file_path)) {
            return $disk->delete($attachment->file_path);
        }

        return false;
    }

    public function sanitizeFolderName(?string $value): ?string
    {
        $value = $this->normalizeText($value);

        if ($value === null) {
            return null;
        }

        $value = preg_replace('/[\\\/\:\*\?"\<\>\|]+/u', ' ', $value) ?: $value;
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?: $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;
        $value = trim($value, " \t\n\r\0\x0B.");

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, 120, 'UTF-8');
    }

    public function normalizeText(?string $value): ?string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;

        return $value === '' ? null : $value;
    }

    private function usesCustomPath(DocumentAttachment $attachment): bool
    {
        return (string) ($attachment->disk ?? '') === self::CUSTOM_DISK
            || $this->normalizeStorageRoot($attachment->storage_root_path ?? null) !== null
            || $this->isAbsolutePath((string) ($attachment->file_path ?? ''));
    }

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

    private function absoluteFolderPath(string $root, string $folder): string
    {
        return $this->joinRootAndRelative($root, $folder);
    }

    private function joinRootAndRelative(string $root, string $relative): string
    {
        $root = $this->normalizeStorageRoot($root) ?: $root;
        $relative = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative), "\\/");

        return rtrim($root, "\\/") . DIRECTORY_SEPARATOR . $relative;
    }

    private function ensureDirectoryExists(string $folder): void
    {
        if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
            throw new \RuntimeException('تعذر إنشاء مجلد الحفظ: ' . $folder);
        }
    }

    private function localAbsolutePath(string $path): ?string
    {
        $disk = Storage::disk('local');

        return method_exists($disk, 'path') ? $disk->path($path) : null;
    }

    private function sanitizeFilePart(string $value, string $fallback): string
    {
        $value = $this->sanitizeFolderName($value) ?: $fallback;
        $value = preg_replace('/\s+/u', '_', $value) ?: $value;
        $value = preg_replace('/[^\p{Arabic}\p{L}\p{N}_\-]+/u', '_', $value) ?: $value;
        $value = trim($value, '_-');

        if ($value === '') {
            $value = $fallback;
        }

        return mb_substr($value, 0, 70, 'UTF-8');
    }

    private function resolveYear(Document $document): int
    {
        if (! empty($document->reference_year)) {
            return (int) $document->reference_year;
        }

        if (! empty($document->reference_date)) {
            return (int) $document->reference_date->format('Y');
        }

        return (int) date('Y');
    }
}

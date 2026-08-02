<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\FileBridgeRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class FileBridgeService
{
    public const SETTING_ENABLED = 'file_bridge_enabled';
    public const SETTING_REQUEST_MINUTES = 'file_bridge_request_minutes';
    public const SETTING_MAX_FILE_MB = 'file_bridge_max_file_mb';

    public const STATUS_PENDING = 'pending';
    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_READY = 'ready';
    public const STATUS_CONSUMING = 'consuming';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';

    private const SELECTION_TOKEN_TTL_SECONDS = 900;
    private const TEMP_DISK = 'local';
    private const TEMP_FOLDER = 'file-bridge/pending';

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

    public function requestMinutes(): int
    {
        return max(3, min(30, Setting::getInt(self::SETTING_REQUEST_MINUTES, 10)));
    }

    public function maxFileMegabytes(): int
    {
        $configured = Setting::getInt(
            self::SETTING_MAX_FILE_MB,
            Setting::getInt(SmartAttachmentBrowserService::SETTING_MAX_FILE_MB, 20)
        );

        return max(1, min(100, $configured));
    }

    public function maxFileBytes(): int
    {
        return $this->maxFileMegabytes() * 1024 * 1024;
    }

    public function allowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }

    public function createRequest(
        User $user,
        ?Document $document,
        ?string $query,
        ?int $year
    ): array {
        if (! $this->enabled()) {
            throw ValidationException::withMessages([
                'file_bridge' => 'File Bridge معطل من إعدادات النظام.',
            ]);
        }

        $this->purgeExpired();

        $query = trim((string) $query);
        $query = Str::limit($query, 255, '');
        $year = $year ?: (int) date('Y');
        $year = max(2000, min(2200, $year));
        $token = $this->randomToken();
        $uuid = (string) Str::uuid();
        $expiresAt = now()->addMinutes($this->requestMinutes());

        $bridgeRequest = FileBridgeRequest::query()->create([
            'uuid' => $uuid,
            'token_hash' => hash('sha256', $token),
            'user_id' => $user->id,
            'document_id' => $document?->id,
            'status' => self::STATUS_PENDING,
            'query' => $query !== '' ? $query : null,
            'reference_number' => $document?->reference_number ?: ($query !== '' ? $query : null),
            'reference_year' => $year,
            'expires_at' => $expiresAt,
        ]);

        ActivityLogger::log(
            'file_bridge.request_created',
            'تم إنشاء طلب File Bridge لاختيار مرفق محلي.',
            $bridgeRequest,
            [
                'document_id' => $document?->id,
                'reference_number' => $document?->reference_number,
                'expires_at' => $expiresAt->toIso8601String(),
            ]
        );

        return [
            'request' => $bridgeRequest,
            'client_token' => $token,
        ];
    }

    public function findForClient(string $uuid, string $token, bool $allowReady = false): FileBridgeRequest
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 256) {
            throw new RuntimeException('رمز File Bridge غير صالح.');
        }

        $bridgeRequest = FileBridgeRequest::query()
            ->where('uuid', $uuid)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $bridgeRequest) {
            throw new RuntimeException('طلب File Bridge غير موجود أو انتهت صلاحيته.');
        }

        $this->expireIfNeeded($bridgeRequest);
        $bridgeRequest->refresh();

        $allowedStatuses = $allowReady
            ? [self::STATUS_PENDING, self::STATUS_UPLOADING, self::STATUS_READY]
            : [self::STATUS_PENDING, self::STATUS_UPLOADING];

        if (! in_array($bridgeRequest->status, $allowedStatuses, true)) {
            throw new RuntimeException($this->statusMessage($bridgeRequest));
        }

        return $bridgeRequest;
    }

    public function receiveUpload(
        string $uuid,
        string $token,
        UploadedFile $file,
        string $originalName,
        ?string $clientName,
        ?string $clientMachine
    ): FileBridgeRequest {
        $bridgeRequest = $this->findForClient($uuid, $token);

        $originalName = $this->sanitizeOriginalName($originalName ?: $file->getClientOriginalName());
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => 'نوع الملف غير مسموح. الأنواع المتاحة: ' . implode(', ', self::ALLOWED_EXTENSIONS),
            ]);
        }

        $size = $file->getSize();
        if ($size === false || $size === null) {
            $size = is_file($file->getPathname()) ? (int) filesize($file->getPathname()) : 0;
        }
        $size = max(0, (int) $size);

        if ($size <= 0) {
            throw ValidationException::withMessages([
                'file' => 'الملف فارغ أو تعذر قراءة حجمه.',
            ]);
        }

        if ($size > $this->maxFileBytes()) {
            throw ValidationException::withMessages([
                'file' => 'حجم الملف يتجاوز الحد المسموح وهو ' . $this->maxFileMegabytes() . ' MB.',
            ]);
        }

        $bridgeRequest = DB::transaction(function () use ($bridgeRequest) {
            $locked = FileBridgeRequest::query()->lockForUpdate()->findOrFail($bridgeRequest->id);
            $this->expireIfNeeded($locked);

            if ($locked->status !== self::STATUS_PENDING) {
                throw new RuntimeException($this->statusMessage($locked));
            }

            $locked->update([
                'status' => self::STATUS_UPLOADING,
                'error_message' => null,
            ]);

            return $locked;
        });

        $folder = self::TEMP_FOLDER . '/' . $bridgeRequest->uuid;
        $safeFileName = Str::random(20) . '.' . $extension;
        $storedPath = null;

        try {
            $storedPath = $file->storeAs($folder, $safeFileName, self::TEMP_DISK);
            if (! is_string($storedPath) || $storedPath === '' || ! Storage::disk(self::TEMP_DISK)->exists($storedPath)) {
                throw new RuntimeException('تعذر حفظ الملف المؤقت على السيرفر.');
            }

            $mimeType = null;
            try {
                $mimeType = $file->getMimeType();
            } catch (Throwable) {
                $mimeType = null;
            }

            $bridgeRequest = DB::transaction(function () use (
                $bridgeRequest,
                $originalName,
                $storedPath,
                $extension,
                $mimeType,
                $size,
                $clientName,
                $clientMachine
            ) {
                $locked = FileBridgeRequest::query()->lockForUpdate()->findOrFail($bridgeRequest->id);
                $this->expireIfNeeded($locked);
                $locked->refresh();

                if ($locked->status !== self::STATUS_UPLOADING) {
                    throw new RuntimeException($this->statusMessage($locked));
                }

                $locked->update([
                    'status' => self::STATUS_READY,
                    'original_name' => $originalName,
                    'temporary_path' => $storedPath,
                    'extension' => $extension,
                    'mime_type' => $mimeType ?: 'application/octet-stream',
                    'file_size' => $size,
                    'client_name' => Str::limit(trim((string) $clientName), 255, ''),
                    'client_machine' => Str::limit(trim((string) $clientMachine), 255, ''),
                    'uploaded_at' => now(),
                    'error_message' => null,
                ]);

                return $locked;
            });

            $this->logForUser(
                $bridgeRequest,
                'file_bridge.upload_ready',
                'تم رفع ملف محلي عبر File Bridge وأصبح جاهزاً للاستخدام.',
                [
                    'original_name' => $originalName,
                    'file_size' => $size,
                    'client_machine' => $clientMachine,
                ]
            );

            return $bridgeRequest->fresh();
        } catch (Throwable $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk(self::TEMP_DISK)->delete($storedPath);
            }

            FileBridgeRequest::query()
                ->whereKey($bridgeRequest->id)
                ->where('status', self::STATUS_UPLOADING)
                ->update([
                    'status' => self::STATUS_FAILED,
                    'error_message' => Str::limit($exception->getMessage(), 1000, ''),
                ]);

            throw $exception;
        }
    }

    public function markClientFailure(string $uuid, string $token, ?string $message): FileBridgeRequest
    {
        $bridgeRequest = $this->findForClient($uuid, $token, true);

        if ($bridgeRequest->status === self::STATUS_READY) {
            return $bridgeRequest;
        }

        $bridgeRequest->update([
            'status' => self::STATUS_FAILED,
            'error_message' => Str::limit(trim((string) $message), 1000, ''),
        ]);

        return $bridgeRequest;
    }

    public function statusForUser(string $uuid, User $user): FileBridgeRequest
    {
        $bridgeRequest = FileBridgeRequest::query()
            ->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->expireIfNeeded($bridgeRequest);

        return $bridgeRequest->fresh();
    }

    public function cancelForUser(string $uuid, User $user): FileBridgeRequest
    {
        return DB::transaction(function () use ($uuid, $user) {
            $bridgeRequest = FileBridgeRequest::query()
                ->where('uuid', $uuid)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($bridgeRequest->status, [self::STATUS_CONSUMED, self::STATUS_CANCELLED], true)) {
                return $bridgeRequest;
            }

            $temporaryPath = $bridgeRequest->temporary_path;
            $bridgeRequest->update([
                'status' => self::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'error_message' => null,
            ]);

            DB::afterCommit(function () use ($temporaryPath): void {
                if (is_string($temporaryPath) && $temporaryPath !== '') {
                    Storage::disk(self::TEMP_DISK)->delete($temporaryPath);
                }
            });

            return $bridgeRequest;
        });
    }

    public function selectionToken(FileBridgeRequest $bridgeRequest): string
    {
        if ($bridgeRequest->status !== self::STATUS_READY) {
            throw new RuntimeException('ملف File Bridge غير جاهز للاختيار.');
        }

        return Crypt::encryptString(json_encode([
            'type' => 'file_bridge_selection',
            'uuid' => $bridgeRequest->uuid,
            'user_id' => (int) $bridgeRequest->user_id,
            'expires_at' => min(
                $bridgeRequest->expires_at?->getTimestamp() ?? time(),
                time() + self::SELECTION_TOKEN_TTL_SECONDS
            ),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function claimSelection(string $selectionToken, User $user, Document $document): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($selectionToken), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file_bridge_token' => 'اختيار File Bridge غير صالح أو انتهت صلاحيته.',
            ]);
        }

        if (! is_array($payload)
            || ($payload['type'] ?? null) !== 'file_bridge_selection'
            || (int) ($payload['user_id'] ?? 0) !== (int) $user->id
            || (int) ($payload['expires_at'] ?? 0) < time()) {
            throw ValidationException::withMessages([
                'file_bridge_token' => 'اختيار File Bridge غير صالح أو انتهت صلاحيته.',
            ]);
        }

        $uuid = (string) ($payload['uuid'] ?? '');

        $bridgeRequest = FileBridgeRequest::query()
            ->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if (! $bridgeRequest) {
            throw ValidationException::withMessages([
                'file_bridge_token' => 'طلب File Bridge غير موجود.',
            ]);
        }

        $this->expireIfNeeded($bridgeRequest);
        $bridgeRequest->refresh();

        if ($bridgeRequest->status !== self::STATUS_READY) {
            throw ValidationException::withMessages([
                'file_bridge_token' => $this->statusMessage($bridgeRequest),
            ]);
        }

        if ($bridgeRequest->document_id !== null
            && (int) $bridgeRequest->document_id !== (int) $document->id) {
            throw ValidationException::withMessages([
                'file_bridge_token' => 'هذا الاختيار مرتبط بكتاب آخر.',
            ]);
        }

        if (! is_string($bridgeRequest->temporary_path)
            || $bridgeRequest->temporary_path === ''
            || ! Storage::disk(self::TEMP_DISK)->exists($bridgeRequest->temporary_path)) {
            $bridgeRequest->update([
                'status' => self::STATUS_FAILED,
                'error_message' => 'الملف المؤقت غير موجود على السيرفر.',
            ]);

            throw ValidationException::withMessages([
                'file_bridge_token' => 'الملف المؤقت لم يعد موجوداً. أعد اختيار الملف.',
            ]);
        }

        $absolutePath = Storage::disk(self::TEMP_DISK)->path($bridgeRequest->temporary_path);
        $bridgeRequest->update([
            'status' => self::STATUS_CONSUMING,
            'document_id' => $document->id,
        ]);

        return [
            'request' => $bridgeRequest,
            'absolute_path' => $absolutePath,
            'name' => (string) $bridgeRequest->original_name,
            'extension' => (string) $bridgeRequest->extension,
            'mime_type' => (string) ($bridgeRequest->mime_type ?: 'application/octet-stream'),
            'size' => (int) $bridgeRequest->file_size,
        ];
    }

    public function markConsumed(
        FileBridgeRequest $bridgeRequest,
        Document $document,
        DocumentAttachment $attachment
    ): void {
        $temporaryPath = $bridgeRequest->temporary_path;

        $bridgeRequest->update([
            'status' => self::STATUS_CONSUMED,
            'document_id' => $document->id,
            'consumed_at' => now(),
            'error_message' => null,
        ]);

        $this->logForUser(
            $bridgeRequest,
            'file_bridge.attachment_imported',
            'تم استيراد مرفق محلي عبر File Bridge للكتاب رقم ' . $document->reference_number,
            [
                'document_id' => $document->id,
                'attachment_id' => $attachment->id,
                'reference_number' => $document->reference_number,
                'original_name' => $bridgeRequest->original_name,
                'client_machine' => $bridgeRequest->client_machine,
            ],
            $attachment
        );

        DB::afterCommit(function () use ($temporaryPath): void {
            if (is_string($temporaryPath) && $temporaryPath !== '') {
                Storage::disk(self::TEMP_DISK)->delete($temporaryPath);
            }
        });
    }

    public function purgeExpired(): int
    {
        $expired = FileBridgeRequest::query()
            ->where('expires_at', '<', now())
            ->whereIn('status', [
                self::STATUS_PENDING,
                self::STATUS_UPLOADING,
                self::STATUS_READY,
                self::STATUS_FAILED,
            ])
            ->limit(200)
            ->get();

        foreach ($expired as $bridgeRequest) {
            $temporaryPath = $bridgeRequest->temporary_path;
            $bridgeRequest->update([
                'status' => self::STATUS_EXPIRED,
                'error_message' => $bridgeRequest->error_message,
            ]);

            if (is_string($temporaryPath) && $temporaryPath !== '') {
                Storage::disk(self::TEMP_DISK)->delete($temporaryPath);
            }
        }

        return $expired->count();
    }

    public function statusMessage(FileBridgeRequest $bridgeRequest): string
    {
        return match ($bridgeRequest->status) {
            self::STATUS_PENDING => 'الطلب بانتظار فتح برنامج File Bridge.',
            self::STATUS_UPLOADING => 'يجري رفع الملف من الجهاز المحلي.',
            self::STATUS_READY => 'الملف جاهز للاختيار.',
            self::STATUS_CONSUMING => 'يجري ربط الملف بالكتاب.',
            self::STATUS_CONSUMED => 'تم استخدام هذا الاختيار مسبقاً.',
            self::STATUS_CANCELLED => 'تم إلغاء طلب File Bridge.',
            self::STATUS_EXPIRED => 'انتهت صلاحية طلب File Bridge.',
            self::STATUS_FAILED => $bridgeRequest->error_message ?: 'فشلت عملية File Bridge.',
            default => 'حالة طلب File Bridge غير معروفة.',
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

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    private function sanitizeOriginalName(string $name): string
    {
        $name = str_replace(["\0", '/', '\\'], '', trim($name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;
        $name = Str::limit($name, 240, '');

        if ($name === '' || $name === '.' || $name === '..') {
            throw ValidationException::withMessages([
                'original_name' => 'اسم الملف غير صالح.',
            ]);
        }

        return $name;
    }

    private function expireIfNeeded(FileBridgeRequest $bridgeRequest): void
    {
        if ($bridgeRequest->expires_at
            && $bridgeRequest->expires_at->isPast()
            && in_array($bridgeRequest->status, [
                self::STATUS_PENDING,
                self::STATUS_UPLOADING,
                self::STATUS_READY,
                self::STATUS_FAILED,
            ], true)) {
            $temporaryPath = $bridgeRequest->temporary_path;
            $bridgeRequest->update(['status' => self::STATUS_EXPIRED]);

            if (is_string($temporaryPath) && $temporaryPath !== '') {
                Storage::disk(self::TEMP_DISK)->delete($temporaryPath);
            }
        }
    }

    private function logForUser(
        FileBridgeRequest $bridgeRequest,
        string $action,
        string $description,
        array $properties = [],
        ?\Illuminate\Database\Eloquent\Model $model = null
    ): void {
        try {
            ActivityLog::query()->create([
                'user_id' => $bridgeRequest->user_id,
                'action' => $action,
                'model_type' => $model ? get_class($model) : get_class($bridgeRequest),
                'model_id' => $model?->getKey() ?: $bridgeRequest->id,
                'description' => $description,
                'properties' => $properties ?: null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

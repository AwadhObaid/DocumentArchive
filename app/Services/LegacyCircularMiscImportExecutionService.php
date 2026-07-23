<?php

namespace App\Services;

use App\Models\ArchiveCategory;
use App\Models\Circular;
use App\Models\CircularAttachment;
use App\Models\LegacyCircularMiscImportItem;
use App\Models\LegacyCircularMiscImportRun;
use App\Models\MiscBook;
use App\Models\MiscBookAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LegacyCircularMiscImportExecutionService
{
    public const VERSION = 'V86.1';

    private const IMPORTABLE_STATUSES = [
        'ready',
        'needs_review',
        'import_failed',
    ];

    public function importSelected(
        LegacyCircularMiscImportRun $run,
        array $itemIds,
        array $overrides,
        int $userId
    ): array {
        @set_time_limit(0);

        $itemIds = array_values(array_unique(array_map(
            'intval',
            $itemIds
        )));

        $result = [
            'requested' => count($itemIds),
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
            'items' => [],
        ];

        $run->forceFill([
            'import_status' => 'processing',
            'imported_by' => $userId,
            'import_started_at' => $run->import_started_at ?: now(),
            'import_finished_at' => null,
        ])->save();

        foreach ($itemIds as $itemId) {
            try {
                $itemResult = $this->importOne(
                    $run,
                    $itemId,
                    $overrides[$itemId] ?? [],
                    $userId
                );
            } catch (Throwable $exception) {
                report($exception);

                $this->markFailure(
                    $run,
                    $itemId,
                    'فشل غير متوقع أثناء الاستيراد: '
                        . $this->safeMessage($exception),
                    $userId
                );

                $itemResult = [
                    'status' => 'failed',
                    'item_id' => $itemId,
                    'message' => $this->safeMessage($exception),
                ];
            }

            $result['items'][] = $itemResult;

            if ($itemResult['status'] === 'imported') {
                $result['imported']++;
            } elseif ($itemResult['status'] === 'failed') {
                $result['failed']++;
            } else {
                $result['skipped']++;
            }
        }

        $this->refreshRunSummary($run);
        $run->refresh();

        ActivityLogger::log(
            $result['failed'] > 0
                ? 'legacy_circular_misc_import.completed_with_errors'
                : 'legacy_circular_misc_import.completed',
            $result['failed'] > 0
                ? 'اكتملت دفعة استيراد التعاميم والمتفرقات القديمة مع أخطاء.'
                : 'اكتملت دفعة استيراد التعاميم والمتفرقات القديمة.',
            $run,
            [
                'version' => self::VERSION,
                'requested' => $result['requested'],
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
                'source_files_modified' => false,
                'source_files_deleted' => false,
            ]
        );

        return $result;
    }

    private function importOne(
        LegacyCircularMiscImportRun $run,
        int $itemId,
        array $override,
        int $userId
    ): array {
        $item = LegacyCircularMiscImportItem::query()
            ->where('run_id', $run->id)
            ->findOrFail($itemId);

        if ($item->status === 'imported'
            && ($item->circular_id || $item->misc_book_id)) {
            return [
                'status' => 'skipped',
                'item_id' => $item->id,
                'message' => 'سبق استيراد هذا الملف.',
            ];
        }

        if (! in_array(
            $item->status,
            self::IMPORTABLE_STATUSES,
            true
        )) {
            return [
                'status' => 'skipped',
                'item_id' => $item->id,
                'message' => 'حالة الملف الحالية لا تسمح بالاستيراد.',
            ];
        }

        $data = $this->normalizedImportData($item, $override);
        $validationError = $this->validateImportData($item, $data);

        if ($validationError) {
            $this->markFailure($run, $itemId, $validationError, $userId);

            return [
                'status' => 'failed',
                'item_id' => $itemId,
                'message' => $validationError,
            ];
        }

        $source = (string) $item->source_path;
        $verification = $this->verifySource($item, $source);

        if (! $verification['ok']) {
            $this->markFailure(
                $run,
                $itemId,
                $verification['message'],
                $userId
            );

            return [
                'status' => 'failed',
                'item_id' => $itemId,
                'message' => $verification['message'],
            ];
        }

        $sourceSize = $verification['size'];
        $sourceHash = $verification['sha256'];
        $mimeType = $this->detectMimeType($source);

        $duplicate = $this->existingAttachment(
            $sourceHash,
            $sourceSize,
            $source
        );

        if ($duplicate) {
            $this->markDuplicate($item, $duplicate, $userId);

            return [
                'status' => 'skipped',
                'item_id' => $item->id,
                'message' => 'الملف موجود مسبقًا في النظام.',
            ];
        }

        $extension = $this->safeExtension(
            (string) (
                $item->extension
                ?: pathinfo($item->file_name, PATHINFO_EXTENSION)
                ?: 'bin'
            )
        );

        $stagePath = 'legacy-circular-misc-import-staging/'
            . $run->id
            . '/'
            . $item->id
            . '_'
            . Str::uuid()
            . '.'
            . $extension;

        $this->copySourceToLocalStage(
            $source,
            $stagePath,
            $sourceSize,
            $sourceHash
        );

        $finalPath = null;
        $record = null;
        $attachment = null;
        $recordNumber = null;

        try {
            DB::transaction(function () use (
                $run,
                $itemId,
                $data,
                $userId,
                $source,
                $sourceSize,
                $sourceHash,
                $mimeType,
                $extension,
                $stagePath,
                &$finalPath,
                &$record,
                &$attachment,
                &$recordNumber
            ): void {
                $lockedItem = LegacyCircularMiscImportItem::query()
                    ->where('run_id', $run->id)
                    ->lockForUpdate()
                    ->findOrFail($itemId);

                if ($lockedItem->status === 'imported'
                    && ($lockedItem->circular_id
                        || $lockedItem->misc_book_id)) {
                    throw new LegacyCircularMiscAlreadyImportedException();
                }

                if (! in_array(
                    $lockedItem->status,
                    self::IMPORTABLE_STATUSES,
                    true
                )) {
                    throw new RuntimeException(
                        'تغيرت حالة الملف ولم تعد تسمح بالاستيراد.'
                    );
                }

                $duplicate = $this->existingAttachment(
                    $sourceHash,
                    $sourceSize,
                    $source,
                    true
                );

                if ($duplicate) {
                    throw new LegacyCircularMiscDuplicateException(
                        $duplicate['type'],
                        $duplicate['attachment_id'],
                        $duplicate['record_id']
                    );
                }

                $category = ArchiveCategory::query()
                    ->lockForUpdate()
                    ->find($data['category_id']);

                if (! $category
                    || $category->module !== $lockedItem->target_module) {
                    throw new RuntimeException(
                        'التصنيف المحدد غير صالح للوحدة المختارة.'
                    );
                }

                if ($lockedItem->target_module === 'circular') {
                    $number = CircularNumberGenerator::generate();
                    $recordNumber = $number['circular_number'];
                    $safeName = $recordNumber
                        . '_'
                        . Str::random(12)
                        . '.'
                        . $extension;
                    $folder = 'circulars/'
                        . $number['circular_year']
                        . '/'
                        . $recordNumber;
                    $finalPath = $folder . '/' . $safeName;

                    $this->moveStageToFinal($stagePath, $finalPath);

                    $record = Circular::create([
                        'circular_number' => $recordNumber,
                        'circular_year' => $number['circular_year'],
                        'circular_sequence' => $number['circular_sequence'],
                        'original_number' => $data['original_number'],
                        'circular_date' => $data['date'],
                        'subject' => $data['subject'],
                        'category_id' => $category->id,
                        'issuing_entity' => $data['entity'],
                        'scope' => null,
                        'status' => 'active',
                        'workflow_status' => 'draft',
                        'confidentiality' => 'normal',
                        'priority' => 'normal',
                        'keywords' => 'استيراد قديم',
                        'notes' => 'استيراد قديم V86.1 — المسار الأصلي محفوظ في بيانات المرفق.',
                        'search_text' => $this->buildCircularSearchText(
                            $recordNumber,
                            $data,
                            $lockedItem
                        ),
                        'created_by' => $userId,
                    ]);

                    $attachment = CircularAttachment::create([
                        'circular_id' => $record->id,
                        'version_no' => 1,
                        'is_main' => true,
                        'original_name' => $lockedItem->file_name,
                        'file_name' => $safeName,
                        'file_path' => $finalPath,
                        'disk' => 'local',
                        'extension' => $extension,
                        'mime_type' => $mimeType,
                        'file_size' => $sourceSize,
                        'sha256' => $sourceHash,
                        'source_path' => $source,
                        'uploaded_by' => $userId,
                    ]);

                    $lockedItem->forceFill([
                        'circular_id' => $record->id,
                        'misc_book_id' => null,
                        'circular_attachment_id' => $attachment->id,
                        'misc_book_attachment_id' => null,
                    ]);
                } else {
                    $number = MiscBookNumberGenerator::generate();
                    $recordNumber = $number['misc_number'];
                    $safeName = $recordNumber
                        . '_'
                        . Str::random(12)
                        . '.'
                        . $extension;
                    $folder = 'misc-books/'
                        . $number['misc_year']
                        . '/'
                        . $recordNumber;
                    $finalPath = $folder . '/' . $safeName;

                    $this->moveStageToFinal($stagePath, $finalPath);

                    $record = MiscBook::create([
                        'misc_number' => $recordNumber,
                        'misc_year' => $number['misc_year'],
                        'misc_sequence' => $number['misc_sequence'],
                        'original_number' => $data['original_number'],
                        'book_date' => $data['date'],
                        'subject' => $data['subject'],
                        'category_id' => $category->id,
                        'correspondence_direction' => $data['direction'],
                        'nature' => $data['nature'],
                        'sender' => $data['sender'],
                        'receiver' => $data['receiver'],
                        'employee_name' => $data['employee_name'],
                        'authority_name' => $data['authority_name'],
                        'status' => 'active',
                        'workflow_status' => 'draft',
                        'confidentiality' => 'normal',
                        'priority' => 'normal',
                        'keywords' => 'استيراد قديم',
                        'notes' => 'استيراد قديم V86.1 — المسار الأصلي محفوظ في بيانات المرفق.',
                        'search_text' => $this->buildMiscSearchText(
                            $recordNumber,
                            $data,
                            $lockedItem
                        ),
                        'created_by' => $userId,
                    ]);

                    $attachment = MiscBookAttachment::create([
                        'misc_book_id' => $record->id,
                        'version_no' => 1,
                        'is_main' => true,
                        'original_name' => $lockedItem->file_name,
                        'file_name' => $safeName,
                        'file_path' => $finalPath,
                        'disk' => 'local',
                        'extension' => $extension,
                        'mime_type' => $mimeType,
                        'file_size' => $sourceSize,
                        'sha256' => $sourceHash,
                        'source_path' => $source,
                        'uploaded_by' => $userId,
                    ]);

                    $lockedItem->forceFill([
                        'circular_id' => null,
                        'misc_book_id' => $record->id,
                        'circular_attachment_id' => null,
                        'misc_book_attachment_id' => $attachment->id,
                    ]);
                }

                $lockedItem->forceFill([
                    'category_id' => $category->id,
                    'proposed_date' => $data['date'],
                    'proposed_subject' => $data['subject'],
                    'proposed_number' => $recordNumber,
                    'proposed_original_number' => $data['original_number'],
                    'proposed_entity' => $data['entity'],
                    'proposed_direction' => $data['direction'],
                    'proposed_nature' => $data['nature'],
                    'status' => 'imported',
                    'notes' => 'تم إنشاء السجل ونسخ الملف والتحقق من الحجم والبصمة.',
                    'import_attempts' => ((int) $lockedItem->import_attempts) + 1,
                    'import_error' => null,
                    'copied_file_size' => $sourceSize,
                    'copied_sha256' => $sourceHash,
                    'imported_by' => $userId,
                    'source_verified_at' => now(),
                    'imported_at' => now(),
                ])->save();
            }, 3);
        } catch (LegacyCircularMiscAlreadyImportedException) {
            $this->deleteLocalPath($stagePath);

            return [
                'status' => 'skipped',
                'item_id' => $itemId,
                'message' => 'سبق استيراد هذا الملف.',
            ];
        } catch (LegacyCircularMiscDuplicateException $exception) {
            $this->deleteLocalPath($stagePath);
            $item->refresh();
            $this->markDuplicate(
                $item,
                [
                    'type' => $exception->type,
                    'attachment_id' => $exception->attachmentId,
                    'record_id' => $exception->recordId,
                ],
                $userId
            );

            return [
                'status' => 'skipped',
                'item_id' => $itemId,
                'message' => 'ظهر الملف في النظام أثناء تنفيذ الدفعة.',
            ];
        } catch (Throwable $exception) {
            $this->deleteLocalPath($stagePath);

            if ($finalPath) {
                $this->deleteLocalPath($finalPath);
            }

            throw $exception;
        }

        if (! $record || ! $attachment || ! $recordNumber) {
            throw new RuntimeException(
                'لم تُرجع عملية الاستيراد سجلًا ومرفقًا صالحين.'
            );
        }

        ActivityLogger::log(
            $item->target_module === 'circular'
                ? 'legacy_circular_misc_import.circular_imported'
                : 'legacy_circular_misc_import.misc_book_imported',
            ($item->target_module === 'circular'
                ? 'تم استيراد التعميم القديم رقم '
                : 'تم استيراد الكتاب المتفرق القديم رقم ')
                . $recordNumber,
            $record,
            [
                'version' => self::VERSION,
                'run_id' => $run->id,
                'item_id' => $itemId,
                'record_number' => $recordNumber,
                'attachment_id' => $attachment->id,
                'source_path' => $source,
                'destination_path' => $attachment->file_path,
                'file_size' => $sourceSize,
                'sha256' => $sourceHash,
                'source_file_modified' => false,
                'source_file_deleted' => false,
            ]
        );

        return [
            'status' => 'imported',
            'item_id' => $itemId,
            'record_id' => $record->id,
            'record_number' => $recordNumber,
            'attachment_id' => $attachment->id,
            'message' => 'تم الاستيراد والتحقق بنجاح.',
        ];
    }

    private function normalizedImportData(
        LegacyCircularMiscImportItem $item,
        array $override
    ): array {
        $categoryId = $override['category_id'] ?? $item->category_id;

        return [
            'date' => trim((string) (
                $override['date']
                ?: optional($item->proposed_date)->format('Y-m-d')
            )),
            'subject' => $this->normalizeText(
                (string) ($override['subject'] ?: $item->proposed_subject)
            ),
            'original_number' => $this->normalizeNullable(
                $override['original_number']
                    ?? $item->proposed_original_number
            ),
            'category_id' => $categoryId ? (int) $categoryId : null,
            'entity' => $this->normalizeNullable(
                $override['entity'] ?? $item->proposed_entity
            ),
            'direction' => $override['direction']
                ?: ($item->proposed_direction ?: 'incoming'),
            'nature' => $override['nature']
                ?: ($item->proposed_nature ?: 'unspecified'),
            'sender' => $this->normalizeNullable(
                $override['sender'] ?? null
            ),
            'receiver' => $this->normalizeNullable(
                $override['receiver'] ?? null
            ),
            'employee_name' => $this->normalizeNullable(
                $override['employee_name'] ?? null
            ),
            'authority_name' => $this->normalizeNullable(
                $override['authority_name'] ?? null
            ),
        ];
    }

    private function validateImportData(
        LegacyCircularMiscImportItem $item,
        array $data
    ): ?string {
        if ($data['subject'] === '') {
            return 'الموضوع مطلوب قبل الاستيراد.';
        }

        if (! $this->isValidDate($data['date'])) {
            return 'التاريخ غير صالح.';
        }

        if (! $data['category_id']) {
            return 'التصنيف مطلوب قبل الاستيراد.';
        }

        $category = ArchiveCategory::find($data['category_id']);

        if (! $category || $category->module !== $item->target_module) {
            return 'التصنيف المحدد لا يتبع الوحدة الصحيحة.';
        }

        if ($item->target_module === 'misc_book') {
            if (! in_array(
                $data['direction'],
                ['incoming', 'outgoing', 'internal'],
                true
            )) {
                return 'اتجاه المخاطبة غير صالح.';
            }

            if (! in_array(
                $data['nature'],
                ['military', 'civil', 'unspecified'],
                true
            )) {
                return 'طبيعة المخاطبة غير صالحة.';
            }
        }

        return null;
    }

    private function verifySource(
        LegacyCircularMiscImportItem $item,
        string $source
    ): array {
        if ($source === '' || ! is_file($source) || ! is_readable($source)) {
            return [
                'ok' => false,
                'message' => 'الملف الأصلي غير موجود أو غير قابل للقراءة بواسطة PHP.',
            ];
        }

        $size = @filesize($source);

        if ($size === false) {
            return [
                'ok' => false,
                'message' => 'تعذر قراءة حجم الملف الأصلي.',
            ];
        }

        $sha256 = @hash_file('sha256', $source);

        if (! is_string($sha256) || strlen($sha256) !== 64) {
            return [
                'ok' => false,
                'message' => 'تعذر حساب بصمة SHA-256 للملف الأصلي.',
            ];
        }

        $sha256 = strtolower($sha256);

        if ((int) $size !== (int) $item->file_size
            || ! hash_equals(
                strtolower((string) $item->sha256),
                $sha256
            )) {
            return [
                'ok' => false,
                'message' => 'تغير الملف الأصلي بعد الفحص. أعد الفحص قبل الاستيراد.',
            ];
        }

        return [
            'ok' => true,
            'size' => (int) $size,
            'sha256' => $sha256,
        ];
    }

    private function existingAttachment(
        string $sha256,
        int $fileSize,
        string $sourcePath,
        bool $lock = false
    ): ?array {
        $circularQuery = CircularAttachment::withTrashed()
            ->where(function ($query) use (
                $sha256,
                $fileSize,
                $sourcePath
            ) {
                $query->where(function ($hashQuery) use (
                    $sha256,
                    $fileSize
                ) {
                    $hashQuery->where('sha256', $sha256)
                        ->where('file_size', $fileSize);
                })->orWhere('source_path', $sourcePath);
            });

        if ($lock) {
            $circularQuery->lockForUpdate();
        }

        $circular = $circularQuery->first();

        if ($circular) {
            return [
                'type' => 'circular',
                'attachment_id' => (int) $circular->id,
                'record_id' => (int) $circular->circular_id,
            ];
        }

        $miscQuery = MiscBookAttachment::withTrashed()
            ->where(function ($query) use (
                $sha256,
                $fileSize,
                $sourcePath
            ) {
                $query->where(function ($hashQuery) use (
                    $sha256,
                    $fileSize
                ) {
                    $hashQuery->where('sha256', $sha256)
                        ->where('file_size', $fileSize);
                })->orWhere('source_path', $sourcePath);
            });

        if ($lock) {
            $miscQuery->lockForUpdate();
        }

        $misc = $miscQuery->first();

        return $misc ? [
            'type' => 'misc_book',
            'attachment_id' => (int) $misc->id,
            'record_id' => (int) $misc->misc_book_id,
        ] : null;
    }

    private function copySourceToLocalStage(
        string $source,
        string $stagePath,
        int $expectedSize,
        string $expectedHash
    ): void {
        $stream = @fopen($source, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('تعذر فتح الملف الأصلي للنسخ.');
        }

        try {
            $saved = Storage::disk('local')->put($stagePath, $stream);
        } finally {
            fclose($stream);
        }

        if (! $saved || ! Storage::disk('local')->exists($stagePath)) {
            $this->deleteLocalPath($stagePath);
            throw new RuntimeException(
                'تعذر نسخ الملف إلى منطقة التحقق المؤقتة.'
            );
        }

        $copiedSize = (int) Storage::disk('local')->size($stagePath);

        if ($copiedSize !== $expectedSize) {
            $this->deleteLocalPath($stagePath);
            throw new RuntimeException(
                'فشل التحقق من حجم الملف بعد النسخ.'
            );
        }

        $absoluteStagePath = Storage::disk('local')->path($stagePath);
        $copiedHash = @hash_file('sha256', $absoluteStagePath);

        if (! is_string($copiedHash)
            || ! hash_equals($expectedHash, strtolower($copiedHash))) {
            $this->deleteLocalPath($stagePath);
            throw new RuntimeException(
                'فشل التحقق من بصمة SHA-256 بعد النسخ.'
            );
        }
    }

    private function moveStageToFinal(
        string $stagePath,
        string $finalPath
    ): void {
        if (Storage::disk('local')->exists($finalPath)) {
            throw new RuntimeException(
                'مسار المرفق النهائي مستخدم مسبقًا.'
            );
        }

        if (! Storage::disk('local')->move($stagePath, $finalPath)) {
            throw new RuntimeException(
                'تعذر نقل النسخة المتحققة إلى مجلد السجل.'
            );
        }
    }

    private function markFailure(
        LegacyCircularMiscImportRun $run,
        int $itemId,
        string $message,
        int $userId
    ): void {
        $item = LegacyCircularMiscImportItem::query()
            ->where('run_id', $run->id)
            ->find($itemId);

        if (! $item || $item->status === 'imported') {
            return;
        }

        $item->forceFill([
            'status' => 'import_failed',
            'import_error' => mb_substr($message, 0, 5000),
            'notes' => mb_substr($message, 0, 2000),
            'import_attempts' => ((int) $item->import_attempts) + 1,
            'imported_by' => $userId,
        ])->save();
    }

    private function markDuplicate(
        LegacyCircularMiscImportItem $item,
        array $duplicate,
        int $userId
    ): void {
        $item->forceFill([
            'status' => 'existing',
            'existing_type' => $duplicate['type'],
            'existing_id' => $duplicate['attachment_id'],
            'circular_id' => $duplicate['type'] === 'circular'
                ? $duplicate['record_id']
                : null,
            'misc_book_id' => $duplicate['type'] === 'misc_book'
                ? $duplicate['record_id']
                : null,
            'notes' => 'المحتوى موجود مسبقًا ضمن مرفقات النظام.',
            'import_error' => null,
            'import_attempts' => ((int) $item->import_attempts) + 1,
            'imported_by' => $userId,
            'source_verified_at' => now(),
        ])->save();
    }

    private function refreshRunSummary(
        LegacyCircularMiscImportRun $run
    ): void {
        $counts = LegacyCircularMiscImportItem::query()
            ->where('run_id', $run->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $imported = (int) ($counts['imported'] ?? 0);
        $failed = (int) ($counts['import_failed'] ?? 0);
        $remaining = (int) ($counts['ready'] ?? 0)
            + (int) ($counts['needs_review'] ?? 0);
        $skipped = (int) ($counts['existing'] ?? 0)
            + (int) ($counts['duplicate'] ?? 0)
            + (int) ($counts['unreadable'] ?? 0)
            + (int) ($counts['unsupported'] ?? 0);

        $status = match (true) {
            $failed > 0 && $remaining === 0
                => 'completed_with_errors',
            $remaining > 0 && ($imported > 0 || $failed > 0)
                => 'partial',
            $remaining > 0
                => 'not_started',
            default
                => 'completed',
        };

        $run->forceFill([
            'ready_count' => (int) ($counts['ready'] ?? 0),
            'needs_review_count' => (int) ($counts['needs_review'] ?? 0),
            'existing_count' => (int) ($counts['existing'] ?? 0),
            'duplicate_count' => (int) ($counts['duplicate'] ?? 0),
            'unreadable_count' => (int) ($counts['unreadable'] ?? 0),
            'unsupported_count' => (int) ($counts['unsupported'] ?? 0),
            'import_status' => $status,
            'import_total' => $imported + $failed + $remaining,
            'imported_files' => $imported,
            'import_failed_files' => $failed,
            'import_skipped_files' => $skipped,
            'import_finished_at' => $remaining === 0 ? now() : null,
        ])->save();
    }

    private function buildCircularSearchText(
        string $number,
        array $data,
        LegacyCircularMiscImportItem $item
    ): string {
        return $this->normalizeText(implode(' ', array_filter([
            $number,
            $data['original_number'],
            $data['date'],
            $data['subject'],
            $data['entity'],
            $item->file_name,
            $item->relative_path,
        ])));
    }

    private function buildMiscSearchText(
        string $number,
        array $data,
        LegacyCircularMiscImportItem $item
    ): string {
        return $this->normalizeText(implode(' ', array_filter([
            $number,
            $data['original_number'],
            $data['date'],
            $data['subject'],
            $data['entity'],
            $data['sender'],
            $data['receiver'],
            $data['employee_name'],
            $data['authority_name'],
            $item->file_name,
            $item->relative_path,
        ])));
    }

    private function normalizeText(?string $value): string
    {
        return trim((string) preg_replace(
            '/\s+/u',
            ' ',
            (string) $value
        ));
    }

    private function normalizeNullable(?string $value): ?string
    {
        $value = $this->normalizeText($value);
        return $value === '' ? null : $value;
    }

    private function isValidDate(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = array_map(
            'intval',
            explode('-', $date)
        );

        return checkdate($month, $day, $year);
    }

    private function safeExtension(string $extension): string
    {
        $extension = strtolower((string) preg_replace(
            '/[^a-z0-9]+/i',
            '',
            $extension
        ));

        return $extension !== '' ? mb_substr($extension, 0, 20) : 'bin';
    }

    private function detectMimeType(string $source): string
    {
        $mime = @mime_content_type($source);
        return is_string($mime) && $mime !== ''
            ? $mime
            : 'application/octet-stream';
    }

    private function deleteLocalPath(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    private function safeMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        return $message !== ''
            ? mb_substr($message, 0, 1000)
            : 'خطأ غير معروف.';
    }
}

class LegacyCircularMiscAlreadyImportedException extends RuntimeException
{
}

class LegacyCircularMiscDuplicateException extends RuntimeException
{
    public function __construct(
        public readonly string $type,
        public readonly int $attachmentId,
        public readonly int $recordId
    ) {
        parent::__construct('Duplicate attachment detected.');
    }
}

<?php

namespace App\Services;

use App\Models\LegacyMemoImportItem;
use App\Models\LegacyMemoImportRun;
use App\Models\Memo;
use App\Models\MemoAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LegacyMemoImportExecutionService
{
    public const VERSION = 'V84.1';

    private const IMPORTABLE_STATUSES = [
        'ready',
        'needs_review',
        'import_failed',
    ];

    public function importSelected(
        LegacyMemoImportRun $run,
        array $itemIds,
        array $overrides,
        int $userId
    ): array {
        @set_time_limit(0);

        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));

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
                ? 'memo_legacy_import.import_completed_with_errors'
                : 'memo_legacy_import.import_completed',
            $result['failed'] > 0
                ? 'اكتملت دفعة استيراد المذكرات القديمة مع أخطاء.'
                : 'اكتملت دفعة استيراد المذكرات القديمة.',
            $run,
            [
                'version' => self::VERSION,
                'requested' => $result['requested'],
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
                'source_root' => $run->source_root,
                'source_files_modified' => false,
                'source_files_deleted' => false,
            ]
        );

        return $result;
    }

    private function importOne(
        LegacyMemoImportRun $run,
        int $itemId,
        array $override,
        int $userId
    ): array {
        $item = LegacyMemoImportItem::query()
            ->where('run_id', $run->id)
            ->findOrFail($itemId);

        if ($item->status === 'imported' && $item->memo_id) {
            return [
                'status' => 'skipped',
                'item_id' => $item->id,
                'memo_id' => $item->memo_id,
                'message' => 'سبق استيراد هذا الملف.',
            ];
        }

        if (! in_array($item->status, self::IMPORTABLE_STATUSES, true)) {
            return [
                'status' => 'skipped',
                'item_id' => $item->id,
                'message' => 'حالة الملف الحالية لا تسمح بالاستيراد.',
            ];
        }

        $subject = $this->normalizeText(
            (string) ($override['subject'] ?? $item->proposed_subject ?? '')
        );
        $memoDate = trim(
            (string) ($override['memo_date']
                ?? optional($item->proposed_memo_date)->format('Y-m-d')
                ?? '')
        );

        if ($subject === '') {
            $this->markFailure(
                $run,
                $itemId,
                'موضوع المذكرة مطلوب قبل الاستيراد.',
                $userId
            );

            return [
                'status' => 'failed',
                'item_id' => $itemId,
                'message' => 'موضوع المذكرة مطلوب.',
            ];
        }

        if (! $this->isValidDate($memoDate)) {
            $this->markFailure(
                $run,
                $itemId,
                'تاريخ المذكرة غير صالح.',
                $userId
            );

            return [
                'status' => 'failed',
                'item_id' => $itemId,
                'message' => 'تاريخ المذكرة غير صالح.',
            ];
        }

        $source = (string) $item->source_path;

        if ($source === '' || ! is_file($source) || ! is_readable($source)) {
            $this->markFailure(
                $run,
                $itemId,
                'الملف الأصلي غير موجود أو غير قابل للقراءة بواسطة PHP.',
                $userId
            );

            return [
                'status' => 'failed',
                'item_id' => $itemId,
                'message' => 'تعذر قراءة الملف الأصلي.',
            ];
        }

        $sourceSize = @filesize($source);

        if ($sourceSize === false) {
            $this->markFailure(
                $run,
                $itemId,
                'تعذر قراءة حجم الملف الأصلي.',
                $userId
            );

            return [
                'status' => 'failed',
                'item_id' => $itemId,
                'message' => 'تعذر قراءة حجم الملف الأصلي.',
            ];
        }

        $sourceHash = @hash_file('sha256', $source);

        if (! is_string($sourceHash) || strlen($sourceHash) !== 64) {
            $this->markFailure(
                $run,
                $itemId,
                'تعذر حساب بصمة الملف الأصلي قبل الاستيراد.',
                $userId
            );

            return [
                'status' => 'failed',
                'item_id' => $itemId,
                'message' => 'تعذر حساب بصمة الملف الأصلي.',
            ];
        }

        $sourceHash = strtolower($sourceHash);

        if ((int) $sourceSize !== (int) $item->file_size
            || ! hash_equals(
                strtolower((string) $item->sha256),
                $sourceHash
            )) {
            $this->markFailure(
                $run,
                $itemId,
                'تغير الملف الأصلي بعد عملية الفحص. أعد فحص المجلد قبل الاستيراد.',
                $userId
            );

            return [
                'status' => 'failed',
                'item_id' => $itemId,
                'message' => 'تغير الملف منذ الفحص.',
            ];
        }

        $duplicate = MemoAttachment::query()
            ->with('memo')
            ->where('sha256', $sourceHash)
            ->where('file_size', (int) $sourceSize)
            ->first();

        if ($duplicate) {
            $this->markDuplicate(
                $item,
                $duplicate,
                $userId
            );

            return [
                'status' => 'skipped',
                'item_id' => $item->id,
                'memo_id' => $duplicate->memo_id,
                'message' => 'الملف موجود مسبقًا في النظام.',
            ];
        }

        $extension = strtolower(
            (string) (
                $item->extension
                ?: pathinfo($item->original_name, PATHINFO_EXTENSION)
                ?: 'bin'
            )
        );

        $stagePath = 'memo-import-staging/'
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
            (int) $sourceSize,
            $sourceHash
        );

        $finalPath = null;
        $memo = null;
        $attachment = null;

        try {
            DB::transaction(function () use (
                $run,
                $itemId,
                $subject,
                $memoDate,
                $userId,
                $sourceSize,
                $sourceHash,
                $extension,
                $stagePath,
                &$finalPath,
                &$memo,
                &$attachment
            ): void {
                $lockedItem = LegacyMemoImportItem::query()
                    ->where('run_id', $run->id)
                    ->lockForUpdate()
                    ->findOrFail($itemId);

                if ($lockedItem->status === 'imported'
                    && $lockedItem->memo_id) {
                    throw new AlreadyImportedException(
                        (int) $lockedItem->memo_id
                    );
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

                $duplicate = MemoAttachment::query()
                    ->with('memo')
                    ->where('sha256', $sourceHash)
                    ->where('file_size', (int) $sourceSize)
                    ->lockForUpdate()
                    ->first();

                if ($duplicate) {
                    throw new DuplicateMemoAttachmentException(
                        (int) $duplicate->id,
                        (int) $duplicate->memo_id
                    );
                }

                $number = MemoNumberGenerator::generate();
                $safeName = $number['memo_number']
                    . '_'
                    . Str::random(12)
                    . '.'
                    . $extension;
                $folder = 'memos/' . $number['memo_number'];
                $finalPath = $folder . '/' . $safeName;

                if (Storage::disk('local')->exists($finalPath)) {
                    throw new RuntimeException(
                        'مسار المرفق النهائي مستخدم مسبقًا.'
                    );
                }

                if (! Storage::disk('local')->move(
                    $stagePath,
                    $finalPath
                )) {
                    throw new RuntimeException(
                        'تعذر نقل النسخة المتحققة إلى مجلد المذكرة.'
                    );
                }

                $memo = Memo::create([
                    'memo_number' => $number['memo_number'],
                    'memo_sequence' => $number['memo_sequence'],
                    'memo_date' => $memoDate,
                    'subject' => $subject,
                    'description'
                        => 'مذكرة قديمة مستوردة من الأرشيف الشبكي.',
                    'department_id'
                        => $lockedItem->proposed_department_id,
                    'sender' => $this->normalizeNullable(
                        $lockedItem->proposed_sender
                    ),
                    'receiver' => $this->normalizeNullable(
                        $lockedItem->proposed_receiver
                    ),
                    'status' => 'active',
                    'workflow_status' => 'draft',
                    'notes' => 'استيراد قديم V84.1 — المسار الأصلي محفوظ '
                        . 'في بيانات المرفق.',
                    'created_by' => $userId,
                    'search_text' => $this->buildSearchText(
                        $number['memo_number'],
                        $memoDate,
                        $subject,
                        $lockedItem
                    ),
                ]);

                $attachment = MemoAttachment::create([
                    'memo_id' => $memo->id,
                    'version_no' => 1,
                    'is_main' => true,
                    'original_name' => $lockedItem->original_name,
                    'file_name' => $safeName,
                    'file_path' => $finalPath,
                    'disk' => 'local',
                    'extension' => $extension,
                    'mime_type' => $lockedItem->mime_type
                        ?: 'application/octet-stream',
                    'file_size' => (int) $sourceSize,
                    'sha256' => $sourceHash,
                    'source_path' => $lockedItem->source_path,
                    'uploaded_by' => $userId,
                ]);

                $payload = array_merge(
                    $lockedItem->payload ?: [],
                    [
                        'import_version' => self::VERSION,
                        'imported_memo_number' => $memo->memo_number,
                        'imported_attachment_id' => $attachment->id,
                        'destination_disk' => 'local',
                        'destination_path' => $finalPath,
                        'source_size_verified' => true,
                        'source_sha256_verified' => true,
                        'copied_size_verified' => true,
                        'copied_sha256_verified' => true,
                        'source_file_modified' => false,
                        'source_file_deleted' => false,
                    ]
                );

                $lockedItem->forceFill([
                    'memo_id' => $memo->id,
                    'imported_attachment_id' => $attachment->id,
                    'status' => 'imported',
                    'message' => 'تم إنشاء المذكرة ونسخ الملف والتحقق من الحجم والبصمة.',
                    'proposed_memo_number' => $memo->memo_number,
                    'proposed_memo_sequence' => $memo->memo_sequence,
                    'proposed_memo_date' => $memoDate,
                    'proposed_subject' => $subject,
                    'payload' => $payload,
                    'import_attempts'
                        => ((int) $lockedItem->import_attempts) + 1,
                    'imported_by' => $userId,
                    'imported_at' => now(),
                    'source_verified_at' => now(),
                    'copied_file_size' => (int) $sourceSize,
                    'copied_sha256' => $sourceHash,
                ])->save();
            }, 3);
        } catch (AlreadyImportedException $exception) {
            $this->deleteLocalPath($stagePath);

            return [
                'status' => 'skipped',
                'item_id' => $itemId,
                'memo_id' => $exception->memoId,
                'message' => 'سبق استيراد هذا الملف.',
            ];
        } catch (DuplicateMemoAttachmentException $exception) {
            $this->deleteLocalPath($stagePath);

            $duplicate = MemoAttachment::query()
                ->find($exception->attachmentId);

            if ($duplicate) {
                $item->refresh();
                $this->markDuplicate($item, $duplicate, $userId);
            }

            return [
                'status' => 'skipped',
                'item_id' => $itemId,
                'memo_id' => $exception->memoId,
                'message' => 'ظهر الملف في النظام أثناء تنفيذ الدفعة.',
            ];
        } catch (Throwable $exception) {
            $this->deleteLocalPath($stagePath);

            if ($finalPath) {
                $this->deleteLocalPath($finalPath);
            }

            throw $exception;
        }

        if (! $memo || ! $attachment) {
            throw new RuntimeException(
                'لم تُرجع عملية الاستيراد مذكرة ومرفقًا صالحين.'
            );
        }

        ActivityLogger::log(
            'memo_legacy_import.memo_imported',
            'تم استيراد المذكرة القديمة رقم ' . $memo->memo_number,
            $memo,
            [
                'version' => self::VERSION,
                'run_id' => $run->id,
                'item_id' => $itemId,
                'memo_number' => $memo->memo_number,
                'attachment_id' => $attachment->id,
                'source_path' => $item->source_path,
                'destination_path' => $attachment->file_path,
                'file_size' => (int) $sourceSize,
                'sha256' => $sourceHash,
                'source_file_modified' => false,
                'source_file_deleted' => false,
            ]
        );

        return [
            'status' => 'imported',
            'item_id' => $itemId,
            'memo_id' => $memo->id,
            'memo_number' => $memo->memo_number,
            'attachment_id' => $attachment->id,
            'message' => 'تم الاستيراد والتحقق بنجاح.',
        ];
    }

    private function copySourceToLocalStage(
        string $source,
        string $stagePath,
        int $expectedSize,
        string $expectedHash
    ): void {
        $stream = @fopen($source, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException(
                'تعذر فتح الملف الأصلي للنسخ.'
            );
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

    private function markFailure(
        LegacyMemoImportRun $run,
        int $itemId,
        string $message,
        int $userId
    ): void {
        $item = LegacyMemoImportItem::query()
            ->where('run_id', $run->id)
            ->find($itemId);

        if (! $item || $item->status === 'imported') {
            return;
        }

        $item->forceFill([
            'status' => 'import_failed',
            'message' => mb_substr($message, 0, 2000),
            'import_attempts' => ((int) $item->import_attempts) + 1,
            'imported_by' => $userId,
        ])->save();
    }

    private function markDuplicate(
        LegacyMemoImportItem $item,
        MemoAttachment $attachment,
        int $userId
    ): void {
        $payload = array_merge($item->payload ?: [], [
            'existing_attachment_id' => $attachment->id,
            'existing_memo_number' => $attachment->memo?->memo_number,
            'duplicate_detected_during_import' => true,
        ]);

        $item->forceFill([
            'memo_id' => $attachment->memo_id,
            'status' => 'duplicate_system',
            'message' => 'المحتوى موجود مسبقًا ضمن مرفقات مذكرة في النظام.',
            'payload' => $payload,
            'import_attempts' => ((int) $item->import_attempts) + 1,
            'imported_by' => $userId,
            'source_verified_at' => now(),
        ])->save();
    }

    private function refreshRunSummary(
        LegacyMemoImportRun $run
    ): void {
        $counts = LegacyMemoImportItem::query()
            ->where('run_id', $run->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $imported = (int) ($counts['imported'] ?? 0);
        $failed = (int) ($counts['import_failed'] ?? 0);
        $remaining = (int) ($counts['ready'] ?? 0)
            + (int) ($counts['needs_review'] ?? 0);
        $skipped = (int) ($counts['duplicate_system'] ?? 0)
            + (int) ($counts['duplicate_scan'] ?? 0)
            + (int) ($counts['unreadable'] ?? 0)
            + (int) ($counts['unsupported'] ?? 0)
            + (int) ($counts['failed'] ?? 0);

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

        $notes = array_merge($run->notes ?: [], [
            'import_version' => self::VERSION,
            'import_source_files_modified' => false,
            'import_source_files_deleted' => false,
            'last_import_summary_at' => now()->toIso8601String(),
        ]);

        $run->forceFill([
            'import_status' => $status,
            'import_total' => $imported + $failed + $remaining,
            'imported_files' => $imported,
            'import_failed_files' => $failed,
            'import_skipped_files' => $skipped,
            'import_finished_at' => $remaining === 0 ? now() : null,
            'notes' => $notes,
        ])->save();
    }

    private function buildSearchText(
        string $memoNumber,
        string $memoDate,
        string $subject,
        LegacyMemoImportItem $item
    ): string {
        return $this->normalizeText(implode(' ', array_filter([
            $memoNumber,
            $memoDate,
            $subject,
            $item->proposed_sender,
            $item->proposed_receiver,
            $item->original_name,
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

    private function deleteLocalPath(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function safeMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return mb_substr(
            $message !== '' ? $message : 'خطأ غير محدد.',
            0,
            1000
        );
    }
}

class AlreadyImportedException extends RuntimeException
{
    public function __construct(public readonly int $memoId)
    {
        parent::__construct('سبق استيراد الملف.');
    }
}

class DuplicateMemoAttachmentException extends RuntimeException
{
    public function __construct(
        public readonly int $attachmentId,
        public readonly int $memoId
    ) {
        parent::__construct('الملف موجود مسبقًا في النظام.');
    }
}

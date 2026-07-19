<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\LegacyArchiveImportItem;
use App\Models\LegacyArchiveImportRun;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class LegacyArchiveImportService
{
    public const SOURCE_NAME = 'ESIS_TbArchive';

    public function __construct(
        private readonly LegacyArchiveCsvReader $reader,
        private readonly BookAttachmentSmartPathService $pathService,
    ) {
    }

    public function dryRun(
        string $sourceFile,
        ?string $sourceRootOverride,
        bool $importMissingWithoutFile,
        int $limit = 100000
    ): LegacyArchiveImportRun {
        return $this->process(
            'dry_run',
            $sourceFile,
            $sourceRootOverride,
            $importMissingWithoutFile,
            $limit
        );
    }

    public function execute(
        string $sourceFile,
        ?string $sourceRootOverride,
        bool $importMissingWithoutFile,
        int $limit = 100000
    ): LegacyArchiveImportRun {
        @set_time_limit(0);

        if (! method_exists($this->pathService, 'storeExistingFile')) {
            throw new RuntimeException('خدمة نسخ المرفقات القديمة غير مكتملة. ثبّت تحديث V75/V80 وإصلاح V3 أولًا.');
        }

        return $this->process(
            'execute',
            $sourceFile,
            $sourceRootOverride,
            $importMissingWithoutFile,
            $limit
        );
    }

    private function process(
        string $mode,
        string $sourceFile,
        ?string $sourceRootOverride,
        bool $importMissingWithoutFile,
        int $limit
    ): LegacyArchiveImportRun {
        $rows = $this->reader->read($sourceFile, $limit);
        $sourceRootOverride = $this->normalizePath($sourceRootOverride);

        $run = LegacyArchiveImportRun::create([
            'source_name' => self::SOURCE_NAME,
            'source_file_path' => $sourceFile,
            'original_filename' => basename($sourceFile),
            'mode' => $mode,
            'status' => 'processing',
            'total_rows' => count($rows),
            'source_root_override' => $sourceRootOverride,
            'import_missing_without_file' => $importMissingWithoutFile,
            'created_by' => Auth::id(),
            'started_at' => now(),
            'notes' => [
                'copy_mode' => 'copy_only',
                'source_files_are_never_deleted' => true,
                'column_count' => count(LegacyArchiveCsvReader::COLUMNS),
            ],
        ]);

        $counters = [
            'ready_rows' => 0,
            'imported_rows' => 0,
            'duplicate_rows' => 0,
            'missing_file_rows' => 0,
            'failed_rows' => 0,
            'skipped_rows' => 0,
            'copied_files' => 0,
            'copied_bytes' => 0,
        ];

        try {
            foreach ($rows as $row) {
                $analysis = $this->analyzeRow($row, $sourceRootOverride);

                if ($mode === 'dry_run') {
                    $this->recordAnalysisItem($run, $analysis);
                    $this->incrementDryRunCounters($counters, $analysis);
                    continue;
                }

                $result = $this->importRow($run, $row, $analysis, $importMissingWithoutFile);
                $this->incrementExecuteCounters($counters, $result);
            }

            if ($mode === 'execute') {
                $this->syncReferenceCounters();
            }

            $run->update(array_merge($counters, [
                'status' => $counters['failed_rows'] > 0 ? 'completed_with_errors' : 'completed',
                'finished_at' => now(),
            ]));

            ActivityLogger::log(
                $mode === 'execute' ? 'legacy_archive.import_completed' : 'legacy_archive.dry_run_completed',
                $mode === 'execute'
                    ? 'اكتمل استيراد الأرشيف القديم.'
                    : 'اكتمل الفحص التجريبي للأرشيف القديم.',
                $run,
                array_merge($counters, ['total_rows' => count($rows)])
            );
        } catch (Throwable $exception) {
            $run->update(array_merge($counters, [
                'status' => 'failed',
                'finished_at' => now(),
                'notes' => array_merge($run->notes ?: [], ['fatal_error' => $exception->getMessage()]),
            ]));

            throw $exception;
        }

        return $run->fresh();
    }

    private function analyzeRow(array $row, ?string $sourceRootOverride): array
    {
        $recordId = $this->positiveInteger($row['ID'] ?? null);
        $referenceNumber = trim((string) ($row['ArchiveNum'] ?? ''));
        $date = $this->parseDate($row['ArchiveDate'] ?? null);
        $subject = trim((string) ($row['ArchiveSubject'] ?? ''));

        $status = 'ready';
        $message = 'السجل جاهز للاستيراد.';

        if ($recordId === null || $referenceNumber === '' || $date === null || $subject === '') {
            $status = 'invalid';
            $message = 'السجل لا يحتوي رقم سجل قديم أو رقم كتاب أو تاريخ أو موضوع صحيح.';
        }

        $legacyDuplicate = $recordId !== null
            && Document::withTrashed()
                ->where('legacy_source', self::SOURCE_NAME)
                ->where('legacy_record_id', $recordId)
                ->exists();

        $referenceDuplicate = $date !== null && $referenceNumber !== ''
            && Document::withTrashed()
                ->where('reference_year', (int) $date->year)
                ->where('reference_number', $referenceNumber)
                ->exists();

        if ($legacyDuplicate) {
            $status = 'duplicate_legacy';
            $message = 'هذا السجل مستورد سابقًا اعتمادًا على رقم ID القديم.';
        } elseif ($referenceDuplicate) {
            $status = 'duplicate_reference';
            $message = 'رقم الكتاب موجود مسبقًا في النظام الجديد.';
        }

        $resolvedPath = $this->resolveSourcePath($row, $sourceRootOverride);
        $fileExists = $resolvedPath !== null && is_file($resolvedPath) && is_readable($resolvedPath);
        $sourceSize = $fileExists ? (int) (@filesize($resolvedPath) ?: 0) : null;

        if ($status === 'ready' && ! $fileExists) {
            $status = 'missing_file';
            $message = 'بيانات الكتاب صالحة لكن ملف المرفق لم يتم العثور عليه.';
        }

        [$company, $operation] = $this->classification($row);

        return [
            'row' => $row,
            'source_record_id' => $recordId,
            'reference_number' => $referenceNumber,
            'reference_date' => $date?->toDateString(),
            'reference_year' => $date ? (int) $date->year : null,
            'status' => $status,
            'message' => $message,
            'source_path' => $this->normalizePath($row['FilePath'] ?? null)
                ?: $this->normalizePath($row['OriginalFilePath'] ?? null),
            'resolved_source_path' => $fileExists ? $resolvedPath : null,
            'file_exists' => $fileExists,
            'source_size' => $sourceSize,
            'company' => $company,
            'operation' => $operation,
        ];
    }

    private function importRow(
        LegacyArchiveImportRun $run,
        array $row,
        array $analysis,
        bool $importMissingWithoutFile
    ): array {
        if (in_array($analysis['status'], ['invalid', 'duplicate_legacy', 'duplicate_reference'], true)) {
            $item = $this->recordAnalysisItem($run, $analysis);

            return [
                'status' => $item->status,
                'copied_files' => 0,
                'copied_bytes' => 0,
            ];
        }

        if ($analysis['status'] === 'missing_file' && ! $importMissingWithoutFile) {
            $analysis['status'] = 'skipped';
            $analysis['message'] = 'تم تجاوز السجل لأن ملفه مفقود وخيار استيراد البيانات دون ملف غير مفعّل.';
            $item = $this->recordAnalysisItem($run, $analysis);

            return [
                'status' => $item->status,
                'copied_files' => 0,
                'copied_bytes' => 0,
            ];
        }

        $stored = null;

        try {
            $result = DB::transaction(function () use ($run, $row, $analysis, &$stored) {
                $document = $this->createDocument($row, $analysis);
                $attachment = null;
                $sourceHash = null;
                $targetHash = null;
                $targetSize = null;

                if ($analysis['file_exists'] && $analysis['resolved_source_path']) {
                    $sourcePath = $analysis['resolved_source_path'];
                    $originalName = $this->firstNonEmpty(
                        $row['OriginalFileName'] ?? null,
                        $row['FileName'] ?? null,
                        basename($sourcePath)
                    );

                    $stored = $this->pathService->storeExistingFile(
                        $document,
                        $sourcePath,
                        $originalName,
                        1
                    );

                    $targetPath = (string) ($stored['absolute_path'] ?? '');
                    if ($targetPath === '' || ! is_file($targetPath)) {
                        throw new RuntimeException('تمت محاولة النسخ لكن الملف الهدف غير موجود.');
                    }

                    $sourceHash = hash_file('sha256', $sourcePath) ?: null;
                    $targetHash = hash_file('sha256', $targetPath) ?: null;

                    if ($sourceHash === null || $targetHash === null || ! hash_equals($sourceHash, $targetHash)) {
                        throw new RuntimeException('فشل التحقق من بصمة الملف بعد النسخ.');
                    }

                    $sourceSize = (int) (@filesize($sourcePath) ?: 0);
                    $targetSize = (int) (@filesize($targetPath) ?: 0);

                    if ($sourceSize !== $targetSize) {
                        throw new RuntimeException('حجم الملف بعد النسخ لا يطابق الملف الأصلي.');
                    }

                    $classification = $stored['classification'];
                    $extension = strtolower(
                        pathinfo($originalName, PATHINFO_EXTENSION)
                        ?: ($row['FileType'] ?? 'bin')
                    );

                    $attachment = DocumentAttachment::create([
                        'document_id' => $document->id,
                        'attachment_type' => 'legacy_import',
                        'version_no' => 1,
                        'is_main' => true,
                        'original_name' => $originalName,
                        'file_name' => $stored['file_name'],
                        'file_path' => $stored['file_path'],
                        'disk' => $stored['disk'],
                        'storage_root_path' => $stored['storage_root_path'],
                        'classification_company_name' => $classification['company'],
                        'classification_operation_name' => $classification['operation'],
                        'classification_year' => $classification['year'],
                        'classification_folder' => $classification['folder'],
                        'extension' => $extension,
                        'mime_type' => $this->mimeType($targetPath, $extension),
                        'file_size' => $targetSize,
                        'ocr_status' => 'pending',
                        'uploaded_by' => Auth::id(),
                    ]);
                }

                $status = $attachment ? 'imported' : 'imported_missing_file';
                $message = $attachment
                    ? 'تم إنشاء الكتاب ونسخ المرفق والتحقق من البصمة والحجم.'
                    : 'تم إنشاء بيانات الكتاب، لكن المرفق القديم غير موجود.';

                $item = LegacyArchiveImportItem::create([
                    'run_id' => $run->id,
                    'source_record_id' => $analysis['source_record_id'],
                    'document_id' => $document->id,
                    'reference_number' => $analysis['reference_number'],
                    'status' => $status,
                    'message' => $message,
                    'source_path' => $analysis['source_path'],
                    'resolved_source_path' => $analysis['resolved_source_path'],
                    'target_path' => $stored['absolute_path'] ?? null,
                    'file_exists' => $analysis['file_exists'],
                    'file_copied' => (bool) $attachment,
                    'source_size' => $analysis['source_size'],
                    'target_size' => $targetSize,
                    'source_sha256' => $sourceHash,
                    'target_sha256' => $targetHash,
                    'payload' => $row,
                ]);

                ActivityLogger::log(
                    'legacy_archive.document_imported',
                    'تم استيراد الكتاب القديم رقم ' . $document->reference_number,
                    $document,
                    [
                        'legacy_record_id' => $analysis['source_record_id'],
                        'attachment_copied' => (bool) $attachment,
                    ]
                );

                return [
                    'item' => $item,
                    'copied_files' => $attachment ? 1 : 0,
                    'copied_bytes' => $targetSize ?: 0,
                ];
            });

            return [
                'status' => $result['item']->status,
                'copied_files' => $result['copied_files'],
                'copied_bytes' => $result['copied_bytes'],
            ];
        } catch (Throwable $exception) {
            $target = (string) ($stored['absolute_path'] ?? '');
            if ($target !== '' && is_file($target)) {
                @unlink($target);
            }

            LegacyArchiveImportItem::create([
                'run_id' => $run->id,
                'source_record_id' => $analysis['source_record_id'],
                'reference_number' => $analysis['reference_number'],
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'source_path' => $analysis['source_path'],
                'resolved_source_path' => $analysis['resolved_source_path'],
                'target_path' => $target ?: null,
                'file_exists' => $analysis['file_exists'],
                'file_copied' => false,
                'source_size' => $analysis['source_size'],
                'payload' => $row,
            ]);

            return [
                'status' => 'failed',
                'copied_files' => 0,
                'copied_bytes' => 0,
            ];
        }
    }

    private function createDocument(array $row, array $analysis): Document
    {
        $referenceNumber = $analysis['reference_number'];
        $year = (int) $analysis['reference_year'];
        $sequence = $this->referenceSequence($referenceNumber, $year);
        $subject = trim((string) ($row['ArchiveSubject'] ?? ''));
        $notes = $this->firstNonEmpty($row['ArchiveNotes'] ?? null);

        $document = Document::create([
            'reference_number' => $referenceNumber,
            'reference_year' => $year,
            'reference_sequence' => $sequence,
            'reference_date' => $analysis['reference_date'],
            'main_policy_number' => $this->firstNonEmpty($row['ArchiveWillMaster'] ?? null),
            'sub_policy_number' => $this->firstNonEmpty($row['ArchiveWillSub'] ?? null),
            'title' => $subject,
            'subject' => $subject,
            'attachment_company_name' => $analysis['company'],
            'attachment_category_name' => $analysis['operation'],
            'description' => null,
            'sender' => null,
            'receiver' => null,
            'department_id' => null,
            'document_type_id' => null,
            'created_by' => Auth::id(),
            'status' => 'active',
            'workflow_status' => 'draft',
            'confidentiality' => 'normal',
            'priority' => 'normal',
            'print_title' => Setting::getValue('print_department_title', 'الشحن والتأمين'),
            'print_top_mm' => Setting::getValue('print_top_mm', '53.30'),
            'print_left_mm' => Setting::getValue('print_left_mm', '30.80'),
            'search_text' => $this->buildSearchText($row, $analysis),
            'notes' => $notes,
            'legacy_source' => self::SOURCE_NAME,
            'legacy_record_id' => $analysis['source_record_id'],
            'legacy_user_name' => $this->firstNonEmpty($row['UserName'] ?? null),
            'legacy_archive_folder' => $this->firstNonEmpty($row['ArchiveFolder'] ?? null),
            'legacy_sader_id' => $this->firstNonEmpty($row['SaderID'] ?? null),
            'legacy_original_sader_id' => $this->firstNonEmpty($row['OriginalSaderID'] ?? null),
        ]);

        if ($this->truthy($row['IsDeleted'] ?? null)) {
            $deletedAt = $this->parseDateTime($row['DeletedDate'] ?? null) ?: now();
            $document->forceFill(['deleted_at' => $deletedAt])->save();
        }

        return $document;
    }

    private function recordAnalysisItem(LegacyArchiveImportRun $run, array $analysis): LegacyArchiveImportItem
    {
        return LegacyArchiveImportItem::create([
            'run_id' => $run->id,
            'source_record_id' => $analysis['source_record_id'],
            'reference_number' => $analysis['reference_number'],
            'status' => $analysis['status'],
            'message' => $analysis['message'],
            'source_path' => $analysis['source_path'],
            'resolved_source_path' => $analysis['resolved_source_path'],
            'file_exists' => $analysis['file_exists'],
            'file_copied' => false,
            'source_size' => $analysis['source_size'],
            'payload' => $analysis['row'],
        ]);
    }

    private function incrementDryRunCounters(array &$counters, array $analysis): void
    {
        match ($analysis['status']) {
            'ready' => $counters['ready_rows']++,
            'missing_file' => $counters['missing_file_rows']++,
            'duplicate_legacy', 'duplicate_reference' => $counters['duplicate_rows']++,
            'invalid' => $counters['failed_rows']++,
            default => $counters['skipped_rows']++,
        };
    }

    private function incrementExecuteCounters(array &$counters, array $result): void
    {
        match ($result['status']) {
            'imported' => $counters['imported_rows']++,
            'imported_missing_file' => [$counters['imported_rows']++, $counters['missing_file_rows']++],
            'duplicate_legacy', 'duplicate_reference' => $counters['duplicate_rows']++,
            'missing_file' => $counters['missing_file_rows']++,
            'failed', 'invalid' => $counters['failed_rows']++,
            default => $counters['skipped_rows']++,
        };

        $counters['copied_files'] += (int) ($result['copied_files'] ?? 0);
        $counters['copied_bytes'] += (int) ($result['copied_bytes'] ?? 0);
    }

    private function resolveSourcePath(array $row, ?string $sourceRootOverride): ?string
    {
        $archiveFolder = $this->normalizeRelativePath($row['ArchiveFolder'] ?? null);
        $fileName = $this->safeBaseName($row['FileName'] ?? null);
        $originalFileName = $this->safeBaseName($row['OriginalFileName'] ?? null);

        $candidates = [
            $this->normalizePath($row['FilePath'] ?? null),
            $this->normalizePath($row['OriginalFilePath'] ?? null),
        ];

        if ($sourceRootOverride !== null) {
            if ($archiveFolder && $fileName) {
                $candidates[] = $this->joinPath($sourceRootOverride, $archiveFolder, $fileName);
            }

            if ($archiveFolder && $originalFileName) {
                $candidates[] = $this->joinPath($sourceRootOverride, $archiveFolder, $originalFileName);
            }

            $filePath = $this->normalizePath($row['FilePath'] ?? null);
            $relativeFromShare = $this->relativeAfterShare($filePath, 'ESIS_Archive');
            if ($relativeFromShare) {
                $candidates[] = $this->joinPath($sourceRootOverride, $relativeFromShare);
            }
        }

        foreach (array_values(array_unique(array_filter($candidates))) as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function classification(array $row): array
    {
        $subject = trim((string) ($row['ArchiveSubject'] ?? ''));
        $folder = trim((string) ($row['ArchiveFolder'] ?? ''));
        $fileName = trim((string) ($row['FileName'] ?? ''));
        $originalFileName = trim((string) ($row['OriginalFileName'] ?? ''));
        $haystack = mb_strtolower(
            $subject . ' ' . $folder . ' ' . $fileName . ' ' . $originalFileName,
            'UTF-8'
        );

        $operation = match (true) {
            str_contains($haystack, 'تصدير') => 'تصدير شحنة',
            str_contains($haystack, 'إفراج') || str_contains($haystack, 'افراج') => 'إفراج جمركي',
            str_contains($haystack, 'استلام مباشر') || str_contains($haystack, 'إستلام مباشر') => 'استلام مباشر',
            str_contains($haystack, 'شحن بري') => 'شحن بري',
            str_contains($haystack, 'تعديل بوليصة') => 'تعديل بوليصة شحن',
            str_contains($haystack, 'بوالص') => 'استلام وتسليم بوالص الشحن',
            default => 'عام',
        };

        $company = match (true) {
            str_contains($haystack, 'expeditors') => 'EXPEDITORS INTERNATIONAL',
            str_contains($haystack, 'fedex') => 'FEDEX EXPRESS',
            str_contains($haystack, 'dhl express') => 'DHL EXPRESS',
            str_contains($haystack, 'dhl global') => 'DHL GLOBAL',
            str_contains($haystack, 'global dynamix') => 'GLOBAL DYNAMIX',
            str_contains($haystack, 'global freight systems') || str_contains($haystack, 'global freight system') => 'GLOBAL FREIGHT SYSTEMS CO.W.L.L',
            str_contains($haystack, 'كونا ناجل') => 'كونا ناجل',
            str_contains($haystack, 'الموانئ الجنوبية') || str_contains($haystack, 'الموانئي الجنوبية') => 'الموانئ الجنوبية شعيبة',
            str_contains($haystack, 'الموانئ الشمالية') => 'الموانئ الشمالية',
            str_contains($haystack, 'استلام مباشر') || str_contains($haystack, 'إستلام مباشر') => 'إجراءات عامة',
            default => null,
        };

        if ($company === null && str_contains($subject, '|')) {
            $left = trim((string) explode('|', $subject, 2)[0]);
            $company = $left !== '' ? $left : null;
        }

        if ($company === null) {
            $candidate = preg_replace(
                '/(?:إفراج\s*جمركي|افراج\s*جمركي|تصدير(?:\s*شحنة)?|استلام\s*مباشر|إستلام\s*مباشر)/u',
                '',
                $subject
            ) ?? $subject;
            $candidate = trim($candidate, " \t\n\r\0\x0B|-–—");

            if ($candidate !== '' && mb_strlen($candidate, 'UTF-8') <= 120) {
                $company = $candidate;
            }
        }

        return [$company ?: 'غير محدد', $operation];
    }

    private function referenceSequence(string $referenceNumber, int $year): int
    {
        $startNumber = (int) Setting::getValue('reference_start_number', 251230000);

        if (ctype_digit($referenceNumber)) {
            $number = (int) $referenceNumber;
            if ($number >= $startNumber) {
                return $number - $startNumber;
            }
        }

        $existing = (int) (Document::withTrashed()
            ->where('reference_year', $year)
            ->max('reference_sequence') ?? -1);

        return $existing + 1;
    }

    private function syncReferenceCounters(): void
    {
        if (! Schema::hasTable('reference_counters')) {
            return;
        }

        $startNumber = (int) Setting::getValue('reference_start_number', 251230000);
        $years = Document::withTrashed()
            ->whereNotNull('reference_year')
            ->distinct()
            ->pluck('reference_year');

        foreach ($years as $year) {
            $maxSequence = (int) (Document::withTrashed()
                ->where('reference_year', (int) $year)
                ->max('reference_sequence') ?? -1);

            $existing = DB::table('reference_counters')
                ->where('reference_year', (int) $year)
                ->first();

            $safeSequence = max($maxSequence, (int) ($existing->last_sequence ?? -1));
            $safeStart = (int) ($existing->start_number ?? $startNumber);

            DB::table('reference_counters')->updateOrInsert(
                ['reference_year' => (int) $year],
                [
                    'start_number' => $safeStart,
                    'last_sequence' => $safeSequence,
                    'last_reference_number' => $safeSequence >= 0 ? (string) ($safeStart + $safeSequence) : null,
                    'created_at' => $existing->created_at ?? now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function buildSearchText(array $row, array $analysis): string
    {
        $values = [
            $analysis['reference_number'],
            $row['ArchiveDate'] ?? null,
            $row['ArchiveSubject'] ?? null,
            $row['ArchiveFolder'] ?? null,
            $row['ArchiveWillMaster'] ?? null,
            $row['ArchiveWillSub'] ?? null,
            $row['ArchiveNotes'] ?? null,
            $row['UserName'] ?? null,
            $row['FileName'] ?? null,
            $row['OriginalFileName'] ?? null,
            $analysis['company'],
            $analysis['operation'],
        ];

        return trim(implode(' ', array_filter(array_map(
            fn ($value) => trim((string) $value),
            $values
        ))));
    }

    private function parseDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDateTime(?string $value): ?Carbon
    {
        return $this->parseDate($value);
    }

    private function positiveInteger(mixed $value): ?int
    {
        $value = trim((string) $value);

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function truthy(mixed $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value), 'UTF-8'), ['1', 'true', 'yes', 'نعم'], true);
    }

    private function firstNonEmpty(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && strcasecmp($value, 'NULL') !== 0) {
                return $value;
            }
        }

        return null;
    }

    private function normalizePath(?string $path): ?string
    {
        $path = trim((string) $path);
        $path = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $path) ?? $path;
        $path = trim($path, " \t\n\r\0\x0B\"'");

        if ($path === '') {
            return null;
        }

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private function normalizeRelativePath(?string $path): ?string
    {
        $path = $this->normalizePath($path);

        return $path ? trim($path, "\\/") : null;
    }

    private function joinPath(string ...$parts): string
    {
        $clean = [];
        foreach ($parts as $index => $part) {
            $part = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $part);
            $clean[] = $index === 0
                ? rtrim($part, "\\/")
                : trim($part, "\\/");
        }

        return implode(DIRECTORY_SEPARATOR, array_filter($clean, fn ($part) => $part !== ''));
    }

    private function relativeAfterShare(?string $path, string $shareName): ?string
    {
        if ($path === null) {
            return null;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $needle = DIRECTORY_SEPARATOR . $shareName . DIRECTORY_SEPARATOR;
        $position = stripos($normalized, $needle);

        if ($position === false) {
            return null;
        }

        return substr($normalized, $position + strlen($needle));
    }

    private function safeBaseName(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $name = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $name);

        return basename($name);
    }

    private function mimeType(string $path, string $extension): string
    {
        $detected = function_exists('mime_content_type') ? @mime_content_type($path) : null;
        if (is_string($detected) && $detected !== '') {
            return $detected;
        }

        return match (strtolower($extension)) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'tif', 'tiff' => 'image/tiff',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }
}

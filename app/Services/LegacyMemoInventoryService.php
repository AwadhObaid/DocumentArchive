<?php

namespace App\Services;

use App\Models\LegacyMemoImportItem;
use App\Models\LegacyMemoImportRun;
use App\Models\MemoAttachment;
use Carbon\Carbon;
use FilesystemIterator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

class LegacyMemoInventoryService
{
    public const VERSION = 'V84';

    private const SUPPORTED_EXTENSIONS = [
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

    /** @var array<string, int|null> */
    private array $existingHashCache = [];

    /** @var array<int, bool> */
    private array $hashedAttachmentIds = [];

    public function scan(
        string $sourceRoot,
        bool $recursive = true,
        int $limit = 10000
    ): LegacyMemoImportRun {
        @set_time_limit(0);

        $sourceRoot = $this->normalizeSourceRoot($sourceRoot);
        $limit = max(1, min($limit, 50000));

        if (! is_dir($sourceRoot)) {
            throw new RuntimeException(
                'مجلد المذكرات غير موجود أو لا يستطيع PHP الوصول إليه: '
                . $sourceRoot
            );
        }

        if (! is_readable($sourceRoot)) {
            throw new RuntimeException(
                'مجلد المذكرات موجود، لكن حساب Apache/PHP لا يملك صلاحية قراءته.'
            );
        }

        $run = LegacyMemoImportRun::create([
            'source_root' => $sourceRoot,
            'recursive' => $recursive,
            'status' => 'processing',
            'created_by' => Auth::id(),
            'started_at' => now(),
            'notes' => [
                'version' => self::VERSION,
                'scan_only' => true,
                'memos_created' => false,
                'files_copied' => false,
                'source_files_modified' => false,
                'source_files_deleted' => false,
                'proposed_numbers_are_not_reserved' => true,
                'memo_number_start' => MemoNumberGenerator::DEFAULT_START_NUMBER,
                'supported_extensions' => self::SUPPORTED_EXTENSIONS,
            ],
        ]);

        $counters = [
            'total_files' => 0,
            'supported_files' => 0,
            'ready_files' => 0,
            'needs_review_files' => 0,
            'duplicate_system_files' => 0,
            'duplicate_scan_files' => 0,
            'unreadable_files' => 0,
            'unsupported_files' => 0,
            'hashed_files' => 0,
            'bytes_total' => 0,
        ];

        try {
            [$files, $truncated] = $this->collectFiles(
                $sourceRoot,
                $recursive,
                $limit
            );

            $rows = [];

            foreach ($files as $file) {
                $rows[] = $this->basicMetadata($sourceRoot, $file);
            }

            usort($rows, function (array $left, array $right): int {
                $dateComparison = strcmp(
                    (string) ($left['proposed_memo_date'] ?? ''),
                    (string) ($right['proposed_memo_date'] ?? '')
                );

                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                return strnatcasecmp(
                    (string) $left['relative_path'],
                    (string) $right['relative_path']
                );
            });

            $seenHashes = [];
            $numberCandidateIndexes = [];

            foreach ($rows as $index => &$row) {
                $counters['total_files']++;
                $counters['bytes_total'] += (int) ($row['file_size'] ?? 0);

                $extension = $row['extension'];

                if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                    $row['status'] = 'unsupported';
                    $row['message'] = 'امتداد الملف غير مدعوم في وحدة المذكرات.';
                    $counters['unsupported_files']++;
                    continue;
                }

                $counters['supported_files']++;

                if (! $row['readable']) {
                    $row['status'] = 'unreadable';
                    $row['message'] = 'تعذر قراءة الملف بواسطة حساب PHP.';
                    $counters['unreadable_files']++;
                    continue;
                }

                $hash = @hash_file('sha256', $row['source_path']);

                if (! is_string($hash) || strlen($hash) !== 64) {
                    $row['status'] = 'unreadable';
                    $row['message'] = 'تعذر حساب بصمة SHA-256 للملف.';
                    $counters['unreadable_files']++;
                    continue;
                }

                $row['sha256'] = strtolower($hash);
                $counters['hashed_files']++;

                if (isset($seenHashes[$row['sha256']])) {
                    $row['status'] = 'duplicate_scan';
                    $row['message'] = 'نفس المحتوى موجود داخل المجلد باسم أو مسار آخر.';
                    $row['payload']['duplicate_of_path']
                        = $seenHashes[$row['sha256']];
                    $counters['duplicate_scan_files']++;
                    continue;
                }

                $seenHashes[$row['sha256']] = $row['relative_path'];

                $existingAttachment = $this->existingAttachmentByHash(
                    $row['sha256'],
                    (int) $row['file_size']
                );

                if ($existingAttachment) {
                    $row['status'] = 'duplicate_system';
                    $row['memo_id'] = $existingAttachment->memo_id;
                    $row['message'] = 'المحتوى موجود مسبقًا ضمن مرفقات مذكرة في النظام.';
                    $row['payload']['existing_attachment_id']
                        = $existingAttachment->id;
                    $row['payload']['existing_memo_number']
                        = $existingAttachment->memo?->memo_number;
                    $counters['duplicate_system_files']++;
                    continue;
                }

                if ($row['generic_name']) {
                    $row['status'] = 'needs_review';
                    $row['message'] = 'اسم الملف عام؛ يلزم مراجعة موضوع المذكرة وتاريخها.';
                    $counters['needs_review_files']++;
                } else {
                    $row['status'] = 'ready';
                    $row['message'] = 'الملف جاهز لمراجعة البيانات قبل الاستيراد.';
                    $counters['ready_files']++;
                }

                $numberCandidateIndexes[] = $index;
            }
            unset($row);

            $numberPreviews = MemoNumberGenerator::previewBatch(
                count($numberCandidateIndexes)
            );

            foreach ($numberCandidateIndexes as $position => $rowIndex) {
                $preview = $numberPreviews[$position] ?? null;

                if (! $preview) {
                    continue;
                }

                $rows[$rowIndex]['proposed_memo_number']
                    = $preview['memo_number'];
                $rows[$rowIndex]['proposed_memo_sequence']
                    = $preview['memo_sequence'];
            }

            foreach ($rows as $row) {
                LegacyMemoImportItem::create([
                    'run_id' => $run->id,
                    'memo_id' => $row['memo_id'] ?? null,
                    'status' => $row['status'],
                    'message' => $row['message'],
                    'source_path' => $row['source_path'],
                    'relative_path' => $row['relative_path'],
                    'original_name' => $row['original_name'],
                    'extension' => $row['extension'],
                    'mime_type' => $row['mime_type'],
                    'file_size' => $row['file_size'],
                    'modified_at' => $row['modified_at'],
                    'sha256' => $row['sha256'] ?? null,
                    'proposed_memo_number'
                        => $row['proposed_memo_number'] ?? null,
                    'proposed_memo_sequence'
                        => $row['proposed_memo_sequence'] ?? null,
                    'proposed_memo_date' => $row['proposed_memo_date'],
                    'proposed_subject' => $row['proposed_subject'],
                    'proposed_sender' => null,
                    'proposed_receiver' => null,
                    'proposed_department_id' => null,
                    'payload' => $row['payload'],
                ]);
            }

            $status = $counters['unreadable_files'] > 0
                ? 'completed_with_errors'
                : 'completed';

            $notes = array_merge($run->notes ?: [], [
                'truncated_by_limit' => $truncated,
                'scan_limit' => $limit,
                'number_preview_count' => count($numberPreviews),
                'sort_order' => 'proposed_date_then_relative_path',
            ]);

            $run->update(array_merge($counters, [
                'status' => $status,
                'finished_at' => now(),
                'notes' => $notes,
            ]));

            ActivityLogger::log(
                'memo_legacy_import.scan_completed',
                'اكتمل فحص مجلد المذكرات القديمة دون إنشاء مذكرات أو نسخ ملفات.',
                $run,
                array_merge($counters, [
                    'source_root' => $sourceRoot,
                    'recursive' => $recursive,
                    'truncated' => $truncated,
                ])
            );

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->update(array_merge($counters, [
                'status' => 'failed',
                'finished_at' => now(),
                'notes' => array_merge($run->notes ?: [], [
                    'fatal_error' => $exception->getMessage(),
                ]),
            ]));

            ActivityLogger::log(
                'memo_legacy_import.scan_failed',
                'فشل فحص مجلد المذكرات القديمة.',
                $run,
                [
                    'source_root' => $sourceRoot,
                    'error' => $exception->getMessage(),
                ]
            );

            throw $exception;
        }
    }

    private function collectFiles(
        string $sourceRoot,
        bool $recursive,
        int $limit
    ): array {
        $files = [];
        $truncated = false;

        if ($recursive) {
            $directory = new RecursiveDirectoryIterator(
                $sourceRoot,
                FilesystemIterator::SKIP_DOTS
            );

            $iterator = new RecursiveIteratorIterator(
                $directory,
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
        } else {
            $iterator = new \DirectoryIterator($sourceRoot);
        }

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $name = $file->getFilename();

            if ($name === ''
                || str_starts_with($name, '~$')
                || str_starts_with($name, '.')) {
                continue;
            }

            if (count($files) >= $limit) {
                $truncated = true;
                break;
            }

            $files[] = clone $file;
        }

        return [$files, $truncated];
    }

    private function basicMetadata(
        string $sourceRoot,
        SplFileInfo $file
    ): array {
        $sourcePath = $file->getPathname();
        $relativePath = $this->relativePath($sourceRoot, $sourcePath);
        $originalName = $file->getFilename();
        $extension = strtolower(
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        $modifiedTimestamp = null;

        try {
            $modifiedTimestamp = $file->getMTime();
        } catch (Throwable $exception) {
            $modifiedTimestamp = null;
        }

        [$memoDate, $dateSource] = $this->inferDate(
            $originalName,
            $modifiedTimestamp
        );

        [$subject, $genericName] = $this->inferSubject(
            $originalName,
            dirname($relativePath)
        );

        $fileSize = 0;

        try {
            $fileSize = (int) $file->getSize();
        } catch (Throwable $exception) {
            $fileSize = 0;
        }

        return [
            'memo_id' => null,
            'status' => 'ready',
            'message' => '',
            'source_path' => $sourcePath,
            'relative_path' => $relativePath,
            'original_name' => $originalName,
            'extension' => $extension,
            'mime_type' => $this->mimeType($sourcePath, $extension),
            'file_size' => $fileSize,
            'modified_at' => $modifiedTimestamp
                ? Carbon::createFromTimestamp($modifiedTimestamp)
                : null,
            'sha256' => null,
            'proposed_memo_number' => null,
            'proposed_memo_sequence' => null,
            'proposed_memo_date' => $memoDate?->toDateString(),
            'proposed_subject' => $subject,
            'generic_name' => $genericName,
            'readable' => is_readable($sourcePath),
            'payload' => [
                'date_source' => $dateSource,
                'parent_folder' => basename(dirname($sourcePath)),
                'original_extension' => $extension,
            ],
        ];
    }

    private function inferDate(
        string $fileName,
        ?int $modifiedTimestamp
    ): array {
        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $patterns = [
            [
                '/(?<!\d)(20\d{2})[-_. ](0?[1-9]|1[0-2])[-_. ]([0-2]?\d|3[01])(?!\d)/u',
                'ymd',
            ],
            [
                '/(?<!\d)([0-2]?\d|3[01])[-_. ](0?[1-9]|1[0-2])[-_. ](20\d{2})(?!\d)/u',
                'dmy',
            ],
            [
                '/(?<!\d)(20\d{2})(0[1-9]|1[0-2])([0-2]\d|3[01])(?!\d)/u',
                'ymd',
            ],
        ];

        foreach ($patterns as [$pattern, $order]) {
            if (! preg_match($pattern, $base, $matches)) {
                continue;
            }

            try {
                if ($order === 'dmy') {
                    $date = Carbon::createSafe(
                        (int) $matches[3],
                        (int) $matches[2],
                        (int) $matches[1]
                    );
                } else {
                    $date = Carbon::createSafe(
                        (int) $matches[1],
                        (int) $matches[2],
                        (int) $matches[3]
                    );
                }

                if ($date) {
                    return [$date->startOfDay(), 'filename'];
                }
            } catch (Throwable $exception) {
                // Continue to the file-modification fallback.
            }
        }

        if ($modifiedTimestamp) {
            return [
                Carbon::createFromTimestamp($modifiedTimestamp)->startOfDay(),
                'file_modified_time',
            ];
        }

        return [now()->startOfDay(), 'scan_date_fallback'];
    }

    private function inferSubject(
        string $fileName,
        string $relativeParent
    ): array {
        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $subject = preg_replace('/[_\-–—]+/u', ' ', $base);
        $subject = trim(preg_replace('/\s+/u', ' ', (string) $subject));

        $generic = $subject === ''
            || mb_strlen($subject) < 4
            || preg_match(
                '/^(scan|img|image|document|doc|memo|file|مستند|مذكرة|ملف)\s*\d*$/iu',
                $subject
            );

        if ($generic) {
            $parent = basename(str_replace('\\', '/', $relativeParent));
            $parent = trim(
                preg_replace('/[_\-–—]+/u', ' ', $parent)
            );

            if ($parent !== ''
                && $parent !== '.'
                && ! preg_match('/^(memos?|مذكرات?)$/iu', $parent)) {
                return ['مذكرة قديمة - ' . $parent, true];
            }

            return ['مذكرة قديمة - ' . ($subject ?: $base), true];
        }

        return [$subject, false];
    }

    private function existingAttachmentByHash(
        string $sha256,
        int $fileSize
    ): ?MemoAttachment {
        if (array_key_exists($sha256, $this->existingHashCache)) {
            $id = $this->existingHashCache[$sha256];

            return $id
                ? MemoAttachment::with('memo')->find($id)
                : null;
        }

        $direct = MemoAttachment::query()
            ->with('memo')
            ->where('sha256', $sha256)
            ->first();

        if ($direct) {
            $this->existingHashCache[$sha256] = $direct->id;

            return $direct;
        }

        $candidates = MemoAttachment::query()
            ->with('memo')
            ->where('file_size', $fileSize)
            ->where(function ($query) {
                $query->whereNull('sha256')
                    ->orWhere('sha256', '');
            })
            ->get();

        foreach ($candidates as $candidate) {
            if (isset($this->hashedAttachmentIds[$candidate->id])) {
                continue;
            }

            $this->hashedAttachmentIds[$candidate->id] = true;
            $candidateHash = $this->hashStoredAttachment($candidate);

            if (! $candidateHash) {
                continue;
            }

            $candidate->forceFill(['sha256' => $candidateHash])
                ->saveQuietly();

            $this->existingHashCache[$candidateHash] = $candidate->id;

            if (hash_equals($sha256, $candidateHash)) {
                return $candidate;
            }
        }

        $this->existingHashCache[$sha256] = null;

        return null;
    }

    private function hashStoredAttachment(
        MemoAttachment $attachment
    ): ?string {
        try {
            $disk = Storage::disk($attachment->disk ?: 'local');

            if (! $disk->exists($attachment->file_path)) {
                return null;
            }

            if (method_exists($disk, 'path')) {
                $absolutePath = $disk->path($attachment->file_path);

                if (is_file($absolutePath) && is_readable($absolutePath)) {
                    $hash = hash_file('sha256', $absolutePath);

                    return is_string($hash) ? strtolower($hash) : null;
                }
            }

            $stream = $disk->readStream($attachment->file_path);

            if (! is_resource($stream)) {
                return null;
            }

            $context = hash_init('sha256');
            hash_update_stream($context, $stream);
            fclose($stream);

            return strtolower(hash_final($context));
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function mimeType(
        string $path,
        string $extension
    ): string {
        $known = match ($extension) {
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
            default => null,
        };

        if ($known) {
            return $known;
        }

        try {
            $detected = @mime_content_type($path);

            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        } catch (Throwable $exception) {
            // Use binary fallback.
        }

        return 'application/octet-stream';
    }

    private function normalizeSourceRoot(string $sourceRoot): string
    {
        $sourceRoot = trim($sourceRoot);
        $sourceRoot = trim($sourceRoot, "\"'");
        $sourceRoot = rtrim($sourceRoot, "\\/");

        if ($sourceRoot === '') {
            throw new RuntimeException('أدخل مسار مجلد المذكرات على Server-1.');
        }

        return $sourceRoot;
    }

    private function relativePath(
        string $sourceRoot,
        string $sourcePath
    ): string {
        $prefixLength = strlen($sourceRoot);

        if (strncasecmp($sourcePath, $sourceRoot, $prefixLength) === 0) {
            return ltrim(
                substr($sourcePath, $prefixLength),
                "\\/"
            );
        }

        return basename($sourcePath);
    }
}

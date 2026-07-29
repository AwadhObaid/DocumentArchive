<?php

namespace App\Services;

use App\Models\AttachmentTextIndex;
use App\Models\CircularAttachment;
use App\Models\DocumentAttachment;
use App\Models\MemoAttachment;
use App\Models\MiscBookAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PdfTextIndexingService
{
    private const SOURCES = [
        'documents',
        'memos',
        'circulars',
        'misc_books',
    ];

    public function __construct(private PdfTextExtractionService $extractor)
    {
    }

    public function indexAll(
        string $source = 'all',
        int $limit = 50,
        bool $force = false,
        bool $enableOcr = false,
        ?array $allowedSources = null
    ): array {
        $source = in_array($source, array_merge(['all'], self::SOURCES), true) ? $source : 'all';
        $limit = max(1, min(500, $limit));

        $sourcesToProcess = $source === 'all' ? self::SOURCES : [$source];

        if ($allowedSources !== null) {
            $allowedSources = array_values(array_intersect(self::SOURCES, $allowedSources));
            $sourcesToProcess = array_values(array_intersect($sourcesToProcess, $allowedSources));
        }

        $summary = [
            'processed' => 0,
            'indexed' => 0,
            'needs_ocr' => 0,
            'failed' => 0,
            'missing' => 0,
            'skipped' => 0,
        ];

        foreach ($sourcesToProcess as $sourceKey) {
            $remaining = $limit - $summary['processed'];

            if ($remaining <= 0) {
                break;
            }

            $attachments = $this->attachmentsQuery($sourceKey, $force)
                ->limit($remaining)
                ->get();

            foreach ($attachments as $attachment) {
                $index = match ($sourceKey) {
                    'documents' => $this->indexDocumentAttachment($attachment, $force, $enableOcr),
                    'memos' => $this->indexMemoAttachment($attachment, $force, $enableOcr),
                    'circulars' => $this->indexCircularAttachment($attachment, $force, $enableOcr),
                    'misc_books' => $this->indexMiscBookAttachment($attachment, $force, $enableOcr),
                };

                $this->applySummary($summary, $index);

                if ($summary['processed'] >= $limit) {
                    break 2;
                }
            }
        }

        return $summary;
    }

    public function indexDocumentAttachment(
        DocumentAttachment $attachment,
        bool $force = false,
        bool $enableOcr = false
    ): AttachmentTextIndex {
        return $this->indexAttachment(
            'document',
            $attachment,
            $attachment->document_id,
            $force,
            $enableOcr
        );
    }

    public function indexMemoAttachment(
        MemoAttachment $attachment,
        bool $force = false,
        bool $enableOcr = false
    ): AttachmentTextIndex {
        return $this->indexAttachment(
            'memo',
            $attachment,
            $attachment->memo_id,
            $force,
            $enableOcr
        );
    }

    public function indexCircularAttachment(
        CircularAttachment $attachment,
        bool $force = false,
        bool $enableOcr = false
    ): AttachmentTextIndex {
        return $this->indexAttachment(
            'circular',
            $attachment,
            $attachment->circular_id,
            $force,
            $enableOcr
        );
    }

    public function indexMiscBookAttachment(
        MiscBookAttachment $attachment,
        bool $force = false,
        bool $enableOcr = false
    ): AttachmentTextIndex {
        return $this->indexAttachment(
            'misc_book',
            $attachment,
            $attachment->misc_book_id,
            $force,
            $enableOcr
        );
    }

    private function indexAttachment(
        string $sourceType,
        Model $attachment,
        int $sourceId,
        bool $force,
        bool $enableOcr
    ): AttachmentTextIndex {
        $index = AttachmentTextIndex::query()->firstOrNew([
            'source_type' => $sourceType,
            'attachment_id' => $attachment->id,
        ]);

        $metadata = $this->metadata($sourceType, $attachment, $sourceId);
        $index->fill($metadata);

        if (! $this->isPdf($attachment)) {
            $index->fill([
                'index_status' => 'skipped',
                'extractor' => 'none',
                'needs_ocr' => false,
                'text_length' => 0,
                'indexed_text' => null,
                'error_message' => 'الملف ليس PDF.',
                'last_indexed_at' => now(),
            ])->save();

            return $index;
        }

        $path = $attachment->file_path;
        $absolutePath = null;

        if ($sourceType === 'document' && $attachment instanceof DocumentAttachment) {
            $attachmentStorage = app(BookAttachmentSmartPathService::class);

            if (! $path || ! $attachmentStorage->attachmentExists($attachment)) {
                return $this->markMissing($index, 'الملف غير موجود على التخزين.');
            }

            $absolutePath = $attachmentStorage->absolutePathForAttachment($attachment);
        } else {
            $diskName = $attachment->disk ?: 'local';
            $disk = Storage::disk($diskName);

            if (! $path || ! $disk->exists($path)) {
                return $this->markMissing($index, 'الملف غير موجود على التخزين.');
            }

            $absolutePath = method_exists($disk, 'path') ? $disk->path($path) : null;
        }

        if (! $absolutePath || ! is_file($absolutePath)) {
            return $this->markMissing($index, 'تعذر تحديد المسار المحلي للملف.');
        }

        $previousHash = $index->file_hash;
        $fileHash = @hash_file('sha256', $absolutePath) ?: null;

        if (
            ! $force
            && $index->exists
            && $index->index_status === 'indexed'
            && $previousHash === $fileHash
            && (int) $index->text_length > 0
        ) {
            $index->fill($metadata);
            $index->file_hash = $fileHash;
            $index->save();

            return $index;
        }

        $index->file_hash = $fileHash;

        $result = $this->extractor->extract($absolutePath, $enableOcr);
        $text = (string) ($result['text'] ?? '');

        $index->fill([
            'index_status' => $result['status'] ?? 'failed',
            'extractor' => $result['extractor'] ?? 'none',
            'needs_ocr' => (bool) ($result['needs_ocr'] ?? false),
            'pages_count' => $result['pages_count'] ?? null,
            'text_length' => mb_strlen($text, 'UTF-8'),
            'indexed_text' => $text !== '' ? $text : null,
            'error_message' => $result['error'] ?? null,
            'last_indexed_at' => now(),
        ])->save();

        return $index;
    }

    private function attachmentsQuery(string $source, bool $force)
    {
        return match ($source) {
            'documents' => $this->documentAttachmentsQuery($force),
            'memos' => $this->memoAttachmentsQuery($force),
            'circulars' => $this->circularAttachmentsQuery($force),
            'misc_books' => $this->miscBookAttachmentsQuery($force),
        };
    }

    private function documentAttachmentsQuery(bool $force)
    {
        return $this->pdfAttachmentsQuery(
            DocumentAttachment::query(),
            $force
        );
    }

    private function memoAttachmentsQuery(bool $force)
    {
        return $this->pdfAttachmentsQuery(
            MemoAttachment::query(),
            $force
        );
    }

    private function circularAttachmentsQuery(bool $force)
    {
        return $this->pdfAttachmentsQuery(
            CircularAttachment::query(),
            $force
        );
    }

    private function miscBookAttachmentsQuery(bool $force)
    {
        return $this->pdfAttachmentsQuery(
            MiscBookAttachment::query(),
            $force
        );
    }

    private function pdfAttachmentsQuery($query, bool $force)
    {
        $query
            ->where(function ($builder) {
                $builder->whereRaw('LOWER(COALESCE(extension, "")) = ?', ['pdf'])
                    ->orWhere('mime_type', 'like', '%pdf%');
            })
            ->orderBy('id');

        if (! $force) {
            $query->whereDoesntHave('textIndex', function ($builder) {
                $builder->where('index_status', 'indexed')
                    ->where('text_length', '>', 0);
            });
        }

        return $query;
    }

    private function metadata(string $sourceType, Model $attachment, int $sourceId): array
    {
        return [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'file_name' => $attachment->file_name,
            'file_path' => $attachment->file_path,
            'disk' => $attachment->disk ?: 'local',
            'extension' => strtolower((string) $attachment->extension),
            'mime_type' => $attachment->mime_type,
            'file_size' => (int) $attachment->file_size,
        ];
    }

    private function isPdf(Model $attachment): bool
    {
        $extension = strtolower((string) $attachment->extension);
        $mime = strtolower((string) $attachment->mime_type);

        return $extension === 'pdf' || str_contains($mime, 'pdf');
    }

    private function markMissing(
        AttachmentTextIndex $index,
        string $message
    ): AttachmentTextIndex {
        $index->fill([
            'index_status' => 'missing',
            'extractor' => 'none',
            'needs_ocr' => false,
            'text_length' => 0,
            'indexed_text' => null,
            'error_message' => $message,
            'last_indexed_at' => now(),
        ])->save();

        return $index;
    }

    private function applySummary(array &$summary, AttachmentTextIndex $index): void
    {
        $summary['processed']++;
        $status = $index->index_status ?: 'failed';

        if (! array_key_exists($status, $summary)) {
            $summary[$status] = 0;
        }

        $summary[$status]++;
    }
}

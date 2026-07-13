<?php

namespace App\Services;

use App\Models\AttachmentTextIndex;
use App\Models\DocumentAttachment;
use App\Models\MemoAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PdfTextIndexingService
{
    public function __construct(private PdfTextExtractionService $extractor)
    {
    }

    public function indexAll(string $source = 'all', int $limit = 50, bool $force = false, bool $enableOcr = false): array
    {
        $source = in_array($source, ['all', 'documents', 'memos'], true) ? $source : 'all';
        $limit = max(1, min(500, $limit));

        $summary = [
            'processed' => 0,
            'indexed' => 0,
            'needs_ocr' => 0,
            'failed' => 0,
            'missing' => 0,
            'skipped' => 0,
        ];

        if ($source !== 'memos') {
            foreach ($this->documentAttachmentsQuery($force)->limit($limit)->get() as $attachment) {
                $this->applySummary($summary, $this->indexDocumentAttachment($attachment, $force, $enableOcr));
                if ($summary['processed'] >= $limit) {
                    return $summary;
                }
            }
        }

        if ($source !== 'documents') {
            $remaining = $limit - $summary['processed'];
            if ($remaining > 0) {
                foreach ($this->memoAttachmentsQuery($force)->limit($remaining)->get() as $attachment) {
                    $this->applySummary($summary, $this->indexMemoAttachment($attachment, $force, $enableOcr));
                }
            }
        }

        return $summary;
    }

    public function indexDocumentAttachment(DocumentAttachment $attachment, bool $force = false, bool $enableOcr = false): AttachmentTextIndex
    {
        return $this->indexAttachment('document', $attachment, $attachment->document_id, $force, $enableOcr);
    }

    public function indexMemoAttachment(MemoAttachment $attachment, bool $force = false, bool $enableOcr = false): AttachmentTextIndex
    {
        return $this->indexAttachment('memo', $attachment, $attachment->memo_id, $force, $enableOcr);
    }

    private function indexAttachment(string $sourceType, Model $attachment, int $sourceId, bool $force, bool $enableOcr): AttachmentTextIndex
    {
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
                $index->fill([
                    'index_status' => 'missing',
                    'extractor' => 'none',
                    'needs_ocr' => false,
                    'text_length' => 0,
                    'indexed_text' => null,
                    'error_message' => 'الملف غير موجود على التخزين.',
                    'last_indexed_at' => now(),
                ])->save();

                return $index;
            }

            $absolutePath = $attachmentStorage->absolutePathForAttachment($attachment);
        } else {
            $diskName = $attachment->disk ?: 'local';
            $disk = Storage::disk($diskName);

            if (! $path || ! $disk->exists($path)) {
                $index->fill([
                    'index_status' => 'missing',
                    'extractor' => 'none',
                    'needs_ocr' => false,
                    'text_length' => 0,
                    'indexed_text' => null,
                    'error_message' => 'الملف غير موجود على التخزين.',
                    'last_indexed_at' => now(),
                ])->save();

                return $index;
            }

            $absolutePath = method_exists($disk, 'path') ? $disk->path($path) : null;
        }

        if (! $absolutePath || ! is_file($absolutePath)) {
            $index->fill([
                'index_status' => 'missing',
                'extractor' => 'none',
                'needs_ocr' => false,
                'text_length' => 0,
                'indexed_text' => null,
                'error_message' => 'تعذر تحديد المسار المحلي للملف.',
                'last_indexed_at' => now(),
            ])->save();

            return $index;
        }

        $fileHash = @hash_file('sha256', $absolutePath) ?: null;
        $index->file_hash = $fileHash;

        if (! $force && $index->exists && $index->index_status === 'indexed' && $index->file_hash === $fileHash && (int) $index->text_length > 0) {
            $index->fill($metadata);
            $index->save();
            return $index;
        }

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

    private function documentAttachmentsQuery(bool $force)
    {
        $query = DocumentAttachment::query()
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

    private function memoAttachmentsQuery(bool $force)
    {
        $query = MemoAttachment::query()
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttachmentTextIndex extends Model
{
    /**
     * Laravel pluralizes AttachmentTextIndex as attachment_text_indices by default.
     * The migration intentionally creates attachment_text_indexes, so keep the
     * model table name explicit to avoid QueryException on /pdf-search.
     */
    protected $table = 'attachment_text_indexes';

    protected $fillable = [
        'source_type',
        'source_id',
        'attachment_id',
        'original_name',
        'file_name',
        'file_path',
        'disk',
        'extension',
        'mime_type',
        'file_size',
        'file_hash',
        'index_status',
        'extractor',
        'needs_ocr',
        'pages_count',
        'text_length',
        'indexed_text',
        'error_message',
        'last_indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'needs_ocr' => 'boolean',
            'pages_count' => 'integer',
            'text_length' => 'integer',
            'last_indexed_at' => 'datetime',
        ];
    }

    public function document()
    {
        return $this->belongsTo(Document::class, 'source_id');
    }

    public function memo()
    {
        return $this->belongsTo(Memo::class, 'source_id');
    }

    public function circular()
    {
        return $this->belongsTo(Circular::class, 'source_id');
    }

    public function miscBook()
    {
        return $this->belongsTo(MiscBook::class, 'source_id');
    }

    public function documentAttachment()
    {
        return $this->belongsTo(DocumentAttachment::class, 'attachment_id');
    }

    public function memoAttachment()
    {
        return $this->belongsTo(MemoAttachment::class, 'attachment_id');
    }

    public function circularAttachment()
    {
        return $this->belongsTo(CircularAttachment::class, 'attachment_id');
    }

    public function miscBookAttachment()
    {
        return $this->belongsTo(MiscBookAttachment::class, 'attachment_id');
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->source_type) {
            'memo' => 'مذكرة',
            'circular' => 'تعميم',
            'misc_book' => 'كتاب متفرق',
            default => 'كتاب',
        };
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->index_status) {
            'indexed' => 'مفهرس بنجاح',
            'needs_ocr' => 'يحتاج تعرفًا ضوئيًا',
            'failed' => 'فشلت الفهرسة',
            'missing' => 'الملف غير موجود',
            'skipped' => 'تم تجاوزه',
            default => 'بانتظار الفهرسة',
        };
    }

    public function getExtractorNameAttribute(): string
    {
        return match ((string) $this->extractor) {
            'native' => 'استخراج نص مباشر من PDF',
            'ocr' => 'تعرف ضوئي على صورة المستند',
            'mixed' => 'استخراج مباشر + تعرف ضوئي',
            'none', '' => 'لم يتم تشغيل معالج بعد',
            default => (string) $this->extractor,
        };
    }

    public function friendlyErrorMessage(): string
    {
        $message = trim((string) $this->error_message);

        if ($message === '') {
            return '';
        }

        $lower = mb_strtolower($message, 'UTF-8');

        if (str_contains($lower, 'not recognized as an internal or external command')
            || str_contains($lower, 'the system cannot find the file specified')
            || str_contains($lower, 'no such file or directory')) {
            if (str_contains($lower, 'pdftotext')) {
                return 'أداة استخراج النص من PDF غير مثبتة أو أن مسارها غير صحيح. ثبّت Poppler أو حدّد مسار pdftotext.exe من الإعدادات.';
            }

            if (str_contains($lower, 'pdftoppm')) {
                return 'أداة تحويل PDF إلى صور غير مثبتة أو أن مسارها غير صحيح. ثبّت Poppler أو حدّد مسار pdftoppm.exe من الإعدادات.';
            }

            if (str_contains($lower, 'tesseract')) {
                return 'محرك التعرف الضوئي غير مثبت أو أن مساره غير صحيح. ثبّت Tesseract أو حدّد مسار tesseract.exe من الإعدادات.';
            }

            return 'إحدى أدوات الفهرسة الخارجية غير مثبتة أو أن مسارها غير صحيح. راجع إعدادات PDF/OCR.';
        }

        if (str_contains($lower, 'unable to get page count') || str_contains($lower, 'pdfinfo')) {
            return 'تعذر قراءة صفحات ملف PDF. قد يكون الملف تالفًا أو محميًا أو يحتاج أدوات Poppler.';
        }

        if (str_contains($lower, 'permission denied') || str_contains($lower, 'access is denied')) {
            return 'لا توجد صلاحية كافية لقراءة الملف أو تشغيل أداة الفهرسة.';
        }

        return $message;
    }

    public function getStatusClassAttribute(): string
    {
        return match ($this->index_status) {
            'indexed' => 'success',
            'needs_ocr' => 'warning',
            'failed', 'missing' => 'danger',
            'skipped' => 'muted',
            default => 'info',
        };
    }

    public function recordTitle(): string
    {
        return match ($this->source_type) {
            'memo' => $this->memo?->subject
                ?: ('مذكرة ' . ($this->memo?->memo_number ?: $this->source_id)),
            'circular' => $this->circular?->subject
                ?: ('تعميم ' . ($this->circular?->circular_number ?: $this->source_id)),
            'misc_book' => $this->miscBook?->subject
                ?: ('كتاب متفرق ' . ($this->miscBook?->misc_number ?: $this->source_id)),
            default => $this->document?->title
                ?: $this->document?->subject
                ?: ('كتاب ' . ($this->document?->reference_number ?: $this->source_id)),
        };
    }

    public function recordNumber(): string
    {
        return match ($this->source_type) {
            'memo' => (string) ($this->memo?->memo_number ?: $this->source_id),
            'circular' => (string) ($this->circular?->circular_number ?: $this->source_id),
            'misc_book' => (string) ($this->miscBook?->misc_number ?: $this->source_id),
            default => (string) ($this->document?->reference_number ?: $this->source_id),
        };
    }

    public function recordRoute(): ?string
    {
        return match ($this->source_type) {
            'memo' => $this->memo ? route('memos.show', $this->memo) : null,
            'circular' => $this->circular ? route('circulars.show', $this->circular) : null,
            'misc_book' => $this->miscBook ? route('misc-books.show', $this->miscBook) : null,
            'document' => $this->document ? route('documents.show', $this->document) : null,
            default => null,
        };
    }

    public function snippet(?string $query = null, int $limit = 260): string
    {
        $text = trim((string) $this->indexed_text);

        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;
        $query = trim((string) $query);

        if ($query !== '') {
            $pos = mb_stripos($text, $query, 0, 'UTF-8');

            if ($pos !== false) {
                $start = max(0, $pos - 80);
                $snippet = mb_substr($text, $start, $limit, 'UTF-8');

                return ($start > 0 ? '… ' : '')
                    . $snippet
                    . (mb_strlen($text, 'UTF-8') > ($start + $limit) ? ' …' : '');
            }
        }

        return mb_substr($text, 0, $limit, 'UTF-8')
            . (mb_strlen($text, 'UTF-8') > $limit ? ' …' : '');
    }
}

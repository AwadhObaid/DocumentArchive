<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

class AttachmentIndexInventoryItem
{
    public function __construct(private object $row)
    {
    }

    public function __get(string $name): mixed
    {
        return $this->row->{$name} ?? null;
    }

    public function sourceLabel(): string
    {
        return match ((string) $this->source_type) {
            'memo' => 'مذكرة',
            'circular' => 'تعميم',
            'misc_book' => 'كتاب متفرق',
            default => 'كتاب',
        };
    }

    public function statusName(): string
    {
        return match ((string) $this->resolved_status) {
            'indexed' => 'مفهرس بنجاح',
            'unindexed' => 'غير مفهرس',
            'needs_ocr' => 'يحتاج تعرفًا ضوئيًا',
            'failed' => 'فشلت الفهرسة',
            'missing' => 'الملف غير موجود',
            'skipped' => 'تم تجاوزه',
            'unsupported' => 'نوع غير قابل للفهرسة',
            default => 'بانتظار الفهرسة',
        };
    }

    public function statusClass(): string
    {
        return match ((string) $this->resolved_status) {
            'indexed' => 'success',
            'unindexed', 'pending' => 'info',
            'needs_ocr' => 'warning',
            'failed', 'missing' => 'danger',
            default => 'muted',
        };
    }

    public function extractorName(): string
    {
        return match ((string) $this->extractor) {
            'native' => 'استخراج نص مباشر من PDF',
            'ocr' => 'تعرف ضوئي على صورة المستند',
            'mixed' => 'استخراج مباشر + تعرف ضوئي',
            'none', '' => 'لم يتم تشغيل معالج بعد',
            default => (string) $this->extractor,
        };
    }

    public function lastIndexedAt(): ?Carbon
    {
        if (! $this->last_indexed_at) {
            return null;
        }

        try {
            return Carbon::parse($this->last_indexed_at);
        } catch (\Throwable) {
            return null;
        }
    }

    public function recordRoute(): ?string
    {
        return match ((string) $this->source_type) {
            'document' => route('documents.show', $this->source_id),
            'memo' => route('memos.show', $this->source_id),
            'circular' => route('circulars.show', $this->source_id),
            'misc_book' => route('misc-books.show', $this->source_id),
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
            $position = mb_stripos($text, $query, 0, 'UTF-8');

            if ($position !== false) {
                $start = max(0, $position - 80);
                $snippet = mb_substr($text, $start, $limit, 'UTF-8');

                return ($start > 0 ? '… ' : '')
                    . $snippet
                    . (mb_strlen($text, 'UTF-8') > ($start + $limit) ? ' …' : '');
            }
        }

        return Str::limit($text, $limit, ' …');
    }

    public function friendlyErrorMessage(): string
    {
        $message = trim((string) $this->error_message);

        if ($message === '') {
            return '';
        }

        $lower = mb_strtolower($message, 'UTF-8');

        if (str_contains($lower, 'permission denied') || str_contains($lower, 'access is denied')) {
            return 'لا توجد صلاحية كافية لقراءة الملف أو تشغيل أداة الفهرسة.';
        }

        if (str_contains($lower, 'pdftotext')) {
            return 'أداة استخراج النص من PDF غير مثبتة أو أن مسارها غير صحيح.';
        }

        if (str_contains($lower, 'tesseract')) {
            return 'محرك التعرف الضوئي غير مثبت أو أن مساره غير صحيح.';
        }

        return $message;
    }
}

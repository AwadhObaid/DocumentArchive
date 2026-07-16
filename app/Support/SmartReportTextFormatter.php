<?php

declare(strict_types=1);

namespace App\Support;

class SmartReportTextFormatter
{
    public static function clean(string|null $text): string
    {
        $text = (string) $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/```(?:[a-zA-Z0-9_-]+)?\s*/u', '', $text) ?? $text;
        $text = str_replace('```', '', $text);
        $text = preg_replace('/^\s*#{1,6}\s*/um', '', $text) ?? $text;
        $text = preg_replace('/\*\*(.*?)\*\*/us', '$1', $text) ?? $text;
        $text = preg_replace('/__([^_]+)__/u', '$1', $text) ?? $text;
        $text = preg_replace('/`([^`]+)`/u', '$1', $text) ?? $text;
        $text = preg_replace('/^\s*[-–—]{3,}\s*$/um', '', $text) ?? $text;
        $text = preg_replace('/^\s*\|.*\|\s*$/um', '', $text) ?? $text;
        $text = preg_replace('/\b(?:Gemini API|gemini api|V66|V67|V68|V69)\b/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+$/um', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    public static function toHtml(string|null $text): string
    {
        $text = self::clean($text);
        if ($text === '') {
            return '<p class="smart-report-muted">لا توجد نتيجة تحليل محفوظة لهذا التقرير.</p>';
        }

        $lines = preg_split('/\n/u', $text) ?: [];
        $html = '';
        $listOpen = false;

        foreach ($lines as $rawLine) {
            $line = trim((string) $rawLine);

            if ($line === '') {
                if ($listOpen) {
                    $html .= '</ul>';
                    $listOpen = false;
                }
                continue;
            }

            $line = self::cleanLine($line);
            if ($line === '') {
                continue;
            }

            if (self::isHeading($line)) {
                if ($listOpen) {
                    $html .= '</ul>';
                    $listOpen = false;
                }
                $html .= '<h3>' . e(self::normalizeHeading($line)) . '</h3>';
                continue;
            }

            $listItem = self::extractListItem($line);
            if ($listItem !== null) {
                if (! $listOpen) {
                    $html .= '<ul class="smart-report-list">';
                    $listOpen = true;
                }
                $html .= '<li>' . e($listItem) . '</li>';
                continue;
            }

            if ($listOpen) {
                $html .= '</ul>';
                $listOpen = false;
            }

            $html .= '<p>' . e($line) . '</p>';
        }

        if ($listOpen) {
            $html .= '</ul>';
        }

        return $html;
    }

    public static function excerpt(string|null $text, int $limit = 420): string
    {
        $text = self::clean($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit) . '...';
    }

    private static function cleanLine(string $line): string
    {
        $line = preg_replace('/^\s*#{1,6}\s*/u', '', $line) ?? $line;
        $line = preg_replace('/\*\*(.*?)\*\*/us', '$1', $line) ?? $line;
        $line = preg_replace('/__([^_]+)__/u', '$1', $line) ?? $line;
        $line = preg_replace('/`([^`]+)`/u', '$1', $line) ?? $line;
        $line = preg_replace('/^\s*[|]+\s*/u', '', $line) ?? $line;
        $line = preg_replace('/\s*[|]+\s*$/u', '', $line) ?? $line;
        $line = trim($line);
        $line = trim($line, " \t\n\r\0\x0B-–—");

        return trim($line);
    }

    private static function isHeading(string $line): bool
    {
        $line = trim($line);
        if (mb_strlen($line) > 110) {
            return false;
        }

        return (bool) preg_match('/^(?:أولاً|أولًا|ثانياً|ثانيًا|ثالثاً|ثالثًا|رابعاً|رابعًا|خامساً|خامسًا|سادساً|سادسًا|سابعاً|سابعًا|ثامناً|ثامنًا|الملخص التنفيذي|المؤشرات الرئيسية|قراءة الرسوم|قراءة البيانات|المخاطر|الملاحظات|التوصيات|التوصيات العملية|الخلاصة|نتائج التحليل|تحليل|تقييم|جودة البيانات)\b/u', $line);
    }

    private static function normalizeHeading(string $line): string
    {
        $line = preg_replace('/^\s*(أولاً|أولًا|ثانياً|ثانيًا|ثالثاً|ثالثًا|رابعاً|رابعًا|خامساً|خامسًا|سادساً|سادسًا|سابعاً|سابعًا|ثامناً|ثامنًا)\s*[:：\-–—]*\s*/u', '$1: ', $line) ?? $line;
        $line = preg_replace('/\s*[:：]\s*$/u', '', $line) ?? $line;

        return trim($line);
    }

    private static function extractListItem(string $line): ?string
    {
        if (preg_match('/^\s*[\*\-•▪▫]+\s*(.+)$/u', $line, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^\s*(?:\d+|[٠-٩]+)\s*[\.)\-]\s*(.+)$/u', $line, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^\s*\.\s*(?:\d+|[٠-٩]+)\s+(.+)$/u', $line, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^\s*(?:[أابجدهوزحطيكلمنسعفصقرشتثخذضظغ]\s*[\.)\-])\s*(.+)$/u', $line, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}

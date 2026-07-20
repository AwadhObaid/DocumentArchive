<?php

namespace App\Services;

use RuntimeException;

class LegacyArchiveCsvReader
{
    public const COLUMNS = [
        'ID',
        'ArchiveNum',
        'ArchiveDate',
        'ArchiveSubject',
        'ArchiveFolder',
        'FileName',
        'FileType',
        'FilePath',
        'OriginalFilePath',
        'ArchiveWillMaster',
        'ArchiveWillSub',
        'UserName',
        'ArchiveNotes',
        'IsDeleted',
        'DeletedDate',
        'DeletedBy',
        'SaderID',
        'OriginalSaderID',
        'OriginalFileName',
    ];

    public function read(string $path, int $limit = 100000): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('ملف التصدير غير موجود أو غير قابل للقراءة: ' . $path);
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('تعذر قراءة ملف التصدير.');
        }

        $utf8 = $this->toUtf8($raw);
        $delimiter = $this->detectDelimiter($utf8);

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('تعذر تجهيز ملف التصدير للقراءة.');
        }

        fwrite($stream, $utf8);
        rewind($stream);

        $rows = [];
        $line = 0;

        while (($columns = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
            $line++;

            if ($line === 1 && $this->looksLikeHeader($columns)) {
                continue;
            }

            if ($this->isEmptyRow($columns)) {
                continue;
            }

            $columns = array_values($columns);

            if (count($columns) < count(self::COLUMNS)) {
                $columns = array_pad($columns, count(self::COLUMNS), null);
            } elseif (count($columns) > count(self::COLUMNS)) {
                $columns = array_slice($columns, 0, count(self::COLUMNS));
            }

            $row = array_combine(self::COLUMNS, $columns);
            if (! is_array($row)) {
                continue;
            }

            /*
             * LEGACY_CSV_PATH_MARKS_FIX_V81_6
             *
             * Unicode direction marks can be part of a real Windows share,
             * folder, or file name. Preserve them in path-related columns.
             */
            $pathColumns = [
                'ArchiveFolder',
                'FileName',
                'FilePath',
                'OriginalFilePath',
                'OriginalFileName',
            ];

            foreach ($row as $key => $value) {
                $row[$key] = $this->normalizeCell(
                    $value,
                    in_array($key, $pathColumns, true)
                );
            }

            $row['_line'] = $line;
            $rows[] = $row;

            if (count($rows) >= max(1, $limit)) {
                break;
            }
        }

        fclose($stream);

        return $rows;
    }

    private function toUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return substr($raw, 3);
        }

        if (str_starts_with($raw, "\xFF\xFE")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($raw, "\xFE\xFF")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        }

        $encoding = mb_detect_encoding($raw, ['UTF-8', 'Windows-1256', 'ISO-8859-6', 'Windows-1252'], true);

        return $encoding && $encoding !== 'UTF-8'
            ? mb_convert_encoding($raw, 'UTF-8', $encoding)
            : $raw;
    }

    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\r\n") ?: '';
        $candidates = [",", "\t", ";"];
        $best = ',';
        $bestCount = -1;

        foreach ($candidates as $candidate) {
            $count = substr_count($firstLine, $candidate);
            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function looksLikeHeader(array $columns): bool
    {
        $first = trim((string) ($columns[0] ?? ''));

        return strcasecmp($first, 'ID') === 0
            || strcasecmp($first, 'ArchiveNum') === 0
            || ! ctype_digit($first);
    }

    private function isEmptyRow(array $columns): bool
    {
        foreach ($columns as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeCell(
        mixed $value,
        bool $preserveDirectionalMarks = false
    ): ?string {
        $value = trim((string) $value);

        if (! $preserveDirectionalMarks) {
            $value = preg_replace(
                '/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u',
                '',
                $value
            ) ?? $value;
        }

        if ($value === '' || strcasecmp($value, 'NULL') === 0) {
            return null;
        }

        return $value;
    }
}

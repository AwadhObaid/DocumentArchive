<?php

namespace App\Services;

use App\Models\CircularAttachment;
use App\Models\LegacyCircularMiscImportItem;
use App\Models\LegacyCircularMiscImportRun;
use App\Models\LegacyCircularMiscImportSource;
use App\Models\MiscBookAttachment;
use Carbon\Carbon;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class LegacyCircularMiscInventoryService
{
    private const SUPPORTED_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'tif', 'tiff',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf',
    ];

    public function scan(LegacyCircularMiscImportRun $run): void
    {
        @set_time_limit(0);
        $seenHashes = [];
        $numberState = [];

        foreach ($run->sources()->with('category')->get() as $source) {
            $this->scanSource($run, $source, $seenHashes, $numberState);
        }

        $counts = LegacyCircularMiscImportItem::query()
            ->where('run_id', $run->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $run->update([
            'status' => 'completed',
            'total_files' => (int) $counts->sum(),
            'ready_count' => (int) ($counts['ready'] ?? 0),
            'needs_review_count' => (int) ($counts['needs_review'] ?? 0),
            'existing_count' => (int) ($counts['existing'] ?? 0),
            'duplicate_count' => (int) ($counts['duplicate'] ?? 0),
            'unreadable_count' => (int) ($counts['unreadable'] ?? 0),
            'unsupported_count' => (int) ($counts['unsupported'] ?? 0),
            'total_size' => (int) LegacyCircularMiscImportItem::query()
                ->where('run_id', $run->id)
                ->sum('file_size'),
            'completed_at' => now(),
        ]);
    }

    private function scanSource(
        LegacyCircularMiscImportRun $run,
        LegacyCircularMiscImportSource $source,
        array &$seenHashes,
        array &$numberState
    ): void {
        $root = $this->normalizePath($source->source_path);

        if (! is_dir($root) || ! is_readable($root)) {
            LegacyCircularMiscImportItem::create([
                'run_id' => $run->id,
                'source_id' => $source->id,
                'target_module' => $source->target_module,
                'category_id' => $source->category_id,
                'source_path' => $root,
                'file_name' => basename(str_replace('\\', '/', $root)) ?: $root,
                'status' => 'unreadable',
                'notes' => 'المجلد غير موجود أو حساب PHP/Apache لا يملك صلاحية قراءته.',
            ]);
            return;
        }

        foreach ($this->files($root, (bool) $source->recursive) as $file) {
            $this->scanFile($run, $source, $root, $file, $seenHashes, $numberState);
        }
    }

    private function scanFile(
        LegacyCircularMiscImportRun $run,
        LegacyCircularMiscImportSource $source,
        string $root,
        SplFileInfo $file,
        array &$seenHashes,
        array &$numberState
    ): void {
        $path = $file->getPathname();
        $name = $file->getFilename();
        $extension = strtolower($file->getExtension());
        $size = $file->isReadable() ? (int) $file->getSize() : 0;
        $modifiedAt = $file->getMTime() > 0
            ? Carbon::createFromTimestamp($file->getMTime())
            : null;

        $base = [
            'run_id' => $run->id,
            'source_id' => $source->id,
            'target_module' => $source->target_module,
            'category_id' => $source->category_id,
            'source_path' => $path,
            'relative_path' => ltrim(substr($path, strlen($root)), '\\/'),
            'file_name' => $name,
            'extension' => $extension ?: null,
            'file_size' => $size,
            'file_modified_at' => $modifiedAt,
            'proposed_direction' => $source->default_direction,
            'proposed_nature' => $source->default_nature,
            'proposed_entity' => $source->default_entity,
        ];

        if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            LegacyCircularMiscImportItem::create($base + [
                'status' => 'unsupported',
                'notes' => 'امتداد الملف غير مدعوم في أداة الاستيراد.',
            ]);
            return;
        }

        if (! $file->isReadable()) {
            LegacyCircularMiscImportItem::create($base + [
                'status' => 'unreadable',
                'notes' => 'تعذر قراءة الملف بواسطة حساب PHP/Apache.',
            ]);
            return;
        }

        $sha256 = @hash_file('sha256', $path);
        if (! is_string($sha256) || strlen($sha256) !== 64) {
            LegacyCircularMiscImportItem::create($base + [
                'status' => 'unreadable',
                'notes' => 'تعذر حساب بصمة SHA-256 للملف.',
            ]);
            return;
        }

        $base['sha256'] = $sha256;
        $existing = $this->existingAttachment($sha256, $path);
        if ($existing) {
            LegacyCircularMiscImportItem::create($base + [
                'status' => 'existing',
                'existing_type' => $existing['type'],
                'existing_id' => $existing['id'],
                'notes' => 'الملف موجود مسبقًا في النظام.',
            ]);
            return;
        }

        if (isset($seenHashes[$sha256])) {
            LegacyCircularMiscImportItem::create($base + [
                'status' => 'duplicate',
                'duplicate_of_item_id' => $seenHashes[$sha256],
                'notes' => 'نفس المحتوى ظهر في مصدر آخر ضمن عملية الفحص.',
            ]);
            return;
        }

        $date = $this->proposedDate($name, $modifiedAt);
        $subject = $this->proposedSubject($name, $file->getPath());
        $genericName = $this->looksGeneric($subject);
        $needsReview = ! $source->category_id || $genericName;
        $number = $this->nextProposedNumber($source->target_module, $numberState);

        $item = LegacyCircularMiscImportItem::create($base + [
            'proposed_date' => $date?->toDateString(),
            'proposed_subject' => $subject,
            'proposed_number' => $number,
            'status' => $needsReview ? 'needs_review' : 'ready',
            'notes' => $needsReview
                ? 'راجع التصنيف أو الموضوع المقترح قبل الاستيراد.'
                : null,
        ]);
        $seenHashes[$sha256] = $item->id;
    }

    private function files(string $root, bool $recursive): iterable
    {
        if ($recursive) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $root,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
        } else {
            $iterator = new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS);
        }

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                yield $file;
            }
        }
    }

    private function existingAttachment(string $sha256, string $path): ?array
    {
        $circular = CircularAttachment::withTrashed()
            ->where(function ($query) use ($sha256, $path) {
                $query->where('sha256', $sha256)->orWhere('source_path', $path);
            })->first();
        if ($circular) return ['type' => 'circular', 'id' => $circular->id];

        $misc = MiscBookAttachment::withTrashed()
            ->where(function ($query) use ($sha256, $path) {
                $query->where('sha256', $sha256)->orWhere('source_path', $path);
            })->first();
        return $misc ? ['type' => 'misc_book', 'id' => $misc->id] : null;
    }

    private function nextProposedNumber(string $module, array &$state): string
    {
        if (! isset($state[$module])) {
            $payload = $module === 'circular'
                ? CircularNumberGenerator::preview()
                : MiscBookNumberGenerator::preview();
            $state[$module] = (int) ($payload[$module === 'circular' ? 'circular_number' : 'misc_number']);
        }
        return (string) $state[$module]++;
    }

    private function proposedDate(string $name, ?Carbon $fallback): ?Carbon
    {
        $patterns = [
            '/(?<!\d)(20\d{2})[-_. ](0?[1-9]|1[0-2])[-_. ](0?[1-9]|[12]\d|3[01])(?!\d)/',
            '/(?<!\d)(0?[1-9]|[12]\d|3[01])[-_. ](0?[1-9]|1[0-2])[-_. ](20\d{2})(?!\d)/',
        ];
        if (preg_match($patterns[0], $name, $m)) {
            try { return Carbon::create((int)$m[1], (int)$m[2], (int)$m[3]); } catch (\Throwable) {}
        }
        if (preg_match($patterns[1], $name, $m)) {
            try { return Carbon::create((int)$m[3], (int)$m[2], (int)$m[1]); } catch (\Throwable) {}
        }
        return $fallback?->copy()->startOfDay();
    }

    private function proposedSubject(string $fileName, string $directory): string
    {
        $subject = pathinfo($fileName, PATHINFO_FILENAME);
        $subject = preg_replace('/[_]+/u', ' ', $subject) ?: $subject;
        $subject = preg_replace('/\s*-\s*/u', ' - ', $subject) ?: $subject;
        $subject = preg_replace('/\s+/u', ' ', $subject) ?: $subject;
        $subject = trim($subject, " \t\n\r\0\x0B-_.");
        if ($subject === '') {
            $subject = basename(str_replace('\\', '/', $directory));
        }
        return mb_substr($subject, 0, 500);
    }

    private function looksGeneric(string $subject): bool
    {
        $normalized = mb_strtolower(trim($subject));
        return $normalized === ''
            || preg_match('/^(scan|img|image|document|file|مستند|صورة)[-_ ]*\d*$/iu', $normalized) === 1
            || preg_match('/^\d+$/u', $normalized) === 1;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        return rtrim($path, "\\/");
    }
}

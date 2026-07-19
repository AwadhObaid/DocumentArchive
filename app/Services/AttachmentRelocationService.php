<?php

namespace App\Services;

use App\Models\AttachmentRelocationItem;
use App\Models\AttachmentRelocationRun;
use App\Models\DocumentAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentRelocationService
{
    public function __construct(private readonly BookAttachmentSmartPathService $pathService)
    {
    }

    public function summarize(int $limit = 500): array
    {
        $plans = $this->plans($limit);
        $summary = $this->countPlans($plans);
        $summary['items'] = array_slice($plans, 0, 30);
        $summary['limit'] = $limit;

        return $summary;
    }

    public function dryRun(int $limit = 500): AttachmentRelocationRun
    {
        return $this->persistRun('dry_run', false, $this->plans($limit));
    }

    public function execute(int $limit = 500, bool $deleteOriginal = false): AttachmentRelocationRun
    {
        $run = AttachmentRelocationRun::query()->create([
            'mode' => 'execute',
            'status' => 'running',
            'delete_original' => $deleteOriginal,
            'created_by' => Auth::id(),
            'started_at' => now(),
        ]);

        $stats = [
            'total_items' => 0,
            'ready_items' => 0,
            'moved_items' => 0,
            'copied_items' => 0,
            'skipped_items' => 0,
            'missing_items' => 0,
            'failed_items' => 0,
        ];

        foreach ($this->plans($limit) as $plan) {
            $stats['total_items']++;

            try {
                if ($plan['status'] !== 'ready') {
                    $this->storeItem($run, $plan);
                    $this->bumpSkippedOrMissing($stats, $plan['status']);
                    continue;
                }

                $result = $this->relocateOne($plan, $deleteOriginal);
                $this->storeItem($run, $result);

                if ($result['status'] === 'moved') {
                    $stats['moved_items']++;
                } elseif ($result['status'] === 'copied') {
                    $stats['copied_items']++;
                } else {
                    $this->bumpSkippedOrMissing($stats, $result['status']);
                }
            } catch (\Throwable $exception) {
                $plan['status'] = 'failed';
                $plan['message'] = $exception->getMessage();
                $this->storeItem($run, $plan);
                $stats['failed_items']++;
            }
        }

        $run->update(array_merge($stats, [
            'status' => $stats['failed_items'] > 0 ? 'failed' : 'finished',
            'finished_at' => now(),
        ]));

        ActivityLogger::log(
            'attachments.relocation.executed',
            'تم تشغيل أداة ترتيب المرفقات القديمة.',
            $run,
            ['delete_original' => $deleteOriginal] + $stats
        );

        return $run->fresh(['items.attachment', 'items.document']);
    }

    public function plans(int $limit = 500): array
    {
        $limit = max(1, min(5000, $limit));

        return DocumentAttachment::query()
            ->with('document')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (DocumentAttachment $attachment) => $this->buildPlan($attachment))
            ->all();
    }

    private function buildPlan(DocumentAttachment $attachment): array
    {
        $document = $attachment->document;
        $sourcePath = (string) ($attachment->file_path ?? '');
        $sourceRoot = $attachment->storage_root_path;
        $sourceDisk = $attachment->disk ?: 'local';
        $sourceAbsolute = $this->pathService->absolutePathForAttachment($attachment);
        $sourceExists = $sourceAbsolute !== null && is_file($sourceAbsolute);

        $plan = [
            'attachment_id' => $attachment->id,
            'document_id' => $attachment->document_id,
            'reference_number' => $document?->reference_number,
            'source_path' => $sourcePath,
            'source_disk' => $sourceDisk,
            'source_root' => $sourceRoot,
            'source_absolute' => $sourceAbsolute,
            'source_exists' => $sourceExists,
            'target_path' => null,
            'target_disk' => null,
            'target_root' => null,
            'target_absolute' => null,
            'target_exists' => false,
            'file_size' => $sourceExists ? @filesize($sourceAbsolute) : ($attachment->file_size ?: null),
            'status' => 'ready',
            'message' => 'جاهز للنقل إلى التصنيف الجديد.',
            'metadata' => [
                'original_name' => $attachment->original_name,
                'file_name' => $attachment->file_name,
            ],
        ];

        if (! $document) {
            $plan['status'] = 'missing_document';
            $plan['message'] = 'المرفق غير مرتبط بكتاب موجود.';
            return $plan;
        }

        $classification = $this->pathService->buildFolder($document);
        $plan['metadata']['classification'] = $classification;

        if (! ($classification['smart'] ?? false)) {
            $plan['status'] = 'missing_classification';
            $plan['message'] = 'لا توجد شركة/جهة أو نوع عملية للكتاب، لذلك لم يتم تحديد مسار التصنيف الذكي.';
            return $plan;
        }

        if (! $sourceExists) {
            $plan['status'] = 'missing_file';
            $plan['message'] = 'الملف غير موجود في المسار الحالي.';
            return $plan;
        }

        $fileName = $this->safeExistingFileName($attachment);
        $targetRelative = trim($classification['folder'], '/\\') . '/' . $fileName;
        $targetRoot = $this->pathService->configuredStorageRoot();
        $targetDisk = $targetRoot ? BookAttachmentSmartPathService::CUSTOM_DISK : 'local';
        $targetAbsolute = $targetRoot
            ? $this->joinRootAndRelative($targetRoot, $targetRelative)
            : Storage::disk('local')->path($targetRelative);

        if ($this->samePath($sourceAbsolute, $targetAbsolute)) {
            $plan['status'] = 'already_sorted';
            $plan['message'] = 'المرفق موجود مسبقًا في المسار الصحيح.';
        }

        $plan['target_path'] = $targetRelative;
        $plan['target_disk'] = $targetDisk;
        $plan['target_root'] = $targetRoot;
        $plan['target_absolute'] = $targetAbsolute;
        $plan['target_exists'] = is_file($targetAbsolute);

        return $plan;
    }

    private function relocateOne(array $plan, bool $deleteOriginal): array
    {
        $source = $plan['source_absolute'];
        $target = $this->uniqueTargetPath($plan['target_absolute']);
        $targetRelative = $plan['target_path'];

        if ($target !== $plan['target_absolute']) {
            $targetRelative = trim(dirname($targetRelative), '.\\/') . '/' . basename($target);
            $targetRelative = ltrim(str_replace('\\', '/', $targetRelative), '/');
        }

        $folder = dirname($target);
        if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
            throw new \RuntimeException('تعذر إنشاء مجلد الهدف: ' . $folder);
        }

        if (! @copy($source, $target)) {
            throw new \RuntimeException('تعذر نسخ الملف إلى المسار الجديد.');
        }

        $attachment = DocumentAttachment::query()->findOrFail($plan['attachment_id']);
        $classification = $plan['metadata']['classification'] ?? [];

        DB::transaction(function () use ($attachment, $targetRelative, $plan, $classification, $target) {
            $attachment->update([
                'file_name' => basename($target),
                'file_path' => $targetRelative,
                'disk' => $plan['target_disk'],
                'storage_root_path' => $plan['target_root'],
                'classification_company_name' => $classification['company'] ?? null,
                'classification_operation_name' => $classification['operation'] ?? null,
                'classification_year' => $classification['year'] ?? null,
                'classification_folder' => $classification['folder'] ?? null,
                'file_size' => is_file($target) ? @filesize($target) : $attachment->file_size,
            ]);
        });

        $deleted = false;
        if ($deleteOriginal && ! $this->samePath($source, $target) && is_file($source)) {
            $deleted = @unlink($source);
        }

        $plan['target_path'] = $targetRelative;
        $plan['target_absolute'] = $target;
        $plan['target_exists'] = is_file($target);
        $plan['status'] = $deleteOriginal ? 'moved' : 'copied';
        $plan['message'] = $deleteOriginal
            ? ($deleted ? 'تم نقل الملف وتحديث قاعدة البيانات.' : 'تم نسخ الملف وتحديث قاعدة البيانات، لكن تعذر حذف النسخة القديمة.')
            : 'تم نسخ الملف إلى المسار الجديد وتحديث قاعدة البيانات، مع إبقاء النسخة القديمة احتياطياً.';

        return $plan;
    }

    private function persistRun(string $mode, bool $deleteOriginal, array $plans): AttachmentRelocationRun
    {
        $summary = $this->countPlans($plans);

        $run = AttachmentRelocationRun::query()->create([
            'mode' => $mode,
            'status' => 'finished',
            'delete_original' => $deleteOriginal,
            'created_by' => Auth::id(),
            'started_at' => now(),
            'finished_at' => now(),
            'total_items' => $summary['total'],
            'ready_items' => $summary['ready'],
            'moved_items' => 0,
            'copied_items' => 0,
            'skipped_items' => $summary['skipped'],
            'missing_items' => $summary['missing'],
            'failed_items' => 0,
        ]);

        foreach ($plans as $plan) {
            $this->storeItem($run, $plan);
        }

        ActivityLogger::log('attachments.relocation.dry_run', 'تم فحص المرفقات القديمة بدون نقل.', $run, $summary);

        return $run->fresh(['items.attachment', 'items.document']);
    }

    private function storeItem(AttachmentRelocationRun $run, array $plan): AttachmentRelocationItem
    {
        return AttachmentRelocationItem::query()->create([
            'run_id' => $run->id,
            'document_attachment_id' => $plan['attachment_id'] ?? null,
            'document_id' => $plan['document_id'] ?? null,
            'reference_number' => $plan['reference_number'] ?? null,
            'original_path' => $plan['source_path'] ?? null,
            'original_disk' => $plan['source_disk'] ?? null,
            'original_root_path' => $plan['source_root'] ?? null,
            'target_path' => $plan['target_path'] ?? null,
            'target_disk' => $plan['target_disk'] ?? null,
            'target_root_path' => $plan['target_root'] ?? null,
            'status' => $plan['status'] ?? 'ready',
            'message' => $plan['message'] ?? null,
            'source_exists' => (bool) ($plan['source_exists'] ?? false),
            'target_exists' => (bool) ($plan['target_exists'] ?? false),
            'file_size' => $plan['file_size'] ?? null,
            'metadata' => [
                'source_absolute' => $plan['source_absolute'] ?? null,
                'target_absolute' => $plan['target_absolute'] ?? null,
                'classification' => $plan['metadata']['classification'] ?? null,
                'original_name' => $plan['metadata']['original_name'] ?? null,
                'file_name' => $plan['metadata']['file_name'] ?? null,
            ],
        ]);
    }

    private function countPlans(array $plans): array
    {
        $summary = [
            'total' => count($plans),
            'ready' => 0,
            'already_sorted' => 0,
            'missing' => 0,
            'missing_classification' => 0,
            'skipped' => 0,
        ];

        foreach ($plans as $plan) {
            match ($plan['status'] ?? '') {
                'ready' => $summary['ready']++,
                'already_sorted' => $summary['already_sorted']++,
                'missing_file', 'missing_document' => $summary['missing']++,
                'missing_classification' => $summary['missing_classification']++,
                default => $summary['skipped']++,
            };
        }

        $summary['skipped'] += $summary['already_sorted'] + $summary['missing_classification'];

        return $summary;
    }

    private function bumpSkippedOrMissing(array &$stats, string $status): void
    {
        if (in_array($status, ['missing_file', 'missing_document'], true)) {
            $stats['missing_items']++;
            return;
        }

        $stats['skipped_items']++;
    }

    private function safeExistingFileName(DocumentAttachment $attachment): string
    {
        $name = $attachment->file_name ?: basename((string) $attachment->file_path);
        $name = trim(str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ' ', $name));
        $name = preg_replace('/\s+/u', '_', $name) ?: $name;
        $name = trim($name, '._-');

        if ($name === '') {
            $extension = strtolower($attachment->extension ?: pathinfo((string) $attachment->original_name, PATHINFO_EXTENSION) ?: 'bin');
            $name = 'attachment_' . $attachment->id . '.' . $extension;
        }

        return mb_substr($name, 0, 180);
    }

    private function uniqueTargetPath(string $path): string
    {
        if (! is_file($path)) {
            return $path;
        }

        $folder = dirname($path);
        $base = pathinfo($path, PATHINFO_FILENAME) ?: 'file';
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        for ($i = 1; $i <= 200; $i++) {
            $candidate = $folder . DIRECTORY_SEPARATOR . $base . '_relocated_' . date('Ymd_His') . '_' . $i . ($extension ? '.' . $extension : '');
            if (! is_file($candidate)) {
                return $candidate;
            }
        }

        return $folder . DIRECTORY_SEPARATOR . $base . '_' . Str::random(12) . ($extension ? '.' . $extension : '');
    }

    private function joinRootAndRelative(string $root, string $relative): string
    {
        $relative = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative), '\\/');

        return rtrim($root, '\\/') . DIRECTORY_SEPARATOR . $relative;
    }

    private function samePath(?string $a, ?string $b): bool
    {
        if (! $a || ! $b) {
            return false;
        }

        $ra = realpath($a) ?: $a;
        $rb = realpath($b) ?: $b;

        return strtolower(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ra)) === strtolower(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rb));
    }
}

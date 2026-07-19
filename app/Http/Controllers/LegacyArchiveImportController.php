<?php

namespace App\Http\Controllers;

use App\Models\LegacyArchiveImportRun;
use App\Services\LegacyArchiveImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class LegacyArchiveImportController extends Controller
{
    public function index()
    {
        $this->authorizeImport();

        $bundledFile = $this->bundledFile();
        $runs = LegacyArchiveImportRun::query()
            ->with('creator')
            ->latest()
            ->limit(15)
            ->get();

        return view('legacy_archive_import.index', [
            'runs' => $runs,
            'bundledFile' => $bundledFile,
            'bundledExists' => is_file($bundledFile),
            'bundledSize' => is_file($bundledFile) ? (int) (@filesize($bundledFile) ?: 0) : 0,
        ]);
    }

    public function dryRun(Request $request, LegacyArchiveImportService $service)
    {
        $this->authorizeImport();
        $validated = $this->validateRequest($request, false);

        try {
            $sourceFile = $this->resolveSourceFile($request);
            $run = $service->dryRun(
                $sourceFile,
                $validated['source_root_override'] ?? null,
                $request->boolean('import_missing_without_file', true),
                (int) ($validated['limit'] ?? 100000)
            );
        } catch (Throwable $exception) {
            return back()->withErrors(['archive_file' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('legacy-archive-import.show', $run)
            ->with('success', 'اكتمل الفحص التجريبي. لم يتم إنشاء كتب أو نسخ ملفات.');
    }

    public function execute(Request $request, LegacyArchiveImportService $service)
    {
        $this->authorizeImport();
        $validated = $this->validateRequest($request, true);

        if (trim((string) ($validated['confirm_phrase'] ?? '')) !== 'استيراد الأرشيف القديم') {
            return back()
                ->withErrors(['confirm_phrase' => 'عبارة التأكيد غير صحيحة. اكتب: استيراد الأرشيف القديم'])
                ->withInput();
        }

        try {
            $sourceFile = $this->resolveSourceFile($request);
            $run = $service->execute(
                $sourceFile,
                $validated['source_root_override'] ?? null,
                $request->boolean('import_missing_without_file', true),
                (int) ($validated['limit'] ?? 100000)
            );
        } catch (Throwable $exception) {
            return back()->withErrors(['archive_file' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('legacy-archive-import.show', $run)
            ->with('success', 'اكتملت محاولة استيراد الأرشيف القديم. راجع التقرير التفصيلي.');
    }

    public function show(LegacyArchiveImportRun $run)
    {
        $this->authorizeImport();

        $items = $run->items()
            ->with('document')
            ->orderBy('id')
            ->paginate(100);

        return view('legacy_archive_import.show', compact('run', 'items'));
    }

    private function validateRequest(Request $request, bool $execute): array
    {
        $rules = [
            'archive_file' => ['nullable', 'file', 'max:51200'],
            'use_bundled_file' => ['nullable', 'boolean'],
            'source_root_override' => ['nullable', 'string', 'max:1000'],
            'import_missing_without_file' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];

        if ($execute) {
            $rules['confirm_phrase'] = ['required', 'string', 'max:100'];
        }

        return $request->validate($rules, [
            'archive_file.max' => 'حجم ملف التصدير يجب ألا يتجاوز 50 ميجابايت.',
            'confirm_phrase.required' => 'اكتب عبارة التأكيد قبل بدء الاستيراد الفعلي.',
        ]);
    }

    private function resolveSourceFile(Request $request): string
    {
        if ($request->hasFile('archive_file')) {
            $file = $request->file('archive_file');
            $safeName = now()->format('Ymd_His') . '_' . Str::random(8) . '_' . preg_replace(
                '/[^\p{Arabic}\p{L}\p{N}_.-]+/u',
                '_',
                $file->getClientOriginalName()
            );

            $stored = $file->storeAs('legacy-archive-imports/uploads', $safeName, 'local');

            return Storage::disk('local')->path($stored);
        }

        if ($request->boolean('use_bundled_file', true)) {
            $bundled = $this->bundledFile();
            if (is_file($bundled)) {
                return $bundled;
            }
        }

        throw new \RuntimeException('اختر ملف CSV/TSV أو فعّل استخدام ملف Results.csv المرفق.');
    }

    private function bundledFile(): string
    {
        return storage_path('app/private/legacy-archive-imports/Results.csv');
    }

    private function authorizeImport(): void
    {
        $user = auth()->user();
        $allowed = $user && method_exists($user, 'hasPermission')
            && ($user->hasPermission('legacy_import.manage') || $user->hasPermission('settings.manage'));

        abort_unless($allowed, 403, 'غير مصرح لك باستيراد الأرشيف القديم.');
    }
}

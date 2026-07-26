<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;
use ZipArchive;

class BackupController extends Controller
{
    private string $backupFolder = 'backups';

    private function ensureAdmin(): void
    {
        $role = trim((string) (auth()->user()->role ?? ''));

        $adminRoles = [
            'admin',
            'administrator',
            'super_admin',
            'مدير النظام',
            'مدير',
        ];

        abort_unless(auth()->check() && in_array($role, $adminRoles, true), 403, 'هذه الصفحة متاحة لمدير النظام فقط.');
    }

    public function index(): View
    {
        $this->ensureAdmin();

        return view('backups.index', [
            'backups' => $this->listBackups(),
            'backupPath' => storage_path('app/private/backups'),
        ]);
    }

    public function createDatabaseBackup(Request $request): RedirectResponse
    {
        $this->ensureAdmin();
        @set_time_limit(0);
        ignore_user_abort(true);

        try {
            $fileName = 'database-backup-' . now()->format('Ymd-His') . '.zip';
            $zipPath = $this->absoluteBackupPath($fileName);
            File::ensureDirectoryExists(dirname($zipPath));

            $sql = $this->buildDatabaseDump();
            $manifest = $this->buildBackupManifest('database', $sql, false);

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return back()->with('error', 'تعذر إنشاء ملف النسخة الاحتياطية.');
            }

            $driver = config('database.default');
            $zip->addFromString(
                'database/database-' . $driver . '-' . now()->format('Ymd-His') . '.sql',
                $sql
            );
            $zip->addFromString(
                'BACKUP_MANIFEST.json',
                json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            );
            $zip->addFromString('README.txt', $this->backupReadme('نسخة قاعدة البيانات الكاملة'));

            if (!$zip->close()) {
                throw new \RuntimeException('تعذر إغلاق ملف النسخة الاحتياطية بعد الكتابة.');
            }

            $this->verifyCreatedBackup($zipPath, $manifest, true);
            $this->logBackupEvent('backup.database_created', $fileName, 'إنشاء نسخة احتياطية كاملة من قاعدة البيانات');

            return back()->with('success', 'تم إنشاء نسخة احتياطية كاملة من قاعدة البيانات والتحقق من سلامتها.');
        } catch (Throwable $e) {
            report($e);

            if (isset($zipPath) && File::exists($zipPath)) {
                File::delete($zipPath);
            }

            return back()->with('error', 'فشل إنشاء نسخة قاعدة البيانات: ' . $e->getMessage());
        }
    }

    public function createFilesBackup(Request $request): RedirectResponse
    {
        $this->ensureAdmin();
        @set_time_limit(0);
        ignore_user_abort(true);

        try {
            $fileName = 'files-backup-' . now()->format('Ymd-His') . '.zip';
            $zipPath = $this->absoluteBackupPath($fileName);
            File::ensureDirectoryExists(dirname($zipPath));

            $manifest = $this->buildBackupManifest('files', null, true);
            $this->assertBackupCapacity($zipPath, (int) ($manifest['storage_total_bytes'] ?? 0));

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return back()->with('error', 'تعذر إنشاء ملف النسخة الاحتياطية.');
            }

            $this->addAttachmentDirectoriesToZip($zip);
            $zip->addFromString(
                'BACKUP_MANIFEST.json',
                json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            );
            $zip->addFromString('README.txt', $this->backupReadme('نسخة جميع ملفات المرفقات'));

            if (!$zip->close()) {
                throw new \RuntimeException('تعذر إغلاق ملف النسخة الاحتياطية بعد الكتابة.');
            }

            $this->verifyCreatedBackup($zipPath, $manifest, false);
            $this->logBackupEvent('backup.files_created', $fileName, 'إنشاء نسخة احتياطية من جميع ملفات المرفقات');

            return back()->with('success', 'تم إنشاء نسخة جميع ملفات المرفقات والتحقق من اكتمال المجلدات.');
        } catch (Throwable $e) {
            report($e);

            if (isset($zipPath) && File::exists($zipPath)) {
                File::delete($zipPath);
            }

            return back()->with('error', 'فشل إنشاء نسخة الملفات: ' . $e->getMessage());
        }
    }

    public function createFullBackup(Request $request): RedirectResponse
    {
        $this->ensureAdmin();
        @set_time_limit(0);
        ignore_user_abort(true);

        try {
            $fileName = 'full-backup-' . now()->format('Ymd-His') . '.zip';
            $zipPath = $this->absoluteBackupPath($fileName);
            File::ensureDirectoryExists(dirname($zipPath));

            $sql = $this->buildDatabaseDump();
            $manifest = $this->buildBackupManifest('full', $sql, true);
            $requiredBytes = (int) ($manifest['storage_total_bytes'] ?? 0) + strlen($sql);
            $this->assertBackupCapacity($zipPath, $requiredBytes);

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return back()->with('error', 'تعذر إنشاء ملف النسخة الاحتياطية.');
            }

            $driver = config('database.default');
            $zip->addFromString(
                'database/database-' . $driver . '-' . now()->format('Ymd-His') . '.sql',
                $sql
            );
            $this->addAttachmentDirectoriesToZip($zip);
            $zip->addFromString(
                'BACKUP_MANIFEST.json',
                json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            );
            $zip->addFromString('README.txt', $this->backupReadme('نسخة كاملة موثقة: قاعدة البيانات + جميع المرفقات'));

            if (!$zip->close()) {
                throw new \RuntimeException('تعذر إغلاق ملف النسخة الاحتياطية بعد الكتابة.');
            }

            $this->verifyCreatedBackup($zipPath, $manifest, true);
            $this->logBackupEvent('backup.full_created', $fileName, 'إنشاء نسخة احتياطية كاملة موثقة');

            return back()->with('success', 'تم إنشاء نسخة كاملة والتحقق من قاعدة البيانات وجميع مجلدات المرفقات.');
        } catch (Throwable $e) {
            report($e);

            if (isset($zipPath) && File::exists($zipPath)) {
                File::delete($zipPath);
            }

            return back()->with('error', 'فشل إنشاء النسخة الكاملة: ' . $e->getMessage());
        }
    }

    public function inspect(string $fileName): View
    {
        $this->ensureAdmin();

        $fileName = basename($fileName);
        $path = $this->absoluteBackupPath($fileName);

        if (!File::exists($path)) {
            abort(404, 'ملف النسخة الاحتياطية غير موجود.');
        }

        $info = $this->inspectBackupFile($fileName);

        return view('backups.inspect', $info);
    }

    public function restore(string $fileName): View
    {
        $this->ensureAdmin();

        $fileName = basename($fileName);
        $path = $this->absoluteBackupPath($fileName);

        if (!File::exists($path)) {
            abort(404, 'ملف النسخة الاحتياطية غير موجود.');
        }

        $info = $this->inspectBackupFile($fileName);

        return view('backups.restore', $info);
    }

    public function restoreDatabase(Request $request, string $fileName): RedirectResponse
    {
        $this->ensureAdmin();
        $this->validateRestoreConfirmation($request);

        $fileName = basename($fileName);

        try {
            $this->createPreRestoreDatabaseBackup();
            $this->restoreDatabaseFromZip($fileName);
            $this->logBackupEvent('backup.database_restored', $fileName, 'استعادة قاعدة البيانات من نسخة احتياطية');

            return redirect()
                ->route('backups.index')
                ->with('success', 'تمت استعادة بيانات الأرشيف بنجاح. لم يتم تغيير المستخدمين أو كلمات المرور.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'فشلت استعادة بيانات الأرشيف: ' . $e->getMessage());
        }
    }

    public function restoreFiles(Request $request, string $fileName): RedirectResponse
    {
        $this->ensureAdmin();
        $this->validateRestoreConfirmation($request);

        $fileName = basename($fileName);

        try {
            $this->createPreRestoreFilesBackup();
            $this->restoreDocumentsFromZip($fileName);
            $this->logBackupEvent('backup.files_restored', $fileName, 'استعادة ملفات المرفقات من نسخة احتياطية');

            return redirect()
                ->route('backups.index')
                ->with('success', 'تمت استعادة ملفات المرفقات بنجاح. تم إنشاء نسخة أمان من الملفات الحالية قبل الاستعادة.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'فشلت استعادة ملفات المرفقات: ' . $e->getMessage());
        }
    }

    public function restoreFull(Request $request, string $fileName): RedirectResponse
    {
        $this->ensureAdmin();
        $this->validateRestoreConfirmation($request);

        $fileName = basename($fileName);

        try {
            $this->assertFullBackupSafeForRestore($fileName);
            $this->createPreRestoreDatabaseBackup();
            $this->createPreRestoreFilesBackup();
            $this->restoreDatabaseFromZip($fileName);
            $this->restoreDocumentsFromZip($fileName);
            $this->logBackupEvent('backup.full_restored', $fileName, 'استعادة قاعدة البيانات وجميع مجلدات المرفقات');

            return redirect()
                ->route('backups.index')
                ->with('success', 'تمت استعادة بيانات الأرشيف وجميع مجلدات المرفقات بنجاح. المستخدمون وكلمات المرور لم يتم تغييرهم.');
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'فشلت استعادة النسخة الكاملة: ' . $e->getMessage());
        }
    }

    public function download(string $fileName)
    {
        $this->ensureAdmin();

        $fileName = basename($fileName);
        $path = $this->absoluteBackupPath($fileName);

        if (!File::exists($path)) {
            abort(404, 'ملف النسخة الاحتياطية غير موجود.');
        }

        return response()->download($path, $fileName);
    }

    public function destroy(string $fileName): RedirectResponse
    {
        $this->ensureAdmin();

        $fileName = basename($fileName);
        $path = $this->absoluteBackupPath($fileName);

        if (File::exists($path)) {
            File::delete($path);
            $this->logBackupEvent('backup.deleted', $fileName, 'حذف ملف نسخة احتياطية');
        }

        return back()->with('success', 'تم حذف ملف النسخة الاحتياطية بنجاح.');
    }

    private function listBackups(): array
    {
        $folder = storage_path('app/private/' . $this->backupFolder);
        File::ensureDirectoryExists($folder);

        return collect(File::files($folder))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'zip')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $this->formatBytes($file->getSize()),
                'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
            ])
            ->values()
            ->all();
    }

    private function absoluteBackupPath(string $fileName): string
    {
        return storage_path('app/private/' . $this->backupFolder . '/' . basename($fileName));
    }

    private function validateRestoreConfirmation(Request $request): void
    {
        abort_unless($request->boolean('confirm_restore'), 422, 'يجب تأكيد الموافقة على الاستعادة.');
        abort_unless(trim((string) $request->input('confirm_text')) === 'استعادة', 422, 'اكتب كلمة استعادة لتأكيد العملية.');
    }

    private function inspectBackupFile(string $fileName): array
    {
        $fileName = basename($fileName);
        $path = $this->absoluteBackupPath($fileName);

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            abort(422, 'تعذر فتح ملف النسخة الاحتياطية للفحص.');
        }

        $entries = [];
        $sqlFiles = [];
        $readmeContent = null;
        $manifest = null;
        $manifestReadError = null;
        $totalUncompressedSize = 0;
        $sqlContent = null;

        $directoryStats = [];
        foreach (array_keys($this->attachmentStorageDirectories()) as $directory) {
            $directoryStats[$directory] = [
                'directory' => $directory,
                'present' => false,
                'file_count' => 0,
                'total_bytes' => 0,
            ];
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) {
                continue;
            }

            $name = str_replace('\\', '/', (string) $stat['name']);
            $size = (int) ($stat['size'] ?? 0);
            $totalUncompressedSize += $size;
            $lowerName = strtolower($name);
            $isDirectory = str_ends_with($name, '/');

            if (str_ends_with($lowerName, '.sql')) {
                $sqlFiles[] = $name;

                if ($sqlContent === null) {
                    $sqlContent = $zip->getFromIndex($i);
                    if ($sqlContent === false) {
                        $sqlContent = null;
                    }
                }
            }

            if ($lowerName === 'readme.txt') {
                $readmeContent = $zip->getFromIndex($i) ?: null;
            }

            if ($lowerName === 'backup_manifest.json') {
                $manifestRaw = $zip->getFromIndex($i);

                if ($manifestRaw === false) {
                    $manifestReadError = 'تعذر قراءة ملف BACKUP_MANIFEST.json.';
                } else {
                    $decoded = json_decode($manifestRaw, true);

                    if (!is_array($decoded)) {
                        $manifestReadError = 'ملف BACKUP_MANIFEST.json غير صالح.';
                    } else {
                        $manifest = $decoded;
                    }
                }
            }

            foreach ($directoryStats as $directory => &$stats) {
                $prefix = $directory . '/';

                if ($name === $directory . '/' || str_starts_with($name, $prefix)) {
                    $stats['present'] = true;

                    if (!$isDirectory && str_starts_with($name, $prefix)) {
                        $stats['file_count']++;
                        $stats['total_bytes'] += $size;
                    }

                    break;
                }
            }
            unset($stats);

            if (count($entries) < 300) {
                $entries[] = [
                    'name' => $name,
                    'size' => $this->formatBytes($size),
                    'raw_size' => $size,
                ];
            }
        }

        $totalZipEntries = $zip->numFiles;
        $zip->close();

        $databaseTables = is_string($sqlContent)
            ? $this->extractCreateTableNames($sqlContent, $this->extractDumpDriver($sqlContent) ?: DB::connection()->getDriverName())
            : [];

        $attachmentFilesCount = array_sum(array_column($directoryStats, 'file_count'));
        $attachmentTotalBytes = array_sum(array_column($directoryStats, 'total_bytes'));
        $presentDirectoryCount = count(array_filter(
            $directoryStats,
            fn (array $stats): bool => (bool) $stats['present']
        ));

        $inferredType = $this->inferBackupType($fileName, count($sqlFiles), $attachmentFilesCount);
        $warnings = [];
        $criticalWarnings = [];

        if ($manifestReadError !== null) {
            $criticalWarnings[] = $manifestReadError;
        }

        if ($manifest === null) {
            $warnings[] = 'هذه نسخة قديمة لا تحتوي على ملف تحقق Manifest؛ يمكن فحص محتوياتها، لكن لا يمكن إثبات اكتمالها آليًا.';

            if ($inferredType === 'نسخة كاملة') {
                $criticalWarnings[] = 'الاستعادة الكاملة معطلة لهذه النسخة القديمة لأنها لا تحتوي على Manifest يثبت اكتمال قاعدة البيانات وجميع مجلدات المرفقات.';
            }
        } else {
            $formatVersion = (int) ($manifest['format_version'] ?? 0);

            if ($formatVersion < 2) {
                $criticalWarnings[] = 'إصدار Manifest غير مدعوم أو أقدم من الإصدار المطلوب.';
            }

            $expectedDirectories = (array) ($manifest['storage_directories'] ?? []);

            foreach ($expectedDirectories as $directory => $expected) {
                $actual = $directoryStats[$directory] ?? [
                    'present' => false,
                    'file_count' => 0,
                    'total_bytes' => 0,
                ];

                $expectedCount = (int) ($expected['file_count'] ?? 0);
                $expectedBytes = (int) ($expected['total_bytes'] ?? 0);

                if (!$actual['present']) {
                    $criticalWarnings[] = 'مجلد المرفقات مفقود من ZIP: ' . $directory;
                    continue;
                }

                if ((int) $actual['file_count'] !== $expectedCount) {
                    $criticalWarnings[] = 'عدد الملفات داخل ' . $directory
                        . ' لا يطابق Manifest: المتوقع ' . $expectedCount
                        . ' والموجود ' . (int) $actual['file_count'] . '.';
                }

                if ((int) $actual['total_bytes'] !== $expectedBytes) {
                    $criticalWarnings[] = 'حجم ملفات ' . $directory
                        . ' لا يطابق Manifest.';
                }
            }

            $expectedTables = array_values(array_map(
                'strval',
                (array) ($manifest['database']['tables'] ?? [])
            ));

            if (!empty($expectedTables)) {
                $missingSqlTables = array_values(array_diff($expectedTables, $databaseTables));

                if (!empty($missingSqlTables)) {
                    $criticalWarnings[] = 'ملف SQL لا يحتوي على جميع الجداول المسجلة في Manifest: '
                        . implode(', ', $missingSqlTables);
                }
            }

            $expectedSqlBytes = (int) ($manifest['database']['sql_bytes'] ?? 0);

            if ($expectedSqlBytes > 0 && (!is_string($sqlContent) || strlen($sqlContent) !== $expectedSqlBytes)) {
                $criticalWarnings[] = 'حجم ملف SQL لا يطابق Manifest.';
            }
        }

        if (str_contains($inferredType, 'قاعدة بيانات') && count($sqlFiles) === 0) {
            $criticalWarnings[] = 'لم يتم العثور على ملف SQL داخل النسخة.';
        }

        if (str_contains($inferredType, 'ملفات') && $presentDirectoryCount === 0) {
            $criticalWarnings[] = 'لم يتم العثور على أي مجلد مرفقات مدعوم داخل النسخة.';
        }

        if ($inferredType === 'نسخة كاملة') {
            if (count($sqlFiles) === 0) {
                $criticalWarnings[] = 'النسخة الكاملة لا تحتوي على ملف قاعدة البيانات SQL.';
            }

            if ($presentDirectoryCount === 0) {
                $criticalWarnings[] = 'النسخة الكاملة لا تحتوي على مجلدات المرفقات.';
            }
        }

        if ($manifest === null && isset($directoryStats['documents']) && $directoryStats['documents']['present']) {
            $otherModernFolders = array_filter(
                ['Books', 'memos', 'circulars', 'misc-books'],
                fn (string $directory): bool => (bool) ($directoryStats[$directory]['present'] ?? false)
            );

            if (empty($otherModernFolders)) {
                $warnings[] = 'تحتوي النسخة على documents فقط ولا تشمل مجلدات Books والمذكرات والتعاميم والكتب المتفرقة.';
            }
        }

        $criticalWarnings = array_values(array_unique($criticalWarnings));
        $warnings = array_values(array_unique($warnings));
        $canRestoreFiles = $presentDirectoryCount > 0;
        $canRestoreFull = $manifest !== null
            && empty($criticalWarnings)
            && count($sqlFiles) > 0
            && $presentDirectoryCount > 0;

        return [
            'fileName' => $fileName,
            'fileSize' => $this->formatBytes((int) File::size($path)),
            'createdAt' => date('Y-m-d H:i:s', File::lastModified($path)),
            'inferredType' => $inferredType,
            'entries' => $entries,
            'totalEntries' => count($entries),
            'totalZipEntries' => $totalZipEntries,
            'totalUncompressedSize' => $this->formatBytes($totalUncompressedSize),
            'sqlFiles' => $sqlFiles,
            'databaseTables' => $databaseTables,
            'databaseTableCount' => count($databaseTables),
            'documentFilesCount' => $attachmentFilesCount,
            'attachmentFilesCount' => $attachmentFilesCount,
            'attachmentTotalSize' => $this->formatBytes($attachmentTotalBytes),
            'attachmentDirectoryStats' => array_values($directoryStats),
            'readmeContent' => $readmeContent,
            'manifest' => $manifest,
            'manifestPresent' => $manifest !== null,
            'warnings' => $warnings,
            'criticalWarnings' => $criticalWarnings,
            'canRestoreFiles' => $canRestoreFiles,
            'canRestoreFull' => $canRestoreFull,
            'integrityStatus' => empty($criticalWarnings)
                ? ($manifest !== null ? 'تم التحقق من سلامة النسخة' : 'نسخة قديمة غير موثقة')
                : 'فشل التحقق من اكتمال النسخة',
        ];
    }

    private function restoreDatabaseFromZip(string $fileName): void
    {
        $path = $this->absoluteBackupPath($fileName);
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('تعذر فتح ملف النسخة الاحتياطية.');
        }

        $sqlFile = $this->firstSqlFileInZip($zip);
        if ($sqlFile === null) {
            $zip->close();
            throw new \RuntimeException('لا يوجد ملف SQL داخل هذه النسخة.');
        }

        $sql = $zip->getFromName($sqlFile);
        $zip->close();

        if ($sql === false || trim($sql) === '') {
            throw new \RuntimeException('ملف SQL داخل النسخة فارغ أو غير قابل للقراءة.');
        }

        $backupDriver = $this->extractDumpDriver($sql);
        $currentDriver = DB::connection()->getDriverName();

        if ($backupDriver !== null && $backupDriver !== $currentDriver) {
            throw new \RuntimeException('نوع قاعدة بيانات النسخة (' . $backupDriver . ') لا يطابق قاعدة البيانات الحالية (' . $currentDriver . ').');
        }

        $this->assertRestorableDatabaseDump($sql, $currentDriver);
        $this->assertArchiveSchemaReady($currentDriver);

        // استعادة بيانات الأرشيف فقط بدون حذف أو إعادة إنشاء جداول النظام.
        // هذا يحافظ على users / sessions / cache / jobs / migrations كما هي.
        $this->restoreArchiveDataOnly($sql, $currentDriver);
    }

    private function restoreDocumentsFromZip(string $fileName): void
    {
        $path = $this->absoluteBackupPath($fileName);
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new \RuntimeException('تعذر فتح ملف النسخة الاحتياطية.');
        }

        $supportedDirectories = array_keys($this->attachmentStorageDirectories());
        $entriesByDirectory = [];

        foreach ($supportedDirectories as $directory) {
            $entriesByDirectory[$directory] = [];
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if (!$stat) {
                continue;
            }

            $name = str_replace('\\', '/', (string) $stat['name']);

            foreach ($supportedDirectories as $directory) {
                $prefix = $directory . '/';

                if ($name === $directory . '/' || str_starts_with($name, $prefix)) {
                    $entriesByDirectory[$directory][] = [
                        'index' => $i,
                        'name' => $name,
                        'size' => (int) ($stat['size'] ?? 0),
                    ];
                    break;
                }
            }
        }

        $presentDirectories = array_filter(
            $entriesByDirectory,
            fn (array $entries): bool => !empty($entries)
        );

        if (empty($presentDirectories)) {
            $zip->close();
            throw new \RuntimeException('لا توجد مجلدات مرفقات مدعومة داخل هذه النسخة.');
        }

        $manifest = $this->readManifestFromZip($zip);

        foreach ($presentDirectories as $directory => $entries) {
            $targetRoot = $this->attachmentStorageDirectories()[$directory];

            if (File::exists($targetRoot)) {
                File::deleteDirectory($targetRoot);
            }

            File::ensureDirectoryExists($targetRoot);

            foreach ($entries as $entry) {
                $zipName = $entry['name'];
                $relative = trim(substr($zipName, strlen($directory . '/')), '/');

                if ($relative === '') {
                    continue;
                }

                if ($this->isUnsafeZipPath($relative)) {
                    throw new \RuntimeException('تم رفض مسار غير آمن داخل ZIP: ' . $zipName);
                }

                $targetPath = $targetRoot . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $relative);

                if (str_ends_with($zipName, '/')) {
                    File::ensureDirectoryExists($targetPath);
                    continue;
                }

                File::ensureDirectoryExists(dirname($targetPath));

                $readStream = $zip->getStream($zipName);

                if ($readStream === false) {
                    throw new \RuntimeException('تعذر قراءة الملف من ZIP: ' . $zipName);
                }

                $writeStream = fopen($targetPath, 'wb');

                if ($writeStream === false) {
                    fclose($readStream);
                    throw new \RuntimeException('تعذر إنشاء الملف أثناء الاستعادة: ' . $targetPath);
                }

                $copiedBytes = stream_copy_to_stream($readStream, $writeStream);
                fclose($readStream);
                fclose($writeStream);

                if ($copiedBytes === false || (int) $copiedBytes !== (int) $entry['size']) {
                    throw new \RuntimeException('لم يكتمل نسخ الملف أثناء الاستعادة: ' . $zipName);
                }
            }

            $actual = $this->directoryStats($targetRoot);
            $expected = $manifest['storage_directories'][$directory] ?? null;

            if (is_array($expected)) {
                if ((int) $actual['file_count'] !== (int) ($expected['file_count'] ?? -1)) {
                    throw new \RuntimeException('عدد الملفات المستعادة لا يطابق Manifest داخل: ' . $directory);
                }

                if ((int) $actual['total_bytes'] !== (int) ($expected['total_bytes'] ?? -1)) {
                    throw new \RuntimeException('حجم الملفات المستعادة لا يطابق Manifest داخل: ' . $directory);
                }
            }
        }

        $zip->close();
    }

    private function runSqlDump(string $sql, string $driver): void
    {
        // لم تعد الاستعادة اليومية تعيد إنشاء الجداول أو تحذف جداول النظام.
        // نستخدم هذه الدالة كغلاف آمن لاستعادة بيانات الأرشيف فقط.
        $this->assertRestorableDatabaseDump($sql, $driver);
        $this->assertArchiveSchemaReady($driver);
        $this->restoreArchiveDataOnly($sql, $driver);
    }

    private function shouldSkipRestoreStatement(string $statement): bool
    {
        if ($statement === '') {
            return true;
        }

        $upper = strtoupper($statement);

        return str_starts_with($upper, 'SET FOREIGN_KEY_CHECKS')
            || str_starts_with($upper, 'PRAGMA FOREIGN_KEYS')
            || str_starts_with($upper, 'START TRANSACTION')
            || str_starts_with($upper, 'COMMIT');
    }

    private function assertRestorableDatabaseDump(string $sql, string $driver): void
    {
        $missingTables = [];

        foreach ($this->requiredTablesForSafeRestore($driver) as $table) {
            if (!$this->dumpContainsCreateTable($sql, $table, $driver)) {
                $missingTables[] = $table;
            }
        }

        if (!empty($missingTables)) {
            throw new \RuntimeException(
                'ملف النسخة الاحتياطية غير مكتمل ولا يمكن استعادته بأمان. الجداول الناقصة: '
                . implode(', ', $missingTables)
                . '. لا تستخدم النسخ التي أُنشئت بعد فشل استعادة سابق. استخدم نسخة كاملة سليمة أو نسخة pre-restore.'
            );
        }
    }

    private function assertCurrentDatabaseReadyForBackup(string $driver): void
    {
        $existingTables = $this->currentDatabaseTables($driver);
        $missingTables = [];

        foreach ($this->requiredTablesForSafeRestore($driver) as $table) {
            if (!in_array($table, $existingTables, true)) {
                $missingTables[] = $table;
            }
        }

        if (!empty($missingTables)) {
            throw new \RuntimeException(
                'لا يمكن إنشاء نسخة احتياطية لأن قاعدة البيانات الحالية غير مكتملة. الجداول الناقصة: '
                . implode(', ', $missingTables)
                . '. نفّذ php artisan migrate أو استعد نسخة سليمة أولاً.'
            );
        }
    }

    private function requiredTablesForSafeRestore(string $driver): array
    {
        return [
            'departments',
            'document_types',
            'settings',
            'reference_counters',
            'documents',
            'document_attachments',
            'activity_logs',
        ];
    }

    private function archiveDataTables(): array
    {
        $driver = DB::connection()->getDriverName();

        return array_values(array_filter(
            $this->currentDatabaseTables($driver),
            fn (string $table): bool => !in_array($table, $this->protectedDatabaseTables(), true)
        ));
    }

    private function archiveDataDeleteOrder(): array
    {
        return array_reverse($this->sortTablesForDump($this->archiveDataTables()));
    }

    private function assertArchiveSchemaReady(string $driver): void
    {
        $existingTables = $this->currentDatabaseTables($driver);
        $missingTables = [];

        foreach ($this->requiredTablesForSafeRestore($driver) as $table) {
            if (!in_array($table, $existingTables, true)) {
                $missingTables[] = $table;
            }
        }

        if (!empty($missingTables)) {
            throw new \RuntimeException(
                'قاعدة البيانات الحالية غير مكتملة ولا يمكن الاستعادة فوقها بأمان. الجداول الناقصة: '
                . implode(', ', $missingTables)
                . '. نفّذ php artisan migrate --force أولاً.'
            );
        }
    }

    private function restoreArchiveDataOnly(string $sql, string $driver): void
    {
        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new \RuntimeException('نوع قاعدة البيانات غير مدعوم في الاستعادة: ' . $driver);
        }

        $dumpTables = $this->extractCreateTableNames($sql, $driver);
        $restoreTables = array_values(array_filter(
            $dumpTables,
            fn (string $table): bool => !in_array($table, $this->protectedDatabaseTables(), true)
        ));

        if (empty($restoreTables)) {
            throw new \RuntimeException('لا تحتوي النسخة على جداول بيانات أرشيف قابلة للاستعادة.');
        }

        $existingTables = $this->currentDatabaseTables($driver);
        $missingCurrentTables = array_values(array_diff($restoreTables, $existingTables));

        if (!empty($missingCurrentTables)) {
            throw new \RuntimeException(
                'قاعدة البيانات الحالية لا تحتوي على بعض جداول النسخة: '
                . implode(', ', $missingCurrentTables)
                . '. نفّذ جميع Migrations قبل الاستعادة.'
            );
        }

        $statements = $this->splitSqlStatements($sql);
        $insertStatements = [];

        foreach ($statements as $statement) {
            $statement = $this->removeLeadingSqlComments(trim($statement));

            if ($statement === '') {
                continue;
            }

            $table = $this->extractInsertTableName($statement, $driver);

            if ($table !== null && in_array($table, $restoreTables, true)) {
                $insertStatements[] = $statement;
            }
        }

        DB::beginTransaction();

        try {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            }

            foreach (array_reverse($restoreTables) as $table) {
                DB::table($table)->delete();
            }

            foreach ($insertStatements as $statement) {
                DB::unprepared($statement);
            }

            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            try {
                if ($driver === 'mysql') {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                } elseif ($driver === 'sqlite') {
                    DB::statement('PRAGMA foreign_keys = ON');
                }
            } catch (Throwable) {
                // تجاهل أي خطأ إضافي أثناء إعادة تفعيل فحص العلاقات.
            }

            throw $e;
        }
    }

    private function extractInsertTableName(string $statement, string $driver): ?string
    {
        if ($driver === 'mysql') {
            if (preg_match('/^INSERT\s+INTO\s+`([^`]+)`/i', $statement, $matches)) {
                return $matches[1];
            }
        }

        if ($driver === 'sqlite') {
            if (preg_match('/^INSERT\s+INTO\s+"([^"]+)"/i', $statement, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function dumpContainsCreateTable(string $sql, string $table, string $driver): bool
    {
        $quoted = preg_quote($table, '/');

        if ($driver === 'mysql') {
            return preg_match('/CREATE\s+TABLE\s+`' . $quoted . '`/i', $sql) === 1
                || preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`' . $quoted . '`/i', $sql) === 1;
        }

        if ($driver === 'sqlite') {
            return preg_match('/CREATE\s+TABLE\s+"' . $quoted . '"/i', $sql) === 1
                || preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?"' . $quoted . '"/i', $sql) === 1;
        }

        return false;
    }

    private function currentDatabaseTables(string $driver): array
    {
        if ($driver === 'mysql') {
            return collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
                ->map(function ($row) {
                    $values = array_values((array) $row);

                    return $values[0] ?? null;
                })
                ->filter()
                ->map(fn ($table) => (string) $table)
                ->values()
                ->all();
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name')
                ->filter()
                ->map(fn ($table) => (string) $table)
                ->values()
                ->all();
        }

        return [];
    }

    private function removeLeadingSqlComments(string $statement): string
    {
        $lines = preg_split('/\R/', $statement) ?: [];
        $cleanLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }

            $cleanLines[] = $line;
        }

        return trim(implode(PHP_EOL, $cleanLines));
    }

    private function dropExistingTablesBeforeRestore(string $driver): void
    {
        if ($driver === 'sqlite') {
            $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name')
                ->filter()
                ->values();

            foreach ($tables as $table) {
                $escapedTable = str_replace('"', '""', (string) $table);
                DB::unprepared('DROP TABLE IF EXISTS "' . $escapedTable . '"');
            }

            return;
        }

        if ($driver === 'mysql') {
            $database = (string) config('database.connections.' . config('database.default') . '.database');
            $tables = collect(DB::select('SHOW TABLES'))
                ->map(function ($row) use ($database) {
                    $key = 'Tables_in_' . $database;
                    return $row->{$key} ?? array_values((array) $row)[0] ?? null;
                })
                ->filter()
                ->values();

            foreach ($tables as $table) {
                $escapedTable = str_replace('`', '``', (string) $table);
                DB::unprepared('DROP TABLE IF EXISTS `' . $escapedTable . '`');
            }
        }
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = null;
        $escaped = false;
        $lineComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                $buffer .= $char;
                if ($char === "\n") {
                    $lineComment = false;
                }
                continue;
            }

            if ($quote === null && $char === '-' && $next === '-') {
                $lineComment = true;
                $buffer .= $char;
                continue;
            }

            if ($quote !== null) {
                $buffer .= $char;

                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statements[] = trim($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }

    private function firstSqlFileInZip(ZipArchive $zip): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) {
                continue;
            }

            $name = str_replace('\\', '/', (string) $stat['name']);
            if (str_ends_with(strtolower($name), '.sql')) {
                return $name;
            }
        }

        return null;
    }

    private function extractDumpDriver(string $sql): ?string
    {
        if (preg_match('/^--\s*Driver:\s*([a-zA-Z0-9_]+)/m', $sql, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private function isUnsafeZipPath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        return str_contains($path, '../')
            || str_starts_with($path, '../')
            || str_starts_with($path, '/')
            || preg_match('/^[a-zA-Z]:\//', $path) === 1;
    }

    private function createPreRestoreDatabaseBackup(): void
    {
        $fileName = 'pre-restore-database-' . now()->format('Ymd-His') . '.zip';
        $zipPath = $this->absoluteBackupPath($fileName);
        File::ensureDirectoryExists(dirname($zipPath));

        $sql = $this->buildDatabaseDump();
        $manifest = $this->buildBackupManifest('pre-restore-database', $sql, false);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('تعذر إنشاء نسخة أمان من قاعدة البيانات قبل الاستعادة.');
        }

        $driver = config('database.default');
        $zip->addFromString(
            'database/database-' . $driver . '-' . now()->format('Ymd-His') . '.sql',
            $sql
        );
        $zip->addFromString(
            'BACKUP_MANIFEST.json',
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
        $zip->addFromString('README.txt', $this->backupReadme('نسخة أمان كاملة لقاعدة البيانات قبل الاستعادة'));

        if (!$zip->close()) {
            throw new \RuntimeException('تعذر إغلاق نسخة أمان قاعدة البيانات.');
        }

        $this->verifyCreatedBackup($zipPath, $manifest, true);
    }

    private function createPreRestoreFilesBackup(): void
    {
        $fileName = 'pre-restore-files-' . now()->format('Ymd-His') . '.zip';
        $zipPath = $this->absoluteBackupPath($fileName);
        File::ensureDirectoryExists(dirname($zipPath));

        $manifest = $this->buildBackupManifest('pre-restore-files', null, true);
        $this->assertBackupCapacity($zipPath, (int) ($manifest['storage_total_bytes'] ?? 0));

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('تعذر إنشاء نسخة أمان من الملفات قبل الاستعادة.');
        }

        $this->addAttachmentDirectoriesToZip($zip);
        $zip->addFromString(
            'BACKUP_MANIFEST.json',
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
        $zip->addFromString('README.txt', $this->backupReadme('نسخة أمان من جميع ملفات المرفقات قبل الاستعادة'));

        if (!$zip->close()) {
            throw new \RuntimeException('تعذر إغلاق نسخة أمان الملفات.');
        }

        $this->verifyCreatedBackup($zipPath, $manifest, false);
    }

    private function buildDatabaseDump(): string
    {
        $driver = DB::connection()->getDriverName();
        $database = config('database.connections.' . config('database.default') . '.database');

        $this->assertCurrentDatabaseReadyForBackup($driver);
        DB::connection()->disableQueryLog();

        $sql = [];
        $sql[] = '-- DocumentArchive Complete Database Backup';
        $sql[] = '-- Driver: ' . $driver;
        $sql[] = '-- Date: ' . now()->format('Y-m-d H:i:s');
        $sql[] = '-- Tables: ' . implode(', ', $this->backupDatabaseTables($driver));
        $sql[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $sql[] = '';

        if ($driver === 'sqlite') {
            return $this->buildSqliteDump($sql);
        }

        return $this->buildMysqlDump($sql, (string) $database);
    }

    private function sortTablesForDump(array $tables): array
    {
        $preferredOrder = [
            'migrations',
            'users',
            'roles',
            'permissions',
            'role_permissions',
            'departments',
            'document_types',
            'settings',
            'reference_counters',
            'memo_counters',
            'circular_counters',
            'misc_book_counters',
            'archive_categories',
            'documents',
            'memos',
            'circulars',
            'misc_books',
            'document_attachments',
            'memo_attachments',
            'circular_attachments',
            'misc_book_attachments',
            'activity_logs',
            'password_reset_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
        ];

        $tables = array_values(array_unique(array_map('strval', $tables)));
        $priority = array_flip($preferredOrder);

        usort($tables, function (string $a, string $b) use ($priority): int {
            $aRank = $priority[$a] ?? 1000;
            $bRank = $priority[$b] ?? 1000;

            if ($aRank === $bRank) {
                return strcmp($a, $b);
            }

            return $aRank <=> $bRank;
        });

        return $tables;
    }

    private function buildMysqlDump(array $sql, string $database): string
    {
        $tables = $this->backupDatabaseTables('mysql');

        foreach ($tables as $table) {
            $escapedTable = str_replace('`', '``', (string) $table);
            $sql[] = '-- Table: ' . $table;
            $sql[] = 'DROP TABLE IF EXISTS `' . $escapedTable . '`;';

            $createRow = DB::select('SHOW CREATE TABLE `' . $escapedTable . '`')[0] ?? null;
            $createArray = (array) $createRow;
            $createSql = $createArray['Create Table'] ?? array_values($createArray)[1] ?? '';

            if ($createSql === '') {
                throw new \RuntimeException('تعذر قراءة بنية الجدول: ' . $table);
            }

            $sql[] = $createSql . ';';

            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use (&$sql, $escapedTable) {
                foreach ($rows as $row) {
                    $data = (array) $row;
                    $columns = array_map(
                        fn ($col) => '`' . str_replace('`', '``', $col) . '`',
                        array_keys($data)
                    );
                    $values = array_map(
                        fn ($value) => $this->sqlValue($value),
                        array_values($data)
                    );
                    $sql[] = 'INSERT INTO `' . $escapedTable . '` ('
                        . implode(', ', $columns)
                        . ') VALUES ('
                        . implode(', ', $values)
                        . ');';
                }
            });

            $sql[] = '';
        }

        $sql[] = 'SET FOREIGN_KEY_CHECKS=1;';

        return implode(PHP_EOL, $sql) . PHP_EOL;
    }

    private function buildSqliteDump(array $sql): string
    {
        $tables = $this->backupDatabaseTables('sqlite');

        foreach ($tables as $table) {
            $escapedTable = str_replace('"', '""', (string) $table);
            $sql[] = '-- Table: ' . $table;

            $createRow = DB::select(
                "SELECT sql FROM sqlite_master WHERE type='table' AND name = ?",
                [$table]
            )[0] ?? null;

            if ($createRow && !empty($createRow->sql)) {
                $sql[] = 'DROP TABLE IF EXISTS "' . $escapedTable . '";';
                $sql[] = $createRow->sql . ';';
            }

            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use (&$sql, $escapedTable) {
                foreach ($rows as $row) {
                    $data = (array) $row;
                    $columns = array_map(
                        fn ($col) => '"' . str_replace('"', '""', $col) . '"',
                        array_keys($data)
                    );
                    $values = array_map(
                        fn ($value) => $this->sqlValue($value),
                        array_values($data)
                    );
                    $sql[] = 'INSERT INTO "' . $escapedTable . '" ('
                        . implode(', ', $columns)
                        . ') VALUES ('
                        . implode(', ', $values)
                        . ');';
                }
            });

            $sql[] = '';
        }

        return implode(PHP_EOL, $sql) . PHP_EOL;
    }

    private function sqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return DB::getPdo()->quote((string) $value);
    }

    private function addDirectoryToZip(ZipArchive $zip, string $sourceDirectory, string $zipDirectory): void
    {
        if (!File::exists($sourceDirectory)) {
            $zip->addEmptyDir($zipDirectory);
            return;
        }

        $sourceDirectory = rtrim(str_replace('\\', '/', $sourceDirectory), '/');
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $realPath = str_replace('\\', '/', $file->getRealPath());
            $relativePath = ltrim(str_replace($sourceDirectory, '', $realPath), '/');
            $zipPath = trim($zipDirectory . '/' . $relativePath, '/');

            if ($file->isDir()) {
                $zip->addEmptyDir($zipPath);
            } else {
                $zip->addFile($file->getRealPath(), $zipPath);
            }
        }
    }


    private function protectedDatabaseTables(): array
    {
        return [
            'migrations',
            'users',
            'roles',
            'permissions',
            'role_permissions',
            'permission_role',
            'role_user',
            'user_roles',
            'user_permissions',
            'model_has_roles',
            'model_has_permissions',
            'role_has_permissions',
            'password_reset_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'personal_access_tokens',
        ];
    }

    private function backupDatabaseTables(string $driver): array
    {
        return $this->sortTablesForDump($this->currentDatabaseTables($driver));
    }

    private function extractCreateTableNames(string $sql, string $driver): array
    {
        $tables = [];

        if ($driver === 'mysql') {
            preg_match_all(
                '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`/i',
                $sql,
                $matches
            );
            $tables = $matches[1] ?? [];
        } elseif ($driver === 'sqlite') {
            preg_match_all(
                '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?"([^"]+)"/i',
                $sql,
                $matches
            );
            $tables = $matches[1] ?? [];
        }

        return array_values(array_unique(array_map('strval', $tables)));
    }

    private function attachmentStorageDirectories(): array
    {
        return [
            'Books' => storage_path('app/private/Books'),
            'documents' => storage_path('app/private/documents'),
            'memos' => storage_path('app/private/memos'),
            'circulars' => storage_path('app/private/circulars'),
            'misc-books' => storage_path('app/private/misc-books'),
        ];
    }

    private function directoryStats(string $directory): array
    {
        if (!File::exists($directory)) {
            return [
                'file_count' => 0,
                'total_bytes' => 0,
            ];
        }

        $fileCount = 0;
        $totalBytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->isLink()) {
                continue;
            }

            $fileCount++;
            $totalBytes += (int) $item->getSize();
        }

        return [
            'file_count' => $fileCount,
            'total_bytes' => $totalBytes,
        ];
    }

    private function attachmentDatabaseCounts(): array
    {
        $tables = [
            'document_attachments',
            'memo_attachments',
            'circular_attachments',
            'misc_book_attachments',
        ];

        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = Schema::hasTable($table)
                ? (int) DB::table($table)->count()
                : 0;
        }

        $counts['total'] = array_sum($counts);

        return $counts;
    }

    private function buildBackupManifest(
        string $backupType,
        ?string $sqlContent,
        bool $includeStorage
    ): array {
        $driver = DB::connection()->getDriverName();
        $storageDirectories = [];
        $storageTotalFiles = 0;
        $storageTotalBytes = 0;

        if ($includeStorage) {
            foreach ($this->attachmentStorageDirectories() as $directory => $sourcePath) {
                $stats = $this->directoryStats($sourcePath);
                $storageDirectories[$directory] = $stats;
                $storageTotalFiles += (int) $stats['file_count'];
                $storageTotalBytes += (int) $stats['total_bytes'];
            }
        }

        $databaseTables = is_string($sqlContent)
            ? $this->extractCreateTableNames($sqlContent, $driver)
            : [];

        return [
            'format_version' => 2,
            'application' => 'DocumentArchive',
            'created_at' => now()->toIso8601String(),
            'backup_type' => $backupType,
            'database' => [
                'driver' => $driver,
                'tables' => $databaseTables,
                'table_count' => count($databaseTables),
                'sql_bytes' => is_string($sqlContent) ? strlen($sqlContent) : 0,
                'attachment_rows' => $this->attachmentDatabaseCounts(),
                'protected_on_web_restore' => $this->protectedDatabaseTables(),
            ],
            'storage_directories' => $storageDirectories,
            'storage_total_files' => $storageTotalFiles,
            'storage_total_bytes' => $storageTotalBytes,
        ];
    }

    private function addAttachmentDirectoriesToZip(ZipArchive $zip): void
    {
        foreach ($this->attachmentStorageDirectories() as $zipDirectory => $sourceDirectory) {
            $this->addDirectoryToZip($zip, $sourceDirectory, $zipDirectory);
        }
    }

    private function assertBackupCapacity(string $zipPath, int $estimatedBytes): void
    {
        $freeBytes = disk_free_space(dirname($zipPath));

        if ($freeBytes === false) {
            return;
        }

        $reserveBytes = max(134217728, (int) ceil($estimatedBytes * 0.10));
        $requiredBytes = $estimatedBytes + $reserveBytes;

        if ($freeBytes < $requiredBytes) {
            throw new \RuntimeException(
                'المساحة الحرة لا تكفي لإنشاء النسخة. المطلوب تقريبًا '
                . $this->formatBytes($requiredBytes)
                . ' والمتاح '
                . $this->formatBytes((int) $freeBytes)
                . '.'
            );
        }
    }

    private function verifyCreatedBackup(
        string $zipPath,
        array $manifest,
        bool $requireSql
    ): void {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('تعذر فتح النسخة بعد إنشائها للتحقق منها.');
        }

        $manifestRaw = $zip->getFromName('BACKUP_MANIFEST.json');

        if ($manifestRaw === false) {
            $zip->close();
            throw new \RuntimeException('لم يتم العثور على Manifest داخل النسخة المنشأة.');
        }

        $decodedManifest = json_decode($manifestRaw, true);

        if (!is_array($decodedManifest) || (int) ($decodedManifest['format_version'] ?? 0) < 2) {
            $zip->close();
            throw new \RuntimeException('Manifest داخل النسخة غير صالح.');
        }

        $sqlCount = 0;
        $sqlBytes = 0;
        $actualDirectories = [];

        foreach (array_keys($this->attachmentStorageDirectories()) as $directory) {
            $actualDirectories[$directory] = [
                'present' => false,
                'file_count' => 0,
                'total_bytes' => 0,
            ];
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if (!$stat) {
                continue;
            }

            $name = str_replace('\\', '/', (string) $stat['name']);
            $size = (int) ($stat['size'] ?? 0);
            $isDirectory = str_ends_with($name, '/');

            if (str_ends_with(strtolower($name), '.sql')) {
                $sqlCount++;
                $sqlBytes += $size;
            }

            foreach ($actualDirectories as $directory => &$stats) {
                $prefix = $directory . '/';

                if ($name === $directory . '/' || str_starts_with($name, $prefix)) {
                    $stats['present'] = true;

                    if (!$isDirectory && str_starts_with($name, $prefix)) {
                        $stats['file_count']++;
                        $stats['total_bytes'] += $size;
                    }

                    break;
                }
            }
            unset($stats);
        }

        $zip->close();

        if ($requireSql && $sqlCount === 0) {
            throw new \RuntimeException('النسخة المنشأة لا تحتوي على ملف SQL.');
        }

        $expectedSqlBytes = (int) ($manifest['database']['sql_bytes'] ?? 0);

        if ($requireSql && $expectedSqlBytes > 0 && $sqlBytes !== $expectedSqlBytes) {
            throw new \RuntimeException('حجم ملف SQL داخل النسخة لا يطابق Manifest.');
        }

        foreach ((array) ($manifest['storage_directories'] ?? []) as $directory => $expected) {
            $actual = $actualDirectories[$directory] ?? null;

            if ($actual === null || !$actual['present']) {
                throw new \RuntimeException('مجلد المرفقات غير موجود داخل النسخة: ' . $directory);
            }

            if ((int) $actual['file_count'] !== (int) ($expected['file_count'] ?? -1)) {
                throw new \RuntimeException('عدد الملفات داخل ZIP لا يطابق المصدر في مجلد: ' . $directory);
            }

            if ((int) $actual['total_bytes'] !== (int) ($expected['total_bytes'] ?? -1)) {
                throw new \RuntimeException('حجم الملفات داخل ZIP لا يطابق المصدر في مجلد: ' . $directory);
            }
        }
    }

    private function readManifestFromZip(ZipArchive $zip): ?array
    {
        $raw = $zip->getFromName('BACKUP_MANIFEST.json');

        if ($raw === false) {
            return null;
        }

        $manifest = json_decode($raw, true);

        return is_array($manifest) ? $manifest : null;
    }

    private function assertFullBackupSafeForRestore(string $fileName): void
    {
        $inspection = $this->inspectBackupFile($fileName);

        if (!($inspection['canRestoreFull'] ?? false)) {
            $reasons = (array) ($inspection['criticalWarnings'] ?? []);

            throw new \RuntimeException(
                'تم منع الاستعادة الكاملة لأن النسخة غير موثقة أو غير مكتملة. '
                . implode(' ', $reasons)
            );
        }
    }

    private function backupReadme(string $type): string
    {
        return implode(PHP_EOL, [
            'DocumentArchive Backup',
            'Type: ' . $type,
            'Created at: ' . now()->format('Y-m-d H:i:s'),
            'Manifest: BACKUP_MANIFEST.json',
            '',
            'مجلدات المرفقات المدعومة:',
            '- Books',
            '- documents',
            '- memos',
            '- circulars',
            '- misc-books',
            '',
            'ملاحظة:',
            'تحتوي النسخ الجديدة على Manifest للتحقق من عدد الملفات وأحجامها وجداول قاعدة البيانات.',
            'الاستعادة الكاملة من واجهة النظام تحافظ على المستخدمين وكلمات المرور وجداول التشغيل.',
            'احتفظ بالنسخة في مكان آمن خارج جهاز التشغيل الأساسي.',
        ]) . PHP_EOL;
    }

    private function inferBackupType(string $fileName, int $sqlCount, int $documentCount): string
    {
        if (str_starts_with($fileName, 'full-backup-')) {
            return 'نسخة كاملة';
        }

        if (str_starts_with($fileName, 'database-backup-')) {
            return 'نسخة قاعدة بيانات';
        }

        if (str_starts_with($fileName, 'files-backup-')) {
            return 'نسخة ملفات';
        }

        if (str_starts_with($fileName, 'pre-restore-database-')) {
            return 'نسخة أمان قاعدة بيانات قبل الاستعادة';
        }

        if (str_starts_with($fileName, 'pre-restore-files-')) {
            return 'نسخة أمان ملفات قبل الاستعادة';
        }

        if ($sqlCount > 0 && $documentCount > 0) {
            return 'نسخة كاملة';
        }

        if ($sqlCount > 0) {
            return 'نسخة قاعدة بيانات';
        }

        if ($documentCount > 0) {
            return 'نسخة ملفات';
        }

        return 'غير محدد';
    }

    private function buildInspectionWarnings(string $type, int $sqlCount, int $documentCount): array
    {
        $warnings = [];

        if (str_contains($type, 'قاعدة بيانات') && $sqlCount === 0) {
            $warnings[] = 'لم يتم العثور على ملف SQL داخل النسخة.';
        }

        if (str_contains($type, 'ملفات') && $documentCount === 0) {
            $warnings[] = 'لم يتم العثور على ملفات مرفقات داخل مجلد documents.';
        }

        if ($type === 'نسخة كاملة') {
            if ($sqlCount === 0) {
                $warnings[] = 'النسخة الكاملة لا تحتوي على ملف قاعدة البيانات SQL.';
            }
            if ($documentCount === 0) {
                $warnings[] = 'النسخة الكاملة لا تحتوي على ملفات مرفقات داخل مجلد documents.';
            }
        }

        return $warnings;
    }

    private function countZipEntries(string $path): int
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return 0;
        }

        $count = $zip->numFiles;
        $zip->close();

        return $count;
    }

    /**
     * يمنع قفل النظام بعد استعادة قاعدة البيانات.
     * بعد أي استعادة لقاعدة البيانات يتم التأكد من وجود حساب المدير الافتراضي
     * بكلمة مرور معروفة، مع عدم التأثير على باقي المستخدمين.
     */
    private function ensureDefaultAdminAfterRestore(): void
    {
        // لم تعد الاستعادة اليومية تغير جدول users نهائياً.
        // تبقى الدالة موجودة للتوافق مع أي استدعاءات قديمة فقط.
    }

    private function logBackupEvent(string $action, string $fileName, string $description): void
    {
        try {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => 'Backup',
                'model_id' => null,
                'description' => $description . ': ' . $fileName,
                'properties' => [
                    'file_name' => $fileName,
                    'backup_path' => storage_path('app/private/' . $this->backupFolder . '/' . basename($fileName)),
                ],
                'ip_address' => request()?->ip(),
                'user_agent' => substr((string) request()?->userAgent(), 0, 1000),
            ]);
        } catch (Throwable) {
            // لا نوقف النسخ الاحتياطي أو الاستعادة إذا تعذر تسجيل النشاط.
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}

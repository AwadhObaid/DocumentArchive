<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        try {
            $fileName = 'database-backup-' . now()->format('Ymd-His') . '.zip';
            $zipPath = $this->absoluteBackupPath($fileName);
            File::ensureDirectoryExists(dirname($zipPath));

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return back()->with('error', 'تعذر إنشاء ملف النسخة الاحتياطية.');
            }

            $driver = config('database.default');
            $zip->addFromString('database-' . $driver . '-' . now()->format('Ymd-His') . '.sql', $this->buildDatabaseDump());
            $zip->addFromString('README.txt', $this->backupReadme('نسخة قاعدة البيانات'));
            $zip->close();

            return back()->with('success', 'تم إنشاء نسخة احتياطية من قاعدة البيانات بنجاح.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'فشل إنشاء نسخة قاعدة البيانات: ' . $e->getMessage());
        }
    }

    public function createFilesBackup(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        try {
            $fileName = 'files-backup-' . now()->format('Ymd-His') . '.zip';
            $zipPath = $this->absoluteBackupPath($fileName);
            File::ensureDirectoryExists(dirname($zipPath));

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return back()->with('error', 'تعذر إنشاء ملف النسخة الاحتياطية.');
            }

            $this->addDirectoryToZip($zip, storage_path('app/private/documents'), 'documents');
            $zip->addFromString('README.txt', $this->backupReadme('نسخة ملفات المرفقات'));
            $zip->close();

            return back()->with('success', 'تم إنشاء نسخة احتياطية من ملفات المرفقات بنجاح.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'فشل إنشاء نسخة الملفات: ' . $e->getMessage());
        }
    }

    public function createFullBackup(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        try {
            $fileName = 'full-backup-' . now()->format('Ymd-His') . '.zip';
            $zipPath = $this->absoluteBackupPath($fileName);
            File::ensureDirectoryExists(dirname($zipPath));

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return back()->with('error', 'تعذر إنشاء ملف النسخة الاحتياطية.');
            }

            $driver = config('database.default');
            $zip->addFromString('database/database-' . $driver . '-' . now()->format('Ymd-His') . '.sql', $this->buildDatabaseDump());
            $this->addDirectoryToZip($zip, storage_path('app/private/documents'), 'documents');
            $zip->addFromString('README.txt', $this->backupReadme('نسخة كاملة: قاعدة البيانات + المرفقات'));
            $zip->close();

            return back()->with('success', 'تم إنشاء نسخة احتياطية كاملة بنجاح.');
        } catch (Throwable $e) {
            report($e);
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
            $this->ensureDefaultAdminAfterRestore();

            return $this->finishRestoreAndLogout(
                $request,
                'تمت استعادة قاعدة البيانات بنجاح. تم إنشاء نسخة أمان قبل الاستعادة، وتم ضبط حساب المدير الافتراضي. الرجاء تسجيل الدخول من جديد.'
            );
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'فشلت استعادة قاعدة البيانات: ' . $e->getMessage());
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
            $this->createPreRestoreDatabaseBackup();
            $this->createPreRestoreFilesBackup();
            $this->restoreDatabaseFromZip($fileName);
            $this->ensureDefaultAdminAfterRestore();
            $this->restoreDocumentsFromZip($fileName);

            return $this->finishRestoreAndLogout(
                $request,
                'تمت استعادة النسخة الكاملة بنجاح. تم إنشاء نسخ أمان قبل الاستعادة، وتم ضبط حساب المدير الافتراضي. الرجاء تسجيل الدخول من جديد.'
            );
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
        $documentFiles = [];
        $readmeContent = null;
        $totalUncompressedSize = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) {
                continue;
            }

            $name = str_replace('\\', '/', (string) $stat['name']);
            $size = (int) ($stat['size'] ?? 0);
            $totalUncompressedSize += $size;

            $lowerName = strtolower($name);
            if (str_ends_with($lowerName, '.sql')) {
                $sqlFiles[] = $name;
            }

            if (str_starts_with($name, 'documents/') && !str_ends_with($name, '/')) {
                $documentFiles[] = $name;
            }

            if ($lowerName === 'readme.txt') {
                $readmeContent = $zip->getFromIndex($i) ?: null;
            }

            if (count($entries) < 300) {
                $entries[] = [
                    'name' => $name,
                    'size' => $this->formatBytes($size),
                    'raw_size' => $size,
                ];
            }
        }

        $zip->close();

        $inferredType = $this->inferBackupType($fileName, count($sqlFiles), count($documentFiles));
        $warnings = $this->buildInspectionWarnings($inferredType, count($sqlFiles), count($documentFiles));

        return [
            'fileName' => $fileName,
            'fileSize' => $this->formatBytes((int) File::size($path)),
            'createdAt' => date('Y-m-d H:i:s', File::lastModified($path)),
            'inferredType' => $inferredType,
            'entries' => $entries,
            'totalEntries' => count($entries),
            'totalZipEntries' => $this->countZipEntries($path),
            'totalUncompressedSize' => $this->formatBytes($totalUncompressedSize),
            'sqlFiles' => $sqlFiles,
            'documentFilesCount' => count($documentFiles),
            'readmeContent' => $readmeContent,
            'warnings' => $warnings,
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

        $this->runSqlDump($sql, $currentDriver);
    }

    private function restoreDocumentsFromZip(string $fileName): void
    {
        $path = $this->absoluteBackupPath($fileName);
        $documentsPath = storage_path('app/private/documents');

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('تعذر فتح ملف النسخة الاحتياطية.');
        }

        $documentEntries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) {
                continue;
            }

            $name = str_replace('\\', '/', (string) $stat['name']);
            if (str_starts_with($name, 'documents/')) {
                $documentEntries[] = ['index' => $i, 'name' => $name];
            }
        }

        if (count($documentEntries) === 0) {
            $zip->close();
            throw new \RuntimeException('لا توجد ملفات مرفقات داخل مجلد documents في هذه النسخة.');
        }

        if (File::exists($documentsPath)) {
            File::deleteDirectory($documentsPath);
        }
        File::ensureDirectoryExists($documentsPath);

        foreach ($documentEntries as $entry) {
            $zipName = $entry['name'];
            $relative = trim(substr($zipName, strlen('documents/')), '/');

            if ($relative === '') {
                continue;
            }

            if ($this->isUnsafeZipPath($relative)) {
                continue;
            }

            $targetPath = $documentsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (str_ends_with($zipName, '/')) {
                File::ensureDirectoryExists($targetPath);
                continue;
            }

            File::ensureDirectoryExists(dirname($targetPath));

            $readStream = $zip->getStream($zipName);
            if ($readStream === false) {
                continue;
            }

            $writeStream = fopen($targetPath, 'wb');
            if ($writeStream === false) {
                fclose($readStream);
                continue;
            }

            stream_copy_to_stream($readStream, $writeStream);
            fclose($readStream);
            fclose($writeStream);
        }

        $zip->close();
    }

    private function runSqlDump(string $sql, string $driver): void
    {
        $statements = $this->splitSqlStatements($sql);

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                // MySQL DDL statements auto-commit, so a normal transaction is not a safe rollback mechanism here.
                // We validate the dump before reaching this point, then replace the existing tables.
                $this->dropExistingTablesBeforeRestore($driver);

                foreach ($statements as $statement) {
                    $statement = $this->removeLeadingSqlComments(trim($statement));

                    if ($this->shouldSkipRestoreStatement($statement)) {
                        continue;
                    }

                    DB::unprepared($statement);
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            return;
        }

        if ($driver === 'sqlite') {
            // Keep SQLite support only as a fallback. Project production/local target is MySQL.
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::beginTransaction();

            try {
                $this->dropExistingTablesBeforeRestore($driver);

                foreach ($statements as $statement) {
                    $statement = $this->removeLeadingSqlComments(trim($statement));

                    if ($this->shouldSkipRestoreStatement($statement)) {
                        continue;
                    }

                    DB::unprepared($statement);
                }

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            } finally {
                DB::statement('PRAGMA foreign_keys = ON');
            }

            return;
        }

        throw new \RuntimeException('نوع قاعدة البيانات غير مدعوم في الاستعادة: ' . $driver);
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
                . '. أعد بناء قاعدة MySQL أولاً ثم أنشئ نسخة جديدة. لا تعتمد على نسخة أُنشئت أثناء فشل استعادة سابق.'
            );
        }
    }

    private function requiredTablesForSafeRestore(string $driver): array
    {
        // Core application tables only.
        // Session/cache/queue tables are not required here because the project runtime is configured as:
        // SESSION_DRIVER=file, CACHE_STORE=file, QUEUE_CONNECTION=sync.
        return [
            'migrations',
            'users',
            'departments',
            'document_types',
            'documents',
            'document_attachments',
            'settings',
            'reference_counters',
            'activity_logs',
        ];
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
            $database = (string) config('database.connections.' . config('database.default') . '.database');

            return collect(DB::select('SHOW TABLES'))
                ->map(function ($row) use ($database) {
                    $key = 'Tables_in_' . $database;
                    return $row->{$key} ?? array_values((array) $row)[0] ?? null;
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

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('تعذر إنشاء نسخة أمان من قاعدة البيانات قبل الاستعادة.');
        }

        $driver = config('database.default');
        $zip->addFromString('database/database-' . $driver . '-' . now()->format('Ymd-His') . '.sql', $this->buildDatabaseDump());
        $zip->addFromString('README.txt', $this->backupReadme('نسخة أمان قبل استعادة قاعدة البيانات'));
        $zip->close();
    }

    private function createPreRestoreFilesBackup(): void
    {
        $fileName = 'pre-restore-files-' . now()->format('Ymd-His') . '.zip';
        $zipPath = $this->absoluteBackupPath($fileName);
        File::ensureDirectoryExists(dirname($zipPath));

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('تعذر إنشاء نسخة أمان من الملفات قبل الاستعادة.');
        }

        $this->addDirectoryToZip($zip, storage_path('app/private/documents'), 'documents');
        $zip->addFromString('README.txt', $this->backupReadme('نسخة أمان قبل استعادة ملفات المرفقات'));
        $zip->close();
    }

    private function buildDatabaseDump(): string
    {
        $driver = DB::connection()->getDriverName();
        $database = config('database.connections.' . config('database.default') . '.database');

        $this->assertCurrentDatabaseReadyForBackup($driver);

        $sql = [];
        $sql[] = '-- DocumentArchive Database Backup';
        $sql[] = '-- Driver: ' . $driver;
        $sql[] = '-- Date: ' . now()->format('Y-m-d H:i:s');
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
            'departments',
            'document_types',
            'settings',
            'reference_counters',
            'documents',
            'document_attachments',
            'activity_logs',
            'password_reset_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
        ];

        $tables = array_values(array_map('strval', $tables));
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
        $tables = collect(DB::select('SHOW TABLES'))
            ->map(function ($row) use ($database) {
                $key = 'Tables_in_' . $database;
                return $row->{$key} ?? array_values((array) $row)[0] ?? null;
            })
            ->filter()
            ->values();

        $tables = $this->sortTablesForDump($tables->all());

        foreach ($tables as $table) {
            $escapedTable = str_replace('`', '``', (string) $table);
            $sql[] = '-- Table: ' . $table;
            $sql[] = 'DROP TABLE IF EXISTS `' . $escapedTable . '`;';

            $createRow = DB::select('SHOW CREATE TABLE `' . $escapedTable . '`')[0] ?? null;
            $createArray = (array) $createRow;
            $createSql = $createArray['Create Table'] ?? array_values($createArray)[1] ?? '';
            $sql[] = $createSql . ';';

            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use (&$sql, $table, $escapedTable) {
                foreach ($rows as $row) {
                    $data = (array) $row;
                    $columns = array_map(fn ($col) => '`' . str_replace('`', '``', $col) . '`', array_keys($data));
                    $values = array_map(fn ($value) => $this->sqlValue($value), array_values($data));
                    $sql[] = 'INSERT INTO `' . $escapedTable . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');';
                }
            });

            $sql[] = '';
        }

        $sql[] = 'SET FOREIGN_KEY_CHECKS=1;';
        return implode(PHP_EOL, $sql) . PHP_EOL;
    }

    private function buildSqliteDump(array $sql): string
    {
        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')
            ->values();

        $tables = $this->sortTablesForDump($tables->all());

        foreach ($tables as $table) {
            $escapedTable = str_replace('"', '""', (string) $table);
            $sql[] = '-- Table: ' . $table;

            $createRow = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$table])[0] ?? null;
            if ($createRow && !empty($createRow->sql)) {
                $sql[] = 'DROP TABLE IF EXISTS "' . $escapedTable . '";';
                $sql[] = $createRow->sql . ';';
            }

            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use (&$sql, $escapedTable) {
                foreach ($rows as $row) {
                    $data = (array) $row;
                    $columns = array_map(fn ($col) => '"' . str_replace('"', '""', $col) . '"', array_keys($data));
                    $values = array_map(fn ($value) => $this->sqlValue($value), array_values($data));
                    $sql[] = 'INSERT INTO "' . $escapedTable . '" (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');';
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

    private function backupReadme(string $type): string
    {
        return implode(PHP_EOL, [
            'DocumentArchive Backup',
            'Type: ' . $type,
            'Created at: ' . now()->format('Y-m-d H:i:s'),
            '',
            'ملاحظة:',
            'هذا الملف يحتوي على نسخة احتياطية من النظام حسب نوع النسخة.',
            'احتفظ به في مكان آمن خارج جهاز التشغيل الأساسي.',
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
     * بعد أي استعادة لقاعدة البيانات يتم ضمان وجود حساب مدير ثابت باللغة الإنجليزية
     * لتجنب مشاكل ترميز الأدوار العربية داخل PowerShell أو النسخ الاحتياطية.
     */
    private function ensureDefaultAdminAfterRestore(): void
    {
        if (!Schema::hasTable('users')) {
            throw new \RuntimeException('تمت قراءة ملف الاستعادة، لكن جدول users غير موجود بعد الاستعادة.');
        }

        $now = now();

        $adminData = [
            'name' => 'Admin',
            'email' => null,
            'phone' => null,
            'role' => 'admin',
            'is_active' => 1,
            'password' => Hash::make('12345678'),
            'remember_token' => null,
            'updated_at' => $now,
        ];

        $existingAdmin = DB::table('users')->where('username', 'admin')->first();

        if ($existingAdmin) {
            DB::table('users')
                ->where('username', 'admin')
                ->update($adminData);

            return;
        }

        $adminData['username'] = 'admin';
        $adminData['created_at'] = $now;

        DB::table('users')->insert($adminData);
    }

    /**
     * بعد استعادة قاعدة البيانات يجب إنهاء الجلسة القديمة لأن جدول المستخدمين تغيّر.
     * هذا يمنع بقاء Session مرتبطة بمستخدم تم استبداله أثناء الاستعادة.
     */
    private function finishRestoreAndLogout(Request $request, string $message): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', $message);
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

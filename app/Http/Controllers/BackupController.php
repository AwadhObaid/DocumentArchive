<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

        return view('backups.inspect', [
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
        ]);
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

    private function buildDatabaseDump(): string
    {
        $driver = DB::connection()->getDriverName();
        $database = config('database.connections.' . config('database.default') . '.database');

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

    private function buildMysqlDump(array $sql, string $database): string
    {
        $tables = collect(DB::select('SHOW TABLES'))
            ->map(function ($row) use ($database) {
                $key = 'Tables_in_' . $database;
                return $row->{$key} ?? array_values((array) $row)[0] ?? null;
            })
            ->filter()
            ->values();

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

        if ($type === 'نسخة قاعدة بيانات' && $sqlCount === 0) {
            $warnings[] = 'لم يتم العثور على ملف SQL داخل النسخة.';
        }

        if ($type === 'نسخة ملفات' && $documentCount === 0) {
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

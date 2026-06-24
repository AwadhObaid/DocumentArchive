<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use ZipArchive;
use Throwable;

class BackupController extends Controller
{
    private string $backupDisk = 'local';
    private string $backupFolder = 'backups';

    private function ensureAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->role === 'admin', 403, 'هذه الصفحة متاحة لمدير النظام فقط.');
    }

    public function index(): View
    {
        $this->ensureAdmin();

        $backups = $this->listBackups();

        return view('backups.index', [
            'backups' => $backups,
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
            $sqlContent = $this->buildDatabaseDump();
            $zip->addFromString('database-' . $driver . '-' . now()->format('Ymd-His') . '.sql', $sqlContent);
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
        $folder = storage_path('app/private/backups');
        File::ensureDirectoryExists($folder);

        $files = collect(File::files($folder))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'zip')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(function ($file) {
                return [
                    'name' => $file->getFilename(),
                    'size' => $this->formatBytes($file->getSize()),
                    'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            })
            ->values()
            ->all();

        return $files;
    }

    private function absoluteBackupPath(string $fileName): string
    {
        return storage_path('app/private/backups/' . basename($fileName));
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
            $sql[] = '-- Table: ' . $table;
            $sql[] = 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`;';

            $createRow = DB::select('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')[0] ?? null;
            $createArray = (array) $createRow;
            $createSql = $createArray['Create Table'] ?? array_values($createArray)[1] ?? '';
            $sql[] = $createSql . ';';

            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use (&$sql, $table) {
                foreach ($rows as $row) {
                    $data = (array) $row;
                    $columns = array_map(fn ($col) => '`' . str_replace('`', '``', $col) . '`', array_keys($data));
                    $values = array_map(fn ($value) => $this->sqlValue($value), array_values($data));
                    $sql[] = 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');';
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
            $sql[] = '-- Table: ' . $table;
            $createRow = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$table])[0] ?? null;
            if ($createRow && !empty($createRow->sql)) {
                $sql[] = 'DROP TABLE IF EXISTS "' . str_replace('"', '""', $table) . '";';
                $sql[] = $createRow->sql . ';';
            }

            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use (&$sql, $table) {
                foreach ($rows as $row) {
                    $data = (array) $row;
                    $columns = array_map(fn ($col) => '"' . str_replace('"', '""', $col) . '"', array_keys($data));
                    $values = array_map(fn ($value) => $this->sqlValue($value), array_values($data));
                    $sql[] = 'INSERT INTO "' . str_replace('"', '""', $table) . '" (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');';
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

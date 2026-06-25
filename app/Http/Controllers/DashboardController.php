<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'documents_total' => $this->countRows('documents'),
            'documents_active' => $this->countDocumentsActive(),
            'documents_today' => $this->countDocumentsToday(),
            'documents_year' => $this->countDocumentsCurrentYear(),
            'attachments_total' => $this->countRows('document_attachments'),
            'departments_total' => $this->countRows('departments'),
            'document_types_total' => $this->countRows('document_types'),
            'activities_total' => $this->countRows('activity_logs'),
        ];

        $latestDocuments = $this->latestDocuments();
        $latestActivities = $this->latestActivities();
        $latestBackup = $this->latestBackup();
        $healthSummary = $this->healthSummary();

        return view('dashboard.index', compact(
            'stats',
            'latestDocuments',
            'latestActivities',
            'latestBackup',
            'healthSummary'
        ));
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return $this->tableExists($table) && Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function countRows(string $table): int
    {
        try {
            if (!$this->tableExists($table)) {
                return 0;
            }

            return (int) DB::table($table)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countDocumentsActive(): int
    {
        try {
            if (!$this->tableExists('documents')) {
                return 0;
            }

            $query = DB::table('documents');

            if ($this->columnExists('documents', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countDocumentsToday(): int
    {
        try {
            if (!$this->tableExists('documents')) {
                return 0;
            }

            $query = DB::table('documents');

            if ($this->columnExists('documents', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            if ($this->columnExists('documents', 'created_at')) {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($this->columnExists('documents', 'reference_date')) {
                $query->whereDate('reference_date', Carbon::today());
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countDocumentsCurrentYear(): int
    {
        try {
            if (!$this->tableExists('documents')) {
                return 0;
            }

            $query = DB::table('documents');

            if ($this->columnExists('documents', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            if ($this->columnExists('documents', 'reference_year')) {
                $query->where('reference_year', (int) date('Y'));
            } elseif ($this->columnExists('documents', 'reference_date')) {
                $query->whereYear('reference_date', (int) date('Y'));
            } elseif ($this->columnExists('documents', 'created_at')) {
                $query->whereYear('created_at', (int) date('Y'));
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function latestDocuments(): array
    {
        try {
            if (!$this->tableExists('documents')) {
                return [];
            }

            $select = ['documents.id'];

            foreach (['reference_number', 'reference_date', 'subject', 'main_policy_number', 'sub_policy_number', 'created_at'] as $column) {
                if ($this->columnExists('documents', $column)) {
                    $select[] = 'documents.' . $column;
                }
            }

            $query = DB::table('documents')->select($select);

            if ($this->columnExists('documents', 'deleted_at')) {
                $query->whereNull('documents.deleted_at');
            }

            if ($this->columnExists('documents', 'created_at')) {
                $query->orderByDesc('documents.created_at');
            } else {
                $query->orderByDesc('documents.id');
            }

            return $query->limit(8)->get()->map(fn ($row) => (array) $row)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function latestActivities(): array
    {
        try {
            if (!$this->tableExists('activity_logs')) {
                return [];
            }

            $select = ['id'];
            foreach (['action', 'description', 'model_type', 'model_id', 'created_at'] as $column) {
                if ($this->columnExists('activity_logs', $column)) {
                    $select[] = $column;
                }
            }

            $query = DB::table('activity_logs')->select($select);

            if ($this->columnExists('activity_logs', 'created_at')) {
                $query->orderByDesc('created_at');
            } else {
                $query->orderByDesc('id');
            }

            return $query->limit(8)->get()->map(fn ($row) => (array) $row)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function latestBackup(): ?array
    {
        $backupDir = storage_path('app/private/backups');

        if (!is_dir($backupDir)) {
            return null;
        }

        $files = glob($backupDir . DIRECTORY_SEPARATOR . '*.zip') ?: [];
        if (empty($files)) {
            return null;
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $file = $files[0];
        $name = basename($file);

        $type = 'غير محدد';
        if (str_starts_with($name, 'full-backup')) {
            $type = 'نسخة كاملة';
        } elseif (str_starts_with($name, 'database-backup')) {
            $type = 'قاعدة البيانات';
        } elseif (str_starts_with($name, 'files-backup')) {
            $type = 'المرفقات';
        }

        return [
            'name' => $name,
            'type' => $type,
            'size' => $this->formatBytes((int) filesize($file)),
            'created_at' => Carbon::createFromTimestamp(filemtime($file))->format('Y-m-d H:i'),
        ];
    }

    private function healthSummary(): array
    {
        $expectedTables = [
            'users',
            'departments',
            'document_types',
            'documents',
            'document_attachments',
            'settings',
            'reference_counters',
            'activity_logs',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'password_reset_tokens',
            'migrations',
        ];

        $missing = [];
        foreach ($expectedTables as $table) {
            if (!$this->tableExists($table)) {
                $missing[] = $table;
            }
        }

        $warnings = [];
        if (config('app.debug')) {
            $warnings[] = 'APP_DEBUG مفعّل';
        }
        if (!class_exists(\ZipArchive::class)) {
            $warnings[] = 'إضافة ZIP غير مفعلة';
        }

        return [
            'ok' => count($missing) === 0,
            'missing_tables' => $missing,
            'warnings' => $warnings,
            'status_text' => count($missing) === 0 ? 'سليم' : 'يحتاج مراجعة',
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        }

        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        /* DASHBOARD_POLISH_CONTROLLER */
        $stats = [
            'documents_total' => $this->countRows('documents'),
            'documents_active' => $this->countDocumentsActive(),
            'documents_today' => $this->countDocumentsForPeriod('today'),
            'documents_month' => $this->countDocumentsForPeriod('month'),
            'documents_year' => $this->countDocumentsForPeriod('year'),
            'attachments_total' => $this->countRows('document_attachments'),
            'documents_with_attachments' => $this->countDocumentsWithAttachments(),
            'documents_without_attachments' => $this->countDocumentsWithoutAttachments(),
            'documents_trashed' => $this->countDocumentsTrashed(),
            'duplicate_main_policies' => $this->countDuplicatePolicy('main_policy_number'),
            'duplicate_sub_policies' => $this->countDuplicatePolicy('sub_policy_number'),
            'departments_total' => $this->countRows('departments'),
            'document_types_total' => $this->countRows('document_types'),
            'activities_total' => $this->countRows('activity_logs'),

            // DASHBOARD_MODULES_V89: active records only (soft-deleted rows are excluded).
            'memos_total' => $this->countActiveRows('memos'),
            'circulars_total' => $this->countActiveRows('circulars'),
            'misc_books_total' => $this->countActiveRows('misc_books'),
        ];

        $latestDocuments = $this->latestDocuments();
        $latestActivities = $this->latestActivities();
        $latestBackup = $this->latestBackup();
        $healthSummary = $this->healthSummary();
        $dashboardAlerts = $this->dashboardAlerts($latestBackup, $healthSummary, $stats);

        $charts = [
            'documents_by_month' => $this->documentsByMonth(6),
            'documents_by_department' => $this->documentsByLookup('department_id', 'departments', 'الإدارة'),
            'documents_by_type' => $this->documentsByLookup('document_type_id', 'document_types', 'نوع الكتاب'),
        ];

        return view('dashboard.index', compact(
            'stats',
            'latestDocuments',
            'latestActivities',
            'latestBackup',
            'healthSummary',
            'dashboardAlerts',
            'charts'
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

    /**
     * Count visible records in a module table.
     *
     * Tables that support soft deletes are counted without deleted rows.
     * The method remains safe when a module has not been migrated yet.
     */
    private function countActiveRows(string $table): int
    {
        try {
            if (!$this->tableExists($table)) {
                return 0;
            }

            $query = DB::table($table);

            if ($this->columnExists($table, 'deleted_at')) {
                $query->whereNull($table . '.deleted_at');
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function documentsQuery(bool $includeTrashed = false)
    {
        $query = DB::table('documents');

        if (!$includeTrashed && $this->columnExists('documents', 'deleted_at')) {
            $query->whereNull('documents.deleted_at');
        }

        return $query;
    }

    private function documentDateColumn(): ?string
    {
        foreach (['reference_date', 'created_at'] as $column) {
            if ($this->columnExists('documents', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function countDocumentsActive(): int
    {
        try {
            if (!$this->tableExists('documents')) {
                return 0;
            }

            return (int) $this->documentsQuery()->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countDocumentsForPeriod(string $period): int
    {
        try {
            if (!$this->tableExists('documents')) {
                return 0;
            }

            $dateColumn = $this->documentDateColumn();
            $query = $this->documentsQuery();

            if (!$dateColumn) {
                return (int) $query->count();
            }

            if ($period === 'today') {
                $query->whereDate('documents.' . $dateColumn, Carbon::today());
            } elseif ($period === 'month') {
                $query->whereYear('documents.' . $dateColumn, (int) date('Y'))
                    ->whereMonth('documents.' . $dateColumn, (int) date('m'));
            } elseif ($period === 'year') {
                if ($this->columnExists('documents', 'reference_year')) {
                    $query->where('documents.reference_year', (int) date('Y'));
                } else {
                    $query->whereYear('documents.' . $dateColumn, (int) date('Y'));
                }
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countDocumentsWithAttachments(): int
    {
        try {
            if (!$this->tableExists('documents') || !$this->tableExists('document_attachments')) {
                return 0;
            }

            return (int) $this->documentsQuery()
                ->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('document_attachments')
                        ->whereColumn('document_attachments.document_id', 'documents.id');
                })
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countDocumentsWithoutAttachments(): int
    {
        try {
            if (!$this->tableExists('documents')) {
                return 0;
            }

            if (!$this->tableExists('document_attachments')) {
                return (int) $this->documentsQuery()->count();
            }

            return (int) $this->documentsQuery()
                ->whereNotExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('document_attachments')
                        ->whereColumn('document_attachments.document_id', 'documents.id');
                })
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countDocumentsTrashed(): int
    {
        try {
            if (!$this->tableExists('documents') || !$this->columnExists('documents', 'deleted_at')) {
                return 0;
            }

            return (int) DB::table('documents')->whereNotNull('deleted_at')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countDuplicatePolicy(string $column): int
    {
        try {
            if (!$this->tableExists('documents') || !$this->columnExists('documents', $column)) {
                return 0;
            }

            $rows = $this->documentsQuery()
                ->whereNotNull('documents.' . $column)
                ->whereRaw("TRIM(COALESCE(documents.{$column}, '')) <> ''")
                ->selectRaw("REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(documents.{$column}, '')), ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), '') as policy_key, COUNT(*) as total")
                ->groupBy('policy_key')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            return (int) $rows->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function documentsByMonth(int $months = 6): array
    {
        try {
            if (!$this->tableExists('documents')) {
                return $this->emptyMonthRows($months);
            }

            $dateColumn = $this->documentDateColumn();
            if (!$dateColumn) {
                return $this->emptyMonthRows($months);
            }

            $rows = [];
            for ($i = $months - 1; $i >= 0; $i--) {
                $date = Carbon::now()->startOfMonth()->subMonths($i);
                $count = $this->documentsQuery()
                    ->whereYear('documents.' . $dateColumn, $date->year)
                    ->whereMonth('documents.' . $dateColumn, $date->month)
                    ->count();

                $rows[] = [
                    'label' => $this->arabicMonthLabel($date),
                    'count' => (int) $count,
                ];
            }

            return $rows;
        } catch (\Throwable $e) {
            return $this->emptyMonthRows($months);
        }
    }

    private function emptyMonthRows(int $months): array
    {
        $rows = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->startOfMonth()->subMonths($i);
            $rows[] = [
                'label' => $this->arabicMonthLabel($date),
                'count' => 0,
            ];
        }

        return $rows;
    }

    private function arabicMonthLabel(Carbon $date): string
    {
        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        return ($months[(int) $date->month] ?? $date->format('m')) . ' ' . $date->format('Y');
    }

    private function documentsByLookup(string $foreignColumn, string $lookupTable, string $defaultLabel): array
    {
        try {
            if (!$this->tableExists('documents') || !$this->columnExists('documents', $foreignColumn)) {
                return [];
            }

            if ($this->tableExists($lookupTable) && $this->columnExists($lookupTable, 'name')) {
                $rows = $this->documentsQuery()
                    ->leftJoin($lookupTable, 'documents.' . $foreignColumn, '=', $lookupTable . '.id')
                    ->selectRaw('COALESCE(' . $lookupTable . '.name, ?) as label, COUNT(*) as total', ['غير محدد'])
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get();
            } else {
                $rows = $this->documentsQuery()
                    ->selectRaw('COALESCE(CAST(documents.' . $foreignColumn . ' AS CHAR), ?) as label, COUNT(*) as total', [$defaultLabel . ' غير محدد'])
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get();
            }

            return $rows->map(fn ($row) => [
                'label' => (string) ($row->label ?: 'غير محدد'),
                'count' => (int) $row->total,
            ])->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function latestDocuments(): array
    {
        try {
            if (!$this->tableExists('documents')) {
                return [];
            }

            $select = ['documents.id'];
            foreach (['reference_number', 'reference_date', 'title', 'subject', 'main_policy_number', 'sub_policy_number', 'created_at'] as $column) {
                if ($this->columnExists('documents', $column)) {
                    $select[] = 'documents.' . $column;
                }
            }

            if ($this->tableExists('departments') && $this->columnExists('departments', 'name') && $this->columnExists('documents', 'department_id')) {
                $select[] = 'departments.name as department_name';
            }

            if ($this->tableExists('document_types') && $this->columnExists('document_types', 'name') && $this->columnExists('documents', 'document_type_id')) {
                $select[] = 'document_types.name as document_type_name';
            }

            if ($this->tableExists('document_attachments')) {
                $select[] = DB::raw('(select count(*) from document_attachments where document_attachments.document_id = documents.id) as attachments_count');
            } else {
                $select[] = DB::raw('0 as attachments_count');
            }

            $query = DB::table('documents')->select($select);

            if ($this->tableExists('departments') && $this->columnExists('departments', 'name') && $this->columnExists('documents', 'department_id')) {
                $query->leftJoin('departments', 'documents.department_id', '=', 'departments.id');
            }

            if ($this->tableExists('document_types') && $this->columnExists('document_types', 'name') && $this->columnExists('documents', 'document_type_id')) {
                $query->leftJoin('document_types', 'documents.document_type_id', '=', 'document_types.id');
            }

            if ($this->columnExists('documents', 'deleted_at')) {
                $query->whereNull('documents.deleted_at');
            }

            $query->orderByDesc($this->columnExists('documents', 'created_at') ? 'documents.created_at' : 'documents.id');

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

            $select = ['activity_logs.id'];
            foreach (['action', 'description', 'model_type', 'model_id', 'created_at', 'user_id'] as $column) {
                if ($this->columnExists('activity_logs', $column)) {
                    $select[] = 'activity_logs.' . $column;
                }
            }

            if ($this->tableExists('users') && $this->columnExists('activity_logs', 'user_id')) {
                $select[] = $this->columnExists('users', 'name')
                    ? 'users.name as user_name'
                    : DB::raw("'' as user_name");
            } else {
                $select[] = DB::raw("'' as user_name");
            }

            $query = DB::table('activity_logs')->select($select);

            if ($this->tableExists('users') && $this->columnExists('activity_logs', 'user_id')) {
                $query->leftJoin('users', 'activity_logs.user_id', '=', 'users.id');
            }

            $query->orderByDesc($this->columnExists('activity_logs', 'created_at') ? 'activity_logs.created_at' : 'activity_logs.id');

            return $query->limit(8)->get()->map(fn ($row) => (array) $row)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function latestBackup(): ?array
    {
        try {
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
                'timestamp' => filemtime($file),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function healthSummary(): array
    {
        $expectedTables = [
            'users', 'departments', 'document_types', 'documents', 'document_attachments',
            'settings', 'reference_counters', 'activity_logs', 'sessions', 'cache',
            'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'migrations',
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
        if (!is_writable(storage_path('app'))) {
            $warnings[] = 'مسار التخزين غير قابل للكتابة';
        }

        return [
            'ok' => count($missing) === 0 && count($warnings) === 0,
            'missing_tables' => $missing,
            'warnings' => $warnings,
            'status_text' => count($missing) === 0 ? (count($warnings) ? 'مستقر مع تنبيهات' : 'سليم') : 'يحتاج مراجعة',
        ];
    }

    private function dashboardAlerts(?array $latestBackup, array $healthSummary, array $stats): array
    {
        $alerts = [];

        try {
            if (!empty($healthSummary['missing_tables'])) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => '🧱',
                    'title' => 'جداول ناقصة في قاعدة البيانات',
                    'message' => 'يوجد نقص في الجداول الأساسية: ' . implode('، ', $healthSummary['missing_tables']),
                    'url' => Route::has('system-health.index') ? route('system-health.index') : url('/system-health'),
                    'action' => 'فحص النظام',
                ];
            }

            if (!$latestBackup) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => '💾',
                    'title' => 'لا توجد نسخة احتياطية',
                    'message' => 'لم يتم العثور على نسخة احتياطية. يفضّل إنشاء نسخة كاملة الآن.',
                    'url' => Route::has('backups.index') ? route('backups.index') : url('/backups'),
                    'action' => 'فتح النسخ الاحتياطي',
                ];
            } elseif (!empty($latestBackup['timestamp']) && $latestBackup['timestamp'] < now()->subDays(7)->timestamp) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => '⏱️',
                    'title' => 'آخر نسخة احتياطية قديمة',
                    'message' => 'آخر نسخة احتياطية أقدم من 7 أيام. يفضّل إنشاء نسخة حديثة.',
                    'url' => Route::has('backups.index') ? route('backups.index') : url('/backups'),
                    'action' => 'إنشاء نسخة',
                ];
            }

            if (($stats['documents_without_attachments'] ?? 0) > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => '📎',
                    'title' => 'كتب بدون مرفقات',
                    'message' => 'يوجد ' . number_format((int) $stats['documents_without_attachments']) . ' كتاب بدون مرفقات.',
                    'url' => Route::has('documents.index') ? route('documents.index', ['has_attachment' => 'no']) : url('/documents'),
                    'action' => 'عرض الكتب',
                ];
            }

            if (($stats['duplicate_main_policies'] ?? 0) > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => '🔁',
                    'title' => 'تكرار في البوليصة الرئيسية',
                    'message' => 'يوجد ' . number_format((int) $stats['duplicate_main_policies']) . ' رقم بوليصة رئيسية مكرر.',
                    'url' => Route::has('data-quality.index') ? route('data-quality.index') : url('/data-quality'),
                    'action' => 'جودة البيانات',
                ];
            }

            if (($stats['duplicate_sub_policies'] ?? 0) > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => '🔂',
                    'title' => 'تكرار في البوليصة الفرعية',
                    'message' => 'يوجد ' . number_format((int) $stats['duplicate_sub_policies']) . ' رقم بوليصة فرعية مكرر.',
                    'url' => Route::has('data-quality.index') ? route('data-quality.index') : url('/data-quality'),
                    'action' => 'جودة البيانات',
                ];
            }

            if (($stats['documents_trashed'] ?? 0) > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => '🗑️',
                    'title' => 'كتب في سلة المحذوفات',
                    'message' => 'يوجد ' . number_format((int) $stats['documents_trashed']) . ' كتاب في سلة المحذوفات.',
                    'url' => Route::has('documents.trash') ? route('documents.trash') : url('/documents/trash'),
                    'action' => 'فتح السلة',
                ];
            }

            if (config('app.debug')) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => '🛠️',
                    'title' => 'وضع التطوير مفعل',
                    'message' => 'APP_DEBUG=true مناسب أثناء التطوير فقط. عند التشغيل الفعلي اجعله false.',
                    'url' => Route::has('system-health.index') ? route('system-health.index') : url('/system-health'),
                    'action' => 'فحص النظام',
                ];
            }
        } catch (\Throwable $e) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => '⚠️',
                'title' => 'تعذر توليد بعض التنبيهات',
                'message' => 'حدث خطأ أثناء توليد التنبيهات، لكن لوحة التحكم ستستمر بالعمل.',
                'url' => Route::has('system-health.index') ? route('system-health.index') : url('/system-health'),
                'action' => 'فحص النظام',
            ];
        }

        return array_slice($alerts, 0, 8);
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

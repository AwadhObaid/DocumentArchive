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
            'documents_without_attachments' => $this->countDocumentsWithoutAttachments(),
            'documents_trashed' => $this->countDocumentsTrashed(),
            'duplicate_main_policies' => $this->countDuplicatePolicy('main_policy_number'),
            'duplicate_sub_policies' => $this->countDuplicatePolicy('sub_policy_number'),
        ];

        $latestDocuments = $this->latestDocuments();
        $latestActivities = $this->latestActivities();
        $latestBackup = $this->latestBackup();
        $healthSummary = $this->healthSummary();
        $dashboardAlerts = $this->dashboardAlerts($latestBackup, $healthSummary);
        $charts = [
            'documents_by_month' => $this->documentsByMonth(6),
            'documents_by_department' => $this->documentsByLookup('department_id', 'departments', 'الإدارة'),
            'documents_by_type' => $this->documentsByLookup('document_type_id', 'document_types', 'نوع الكتاب'),
        ];

                // [DA-DASHBOARD-OBJECT-COMPAT-START]
        $daObjectify = function ($items) {
            if ($items instanceof \Illuminate\Support\Collection) {
                return $items->map(function ($item) {
                    return is_array($item) ? (object) $item : $item;
                });
            }

            if (is_array($items)) {
                return collect($items)->map(function ($item) {
                    return is_array($item) ? (object) $item : $item;
                });
            }

            return $items;
        };

        foreach ([
            'latestDocuments',
            'recentDocuments',
            'lastDocuments',
            'latestDocs',
            'recentDocs',
            'documents',
            'latestActivityLogs',
            'recentActivityLogs',
            'activityLogs',
            'latestActivities',
            'recentActivities',
            'activities',
        ] as $daVarName) {
            if (isset($$daVarName)) {
                $$daVarName = $daObjectify($$daVarName);
            }
        }
        // [DA-DASHBOARD-OBJECT-COMPAT-END]
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

    private function documentDateColumn(): ?string
    {
        if ($this->columnExists('documents', 'reference_date')) {
            return 'reference_date';
        }

        if ($this->columnExists('documents', 'created_at')) {
            return 'created_at';
        }

        return null;
    }

    private function documentsQuery()
    {
        $query = DB::table('documents');

        if ($this->columnExists('documents', 'deleted_at')) {
            $query->whereNull('documents.deleted_at');
        }

        return $query;
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

    private function countDocumentsToday(): int
    {
        try {
            if (!$this->tableExists('documents')) {
                return 0;
            }

            $dateColumn = $this->documentDateColumn();
            $query = $this->documentsQuery();

            if ($dateColumn) {
                $query->whereDate('documents.' . $dateColumn, Carbon::today());
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

            $query = $this->documentsQuery();

            if ($this->columnExists('documents', 'reference_year')) {
                $query->where('documents.reference_year', (int) date('Y'));
            } elseif ($dateColumn = $this->documentDateColumn()) {
                $query->whereYear('documents.' . $dateColumn, (int) date('Y'));
            }

            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function documentsByMonth(int $months = 6): array
    {
        $rows = [];

        try {
            if (!$this->tableExists('documents')) {
                return $this->emptyMonthRows($months);
            }

            $dateColumn = $this->documentDateColumn();
            if (!$dateColumn) {
                return $this->emptyMonthRows($months);
            }

            for ($i = $months - 1; $i >= 0; $i--) {
                $date = Carbon::now()->startOfMonth()->subMonths($i);
                $query = $this->documentsQuery()
                    ->whereYear('documents.' . $dateColumn, $date->year)
                    ->whereMonth('documents.' . $dateColumn, $date->month);

                $rows[] = [
                    'label' => $this->arabicMonthLabel($date),
                    'count' => (int) $query->count(),
                ];
            }
        } catch (\Throwable $e) {
            return $this->emptyMonthRows($months);
        }

        return $rows;
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
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
        ];

        return ($months[(int) $date->month] ?? $date->format('m')) . ' ' . $date->format('Y');
    }

    private function documentsByLookup(string $foreignColumn, string $lookupTable, string $defaultLabel): array
    {
        try {
            if (!$this->tableExists('documents') || !$this->columnExists('documents', $foreignColumn)) {
                return [];
            }

            $nameColumn = $this->columnExists($lookupTable, 'name') ? 'name' : null;

            if ($this->tableExists($lookupTable) && $nameColumn) {
                $query = $this->documentsQuery()
                    ->leftJoin($lookupTable, 'documents.' . $foreignColumn, '=', $lookupTable . '.id')
                    ->selectRaw('COALESCE(' . $lookupTable . '.' . $nameColumn . ', ?) as label, COUNT(*) as total', ['غير محدد'])
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8);
            } else {
                $query = $this->documentsQuery()
                    ->selectRaw('COALESCE(CAST(documents.' . $foreignColumn . ' AS CHAR), ?) as label, COUNT(*) as total', [$defaultLabel . ' غير محدد'])
                    ->groupBy('label')
                    ->orderByDesc('total')
                    ->limit(8);
            }

            return $query->get()->map(fn ($row) => [
                'label' => (string) ($row->label ?: 'غير محدد'),
                'count' => (int) $row->total,
            ])->all();
        } catch (\Throwable $e) {
            return [];
        }
    }


    private function countDocumentsWithoutAttachments(): int
    {
        try {
            if (!$this->tableExists('documents') || !$this->tableExists('document_attachments')) {
                return 0;
            }

            $query = $this->documentsQuery()
                ->leftJoin('document_attachments', 'documents.id', '=', 'document_attachments.document_id')
                ->whereNull('document_attachments.id');

            return (int) $query->count();
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

            $query = $this->documentsQuery()
                ->whereNotNull('documents.' . $column)
                ->where('documents.' . $column, '<>', '')
                ->select('documents.' . $column)
                ->groupBy('documents.' . $column)
                ->havingRaw('COUNT(*) > 1');

            return (int) count($query->get());
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function dashboardAlerts(?array $latestBackup, array $healthSummary): array
    {
        $alerts = [];

        try {
            if (!empty($healthSummary['missing_tables'])) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => '🧱',
                    'title' => 'جداول ناقصة في قاعدة البيانات',
                    'message' => 'يوجد نقص في الجداول الأساسية: ' . implode('، ', $healthSummary['missing_tables']),
                    'url' => \Illuminate\Support\Facades\Route::has('system-health.index') ? route('system-health.index') : url('/system-health'),
                    'action' => 'فحص النظام',
                ];
            }

            if (!$latestBackup) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => '💾',
                    'title' => 'لا توجد نسخة احتياطية',
                    'message' => 'لم يتم العثور على أي ملف نسخة احتياطية. يفضل إنشاء نسخة كاملة الآن.',
                    'url' => \Illuminate\Support\Facades\Route::has('backups.index') ? route('backups.index') : url('/backups'),
                    'action' => 'فتح النسخ الاحتياطي',
                ];
            } elseif (!empty($latestBackup['timestamp']) && $latestBackup['timestamp'] < now()->subDays(7)->timestamp) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => '⏱️',
                    'title' => 'آخر نسخة احتياطية قديمة',
                    'message' => 'آخر نسخة احتياطية أقدم من 7 أيام. يفضل إنشاء نسخة حديثة.',
                    'url' => \Illuminate\Support\Facades\Route::has('backups.index') ? route('backups.index') : url('/backups'),
                    'action' => 'إنشاء نسخة',
                ];
            }

            $withoutAttachments = $this->countDocumentsWithoutAttachments();
            if ($withoutAttachments > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => '📎',
                    'title' => 'كتب بدون مرفقات',
                    'message' => 'يوجد ' . number_format($withoutAttachments) . ' كتاب/كتب بدون مرفقات. راجعها إذا كان رفع النسخة الممسوحة إلزامياً.',
                    'url' => \Illuminate\Support\Facades\Route::has('documents.index') ? route('documents.index') : url('/documents'),
                    'action' => 'عرض الكتب',
                ];
            }

            $mainDuplicates = $this->countDuplicatePolicy('main_policy_number');
            if ($mainDuplicates > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => '🔁',
                    'title' => 'تكرار في البوليصة الرئيسية',
                    'message' => 'يوجد ' . number_format($mainDuplicates) . ' رقم/أرقام بوليصة رئيسية مكررة.',
                    'url' => \Illuminate\Support\Facades\Route::has('documents.index') ? route('documents.index') : url('/documents'),
                    'action' => 'مراجعة الكتب',
                ];
            }

            $subDuplicates = $this->countDuplicatePolicy('sub_policy_number');
            if ($subDuplicates > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => '🔂',
                    'title' => 'تكرار في البوليصة الفرعية',
                    'message' => 'يوجد ' . number_format($subDuplicates) . ' رقم/أرقام بوليصة فرعية مكررة.',
                    'url' => \Illuminate\Support\Facades\Route::has('documents.index') ? route('documents.index') : url('/documents'),
                    'action' => 'مراجعة الكتب',
                ];
            }

            $trashed = $this->countDocumentsTrashed();
            if ($trashed > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => '🗑️',
                    'title' => 'كتب في سلة المحذوفات',
                    'message' => 'يوجد ' . number_format($trashed) . ' كتاب/كتب في سلة المحذوفات.',
                    'url' => \Illuminate\Support\Facades\Route::has('documents.trash') ? route('documents.trash') : url('/documents/trash'),
                    'action' => 'فتح السلة',
                ];
            }

            if (config('app.debug')) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => '🛠️',
                    'title' => 'وضع التطوير مفعل',
                    'message' => 'APP_DEBUG=true مناسب أثناء التطوير فقط. عند التشغيل الفعلي اجعله false.',
                    'url' => \Illuminate\Support\Facades\Route::has('system-health.index') ? route('system-health.index') : url('/system-health'),
                    'action' => 'فحص النظام',
                ];
            }
        } catch (\Throwable $e) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => '⚠️',
                'title' => 'تعذر توليد بعض التنبيهات',
                'message' => 'حدث خطأ أثناء توليد التنبيهات الإدارية، لكن لوحة التحكم ستستمر بالعمل.',
                'url' => \Illuminate\Support\Facades\Route::has('system-health.index') ? route('system-health.index') : url('/system-health'),
                'action' => 'فحص النظام',
            ];
        }

        return array_slice($alerts, 0, 8);
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
            'timestamp' => filemtime($file),
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
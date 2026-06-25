<?php
/**
 * DocumentArchive - Dashboard Charts Update
 * يضيف رسوماً بيانية آمنة إلى لوحة التحكم بدون CDN أو مكتبات خارجية.
 */

$root = dirname(__DIR__);

function ensure_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function backup_file_if_exists(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $backupDir = dirname($path) . DIRECTORY_SEPARATOR . '_backup_dashboard_charts_' . date('Ymd_His');
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }

    copy($path, $backupDir . DIRECTORY_SEPARATOR . basename($path));
}

function detect_layout(string $root): string
{
    $candidates = [
        $root . '/resources/views/dashboard/index.blade.php',
        $root . '/resources/views/dashboard.blade.php',
        $root . '/resources/views/admin/dashboard.blade.php',
        $root . '/resources/views/home.blade.php',
    ];

    foreach ($candidates as $file) {
        if (!file_exists($file)) {
            continue;
        }

        $content = file_get_contents($file);
        if (preg_match('/@extends\((\'|\")([^\'\"]+)\1\)/u', $content, $m)) {
            return $m[2];
        }
    }

    if (file_exists($root . '/resources/views/layouts/app.blade.php')) {
        return 'layouts.app';
    }

    if (file_exists($root . '/resources/views/layouts/admin.blade.php')) {
        return 'layouts.admin';
    }

    return 'layouts.app';
}

$layout = detect_layout($root);
$controllerPath = $root . '/app/Http/Controllers/DashboardController.php';
$viewDir = $root . '/resources/views/dashboard';
$viewPath = $viewDir . '/index.blade.php';

ensure_dir(dirname($controllerPath));
ensure_dir($viewDir);
backup_file_if_exists($controllerPath);
backup_file_if_exists($viewPath);

$controller = <<<'PHP'
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
PHP;

$view = <<<'BLADE'
@extends('__LAYOUT__')

@section('title', 'لوحة التحكم')

@section('content')
@php
    $user = auth()->user();

    $hasPermission = function (string $permission) use ($user): bool {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if (isset($user->role) && in_array($user->role, ['admin', 'مدير النظام'], true)) {
            return true;
        }

        if (method_exists($user, 'hasPermission')) {
            return (bool) $user->hasPermission($permission);
        }

        $permissions = $user->permissions ?? [];
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        if (is_array($permissions)) {
            return in_array($permission, $permissions, true) || !empty($permissions[$permission] ?? false);
        }

        return true;
    };

    $routeExists = fn (string $name): bool => \Illuminate\Support\Facades\Route::has($name);

    $months = $charts['documents_by_month'] ?? [];
    $maxMonthValue = max(1, ...array_map(fn ($item) => (int) ($item['count'] ?? 0), $months));
    $barWidth = 72;
    $gap = 22;
    $chartHeight = 190;
    $plotWidth = max(1, count($months) * ($barWidth + $gap));

    $departmentRows = $charts['documents_by_department'] ?? [];
    $typeRows = $charts['documents_by_type'] ?? [];
    $maxDepartment = max(1, ...array_map(fn ($item) => (int) ($item['count'] ?? 0), $departmentRows ?: [['count' => 0]]));
    $maxType = max(1, ...array_map(fn ($item) => (int) ($item['count'] ?? 0), $typeRows ?: [['count' => 0]]));
@endphp

<style>
    .da-dashboard {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .da-dashboard * { box-sizing: border-box; }

    .da-page-head {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .da-page-title {
        margin: 0;
        font-size: clamp(26px, 3vw, 38px);
        font-weight: 900;
        letter-spacing: -0.5px;
    }

    .da-page-subtitle {
        margin: 8px 0 0;
        color: #8ea0bd;
        font-size: 14px;
    }

    .da-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .da-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 11px 15px;
        border-radius: 12px;
        text-decoration: none;
        border: 1px solid rgba(148, 163, 184, .24);
        background: rgba(15, 23, 42, .74);
        color: #f8fafc;
        font-weight: 800;
        transition: .18s ease;
    }

    .da-btn:hover { transform: translateY(-1px); color: #fff; }

    .da-btn-primary {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        border-color: rgba(37, 99, 235, .7);
    }

    .da-grid { display: grid; gap: 14px; }
    .da-grid-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .da-grid-main { grid-template-columns: minmax(0, 1.45fr) minmax(320px, .85fr); align-items: start; }
    .da-grid-charts { grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr); align-items: stretch; }

    .da-card {
        background: rgba(15, 23, 42, .72);
        border: 1px solid rgba(148, 163, 184, .20);
        border-radius: 18px;
        padding: 18px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .16);
    }

    .da-stat { position: relative; overflow: hidden; min-height: 118px; }
    .da-stat::after {
        content: '';
        position: absolute;
        inset-inline-start: -38px;
        top: -38px;
        width: 118px;
        height: 118px;
        border-radius: 999px;
        background: rgba(37, 99, 235, .12);
    }

    .da-stat-label { color: #93a4bf; font-weight: 800; font-size: 13px; margin-bottom: 10px; }
    .da-stat-value { color: #fff; font-size: 30px; font-weight: 950; line-height: 1; }
    .da-stat-note { margin-top: 10px; color: #8ea0bd; font-size: 12px; }

    .da-section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 14px;
    }

    .da-section-title h2 { margin: 0; font-size: 18px; font-weight: 900; }
    .da-section-hint { color: #94a3b8; font-size: 12px; font-weight: 750; }

    .da-chart-scroll { overflow-x: auto; padding-bottom: 4px; }
    .da-chart-bars {
        min-width: 560px;
        height: 260px;
        display: flex;
        align-items: flex-end;
        gap: 18px;
        padding: 22px 12px 12px;
        border-radius: 16px;
        background: linear-gradient(180deg, rgba(30,41,59,.42), rgba(15,23,42,.18));
        border: 1px solid rgba(148,163,184,.12);
        position: relative;
    }

    .da-chart-bars::before {
        content: '';
        position: absolute;
        inset: 24px 12px 58px;
        background-image: linear-gradient(to top, rgba(148,163,184,.12) 1px, transparent 1px);
        background-size: 100% 34px;
        pointer-events: none;
        opacity: .75;
    }

    .da-month-bar {
        position: relative;
        z-index: 1;
        flex: 1;
        min-width: 72px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
        height: 220px;
    }

    .da-month-value { color: #e0f2fe; font-weight: 950; font-size: 13px; }
    .da-month-fill {
        width: 46px;
        min-height: 8px;
        border-radius: 999px 999px 10px 10px;
        background: linear-gradient(180deg, #60a5fa, #2563eb);
        box-shadow: 0 10px 24px rgba(37,99,235,.26);
    }
    .da-month-label { color: #93a4bf; font-size: 11px; font-weight: 800; text-align: center; line-height: 1.35; min-height: 32px; }

    .da-mini-charts { display: flex; flex-direction: column; gap: 14px; }
    .da-horizontal-chart { display: flex; flex-direction: column; gap: 11px; }
    .da-bar-row { display: grid; grid-template-columns: minmax(82px, 130px) 1fr auto; align-items: center; gap: 10px; }
    .da-bar-label { color: #e5e7eb; font-size: 12px; font-weight: 850; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .da-bar-track { height: 13px; border-radius: 999px; background: rgba(30,41,59,.84); border: 1px solid rgba(148,163,184,.12); overflow: hidden; }
    .da-bar-fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #38bdf8, #2563eb); min-width: 4px; }
    .da-bar-count { color: #bfdbfe; font-size: 12px; font-weight: 950; min-width: 28px; text-align: left; }

    .da-table-wrap { overflow-x: auto; }
    .da-table { width: 100%; border-collapse: collapse; min-width: 620px; }
    .da-table th, .da-table td { padding: 13px 10px; border-bottom: 1px solid rgba(148, 163, 184, .16); text-align: right; vertical-align: middle; white-space: nowrap; }
    .da-table th { color: #93a4bf; font-size: 12px; font-weight: 900; }
    .da-table td { color: #f8fafc; font-weight: 700; }

    .da-muted { color: #94a3b8; font-weight: 700; }
    .da-empty { padding: 26px; text-align: center; color: #94a3b8; border: 1px dashed rgba(148, 163, 184, .25); border-radius: 14px; font-weight: 800; }

    .da-side-list { display: flex; flex-direction: column; gap: 12px; }
    .da-info-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 13px; border-radius: 14px; background: rgba(30, 41, 59, .52); border: 1px solid rgba(148, 163, 184, .14); }
    .da-info-row strong { color: #fff; }

    .da-badge { display: inline-flex; align-items: center; justify-content: center; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 900; border: 1px solid rgba(148, 163, 184, .24); color: #e5e7eb; }
    .da-badge-ok { color: #bbf7d0; background: rgba(22, 163, 74, .16); border-color: rgba(34, 197, 94, .35); }
    .da-badge-warn { color: #fde68a; background: rgba(245, 158, 11, .15); border-color: rgba(245, 158, 11, .35); }

    .da-alert { padding: 14px 16px; border-radius: 14px; font-weight: 850; border: 1px solid rgba(245, 158, 11, .35); color: #fde68a; background: rgba(245, 158, 11, .12); }

    .da-activity { display: flex; flex-direction: column; gap: 11px; }
    .da-activity-item { padding: 12px; border-radius: 14px; background: rgba(30, 41, 59, .50); border: 1px solid rgba(148, 163, 184, .12); }
    .da-activity-title { color: #fff; font-weight: 900; margin-bottom: 5px; }
    .da-activity-meta { color: #94a3b8; font-size: 12px; font-weight: 700; }

    @media (max-width: 1100px) {
        .da-grid-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .da-grid-main, .da-grid-charts { grid-template-columns: 1fr; }
    }

    @media (max-width: 640px) {
        .da-grid-stats { grid-template-columns: 1fr; }
        .da-page-head { align-items: stretch; }
        .da-actions, .da-actions .da-btn { width: 100%; }
        .da-bar-row { grid-template-columns: 1fr; gap: 6px; }
        .da-bar-count { text-align: right; }
    }
</style>

<div class="da-dashboard" dir="rtl">
    <div class="da-page-head">
        <div>
            <h1 class="da-page-title">لوحة التحكم</h1>
            <p class="da-page-subtitle">ملخص سريع لحالة الأرشيف الإلكتروني والكتب والمرفقات والنسخ الاحتياطي.</p>
        </div>

        <div class="da-actions">
            @if($hasPermission('documents.create') && $routeExists('documents.create'))
                <a class="da-btn da-btn-primary" href="{{ route('documents.create') }}">➕ إضافة كتاب</a>
            @endif
            @if($hasPermission('reports.view') && $routeExists('reports.index'))
                <a class="da-btn" href="{{ route('reports.index') }}">📊 التقارير</a>
            @endif
            @if($hasPermission('backups.view') && $routeExists('backups.index'))
                <a class="da-btn" href="{{ route('backups.index') }}">💾 النسخ الاحتياطي</a>
            @endif
        </div>
    </div>

    @if(!empty($healthSummary['warnings']))
        <div class="da-alert">⚠️ توجد تنبيهات في فحص النظام: {{ implode('، ', $healthSummary['warnings']) }}</div>
    @endif

    <div class="da-grid da-grid-stats">
        <div class="da-card da-stat">
            <div class="da-stat-label">إجمالي الكتب</div>
            <div class="da-stat-value">{{ number_format($stats['documents_total'] ?? 0) }}</div>
            <div class="da-stat-note">كل الكتب المسجلة في النظام</div>
        </div>
        <div class="da-card da-stat">
            <div class="da-stat-label">الكتب الفعالة</div>
            <div class="da-stat-value">{{ number_format($stats['documents_active'] ?? 0) }}</div>
            <div class="da-stat-note">بدون سلة المحذوفات</div>
        </div>
        <div class="da-card da-stat">
            <div class="da-stat-label">كتب اليوم</div>
            <div class="da-stat-value">{{ number_format($stats['documents_today'] ?? 0) }}</div>
            <div class="da-stat-note">المدخلة خلال اليوم الحالي</div>
        </div>
        <div class="da-card da-stat">
            <div class="da-stat-label">المرفقات</div>
            <div class="da-stat-value">{{ number_format($stats['attachments_total'] ?? 0) }}</div>
            <div class="da-stat-note">ملفات PDF والصور المرفوعة</div>
        </div>
    </div>

    <div class="da-grid da-grid-charts">
        <div class="da-card">
            <div class="da-section-title">
                <h2>حركة الكتب خلال آخر 6 أشهر</h2>
                <span class="da-section-hint">حسب تاريخ الكتاب</span>
            </div>

            @if(!empty($months))
                <div class="da-chart-scroll">
                    <div class="da-chart-bars">
                        @foreach($months as $month)
                            @php
                                $value = (int) ($month['count'] ?? 0);
                                $height = max(8, (int) round(($value / $maxMonthValue) * 170));
                            @endphp
                            <div class="da-month-bar" title="{{ $month['label'] }}: {{ $value }}">
                                <div class="da-month-value">{{ number_format($value) }}</div>
                                <div class="da-month-fill" style="height: {{ $height }}px"></div>
                                <div class="da-month-label">{{ $month['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="da-empty">لا توجد بيانات شهرية متاحة.</div>
            @endif
        </div>

        <div class="da-mini-charts">
            <div class="da-card">
                <div class="da-section-title">
                    <h2>توزيع الكتب حسب الإدارة</h2>
                    <span class="da-section-hint">أعلى الإدارات</span>
                </div>
                @if(!empty($departmentRows))
                    <div class="da-horizontal-chart">
                        @foreach($departmentRows as $row)
                            @php
                                $value = (int) ($row['count'] ?? 0);
                                $width = max(4, (int) round(($value / $maxDepartment) * 100));
                            @endphp
                            <div class="da-bar-row" title="{{ $row['label'] }}: {{ $value }}">
                                <div class="da-bar-label">{{ $row['label'] }}</div>
                                <div class="da-bar-track"><div class="da-bar-fill" style="width: {{ $width }}%"></div></div>
                                <div class="da-bar-count">{{ number_format($value) }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="da-empty">لا توجد بيانات كافية للإدارات.</div>
                @endif
            </div>

            <div class="da-card">
                <div class="da-section-title">
                    <h2>توزيع الكتب حسب النوع</h2>
                    <span class="da-section-hint">أعلى الأنواع</span>
                </div>
                @if(!empty($typeRows))
                    <div class="da-horizontal-chart">
                        @foreach($typeRows as $row)
                            @php
                                $value = (int) ($row['count'] ?? 0);
                                $width = max(4, (int) round(($value / $maxType) * 100));
                            @endphp
                            <div class="da-bar-row" title="{{ $row['label'] }}: {{ $value }}">
                                <div class="da-bar-label">{{ $row['label'] }}</div>
                                <div class="da-bar-track"><div class="da-bar-fill" style="width: {{ $width }}%"></div></div>
                                <div class="da-bar-count">{{ number_format($value) }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="da-empty">لا توجد بيانات كافية لأنواع الكتب.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="da-grid da-grid-main">
        <div class="da-card">
            <div class="da-section-title">
                <h2>آخر الكتب المضافة</h2>
                @if($routeExists('documents.index'))
                    <a class="da-btn" href="{{ route('documents.index') }}">عرض الكل</a>
                @endif
            </div>

            @if(!empty($latestDocuments))
                <div class="da-table-wrap">
                    <table class="da-table">
                        <thead>
                            <tr>
                                <th>رقم الكتاب</th>
                                <th>الموضوع</th>
                                <th>البوليصة الرئيسية</th>
                                <th>البوليصة الفرعية</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($latestDocuments as $document)
                                <tr>
                                    <td>
                                        @if($routeExists('documents.show') && !empty($document['id']))
                                            <a href="{{ route('documents.show', $document['id']) }}" style="color:#bfdbfe;text-decoration:none;">
                                                {{ $document['reference_number'] ?? ('#' . $document['id']) }}
                                            </a>
                                        @else
                                            {{ $document['reference_number'] ?? ('#' . ($document['id'] ?? '')) }}
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($document['subject'] ?? 'بدون موضوع', 42) }}</td>
                                    <td class="da-muted">{{ $document['main_policy_number'] ?? '-' }}</td>
                                    <td class="da-muted">{{ $document['sub_policy_number'] ?? '-' }}</td>
                                    <td class="da-muted">{{ !empty($document['reference_date']) ? \Carbon\Carbon::parse($document['reference_date'])->format('Y-m-d') : (!empty($document['created_at']) ? \Carbon\Carbon::parse($document['created_at'])->format('Y-m-d') : '-') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="da-empty">لا توجد كتب مضافة حتى الآن.</div>
            @endif
        </div>

        <div class="da-side-list">
            <div class="da-card">
                <div class="da-section-title">
                    <h2>حالة النظام</h2>
                    <span class="da-badge {{ ($healthSummary['ok'] ?? false) ? 'da-badge-ok' : 'da-badge-warn' }}">
                        {{ $healthSummary['status_text'] ?? 'غير معروف' }}
                    </span>
                </div>

                <div class="da-side-list">
                    <div class="da-info-row"><span class="da-muted">جداول قاعدة البيانات</span><strong>{{ empty($healthSummary['missing_tables']) ? 'مكتملة' : 'ناقصة' }}</strong></div>
                    <div class="da-info-row"><span class="da-muted">الإدارات</span><strong>{{ number_format($stats['departments_total'] ?? 0) }}</strong></div>
                    <div class="da-info-row"><span class="da-muted">أنواع الكتب</span><strong>{{ number_format($stats['document_types_total'] ?? 0) }}</strong></div>
                    <div class="da-info-row"><span class="da-muted">أنشطة النظام</span><strong>{{ number_format($stats['activities_total'] ?? 0) }}</strong></div>
                </div>
            </div>

            <div class="da-card">
                <div class="da-section-title">
                    <h2>آخر نسخة احتياطية</h2>
                    @if($latestBackup)
                        <span class="da-badge da-badge-ok">موجودة</span>
                    @else
                        <span class="da-badge da-badge-warn">غير موجودة</span>
                    @endif
                </div>

                @if($latestBackup)
                    <div class="da-side-list">
                        <div class="da-info-row"><span class="da-muted">النوع</span><strong>{{ $latestBackup['type'] }}</strong></div>
                        <div class="da-info-row"><span class="da-muted">الحجم</span><strong>{{ $latestBackup['size'] }}</strong></div>
                        <div class="da-info-row"><span class="da-muted">التاريخ</span><strong>{{ $latestBackup['created_at'] }}</strong></div>
                    </div>
                    <div class="da-muted" style="margin-top:12px;font-size:12px;word-break:break-all;">{{ $latestBackup['name'] }}</div>
                @else
                    <div class="da-empty">لم يتم إنشاء نسخة احتياطية بعد.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="da-card">
        <div class="da-section-title">
            <h2>آخر الأنشطة</h2>
            @if($routeExists('activity-logs.index'))
                <a class="da-btn" href="{{ route('activity-logs.index') }}">عرض السجل</a>
            @endif
        </div>

        @if(!empty($latestActivities))
            <div class="da-activity">
                @foreach($latestActivities as $activity)
                    <div class="da-activity-item">
                        <div class="da-activity-title">{{ $activity['description'] ?? $activity['action'] ?? 'نشاط' }}</div>
                        <div class="da-activity-meta">
                            {{ !empty($activity['created_at']) ? \Carbon\Carbon::parse($activity['created_at'])->format('Y-m-d H:i') : '' }}
                            @if(!empty($activity['model_type']))
                                · {{ class_basename($activity['model_type']) }} {{ $activity['model_id'] ?? '' }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="da-empty">لا توجد أنشطة مسجلة حتى الآن.</div>
        @endif
    </div>
</div>
@endsection
BLADE;

$view = str_replace('__LAYOUT__', $layout, $view);

file_put_contents($controllerPath, $controller);
file_put_contents($viewPath, $view);

echo "Dashboard charts update applied successfully.\n";
echo "Detected layout: {$layout}\n";
echo "Updated: app/Http/Controllers/DashboardController.php\n";
echo "Updated: resources/views/dashboard/index.blade.php\n";

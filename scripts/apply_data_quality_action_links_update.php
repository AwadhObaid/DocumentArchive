<?php
/**
 * DocumentArchive - Data Quality Action Links Update
 * يضيف روابط مباشرة وأزرار مراجعة داخل صفحة جودة البيانات.
 * شغّل من جذر مشروع Laravel:
 * php scripts/apply_data_quality_action_links_update.php
 */

$root = dirname(__DIR__);
if (!file_exists($root . '/artisan')) {
    fwrite(STDERR, "ERROR: شغّل هذا السكربت من جذر مشروع Laravel.\n");
    exit(1);
}

function backup_file(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $dir = dirname($path);
    $name = basename($path);
    $backup = $dir . '/' . $name . '.before-data-quality-actions-' . date('Ymd_His') . '.bak';
    copy($path, $backup);
    echo "Backup: {$backup}\n";
}

function write_file(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    backup_file($path);
    file_put_contents($path, $content);
    echo "Written: {$path}\n";
}

$controllerPath = $root . '/app/Http/Controllers/DataQualityController.php';
$viewPath = $root . '/resources/views/data-quality/index.blade.php';
$routesPath = $root . '/routes/web.php';

$controller = <<<'PHP_CONTROLLER'
<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataQualityController extends Controller
{
    public function index()
    {
        $data = [
            'documentsTableExists' => Schema::hasTable('documents'),
            'attachmentsTableExists' => Schema::hasTable('document_attachments'),
            'withoutAttachments' => collect(),
            'duplicateMainPolicies' => collect(),
            'duplicateSubPolicies' => collect(),
            'withoutMainPolicy' => collect(),
            'withoutSubPolicy' => collect(),
            'trashedDocuments' => collect(),
            'summary' => [
                'without_attachments' => 0,
                'duplicate_main_policies' => 0,
                'duplicate_sub_policies' => 0,
                'trashed_documents' => 0,
                'without_main_policy' => 0,
                'without_sub_policy' => 0,
            ],
        ];

        if (! $data['documentsTableExists']) {
            return view('data-quality.index', $data);
        }

        $base = Document::query();

        $data['withoutAttachments'] = $this->safeWithoutAttachments();
        $data['duplicateMainPolicies'] = $this->duplicatePolicies('main_policy_number');
        $data['duplicateSubPolicies'] = $this->duplicatePolicies('sub_policy_number');
        $data['withoutMainPolicy'] = $this->documentsMissingPolicy('main_policy_number');
        $data['withoutSubPolicy'] = $this->documentsMissingPolicy('sub_policy_number');
        $data['trashedDocuments'] = $this->safeTrashedDocuments();

        $data['summary'] = [
            'without_attachments' => $data['withoutAttachments']->count(),
            'duplicate_main_policies' => $data['duplicateMainPolicies']->count(),
            'duplicate_sub_policies' => $data['duplicateSubPolicies']->count(),
            'trashed_documents' => $data['trashedDocuments']->count(),
            'without_main_policy' => $data['withoutMainPolicy']->count(),
            'without_sub_policy' => $data['withoutSubPolicy']->count(),
        ];

        return view('data-quality.index', $data);
    }

    private function safeWithoutAttachments(): Collection
    {
        if (! Schema::hasTable('document_attachments')) {
            return collect();
        }

        try {
            return Document::query()
                ->whereDoesntHave('attachments')
                ->latest('reference_date')
                ->latest('id')
                ->limit(50)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function duplicatePolicies(string $column): Collection
    {
        if (! Schema::hasColumn('documents', $column)) {
            return collect();
        }

        $duplicates = Document::query()
            ->select($column, DB::raw('COUNT(*) as duplicate_count'))
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('duplicate_count')
            ->limit(50)
            ->get();

        return $duplicates->map(function ($row) use ($column) {
            $policy = $row->{$column};

            $documents = Document::query()
                ->where($column, $policy)
                ->orderBy('reference_date')
                ->orderBy('reference_number')
                ->get();

            return [
                'policy' => $policy,
                'count' => (int) $row->duplicate_count,
                'documents' => $documents,
            ];
        });
    }

    private function documentsMissingPolicy(string $column): Collection
    {
        if (! Schema::hasColumn('documents', $column)) {
            return collect();
        }

        return Document::query()
            ->where(function ($query) use ($column) {
                $query->whereNull($column)->orWhere($column, '');
            })
            ->latest('reference_date')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    private function safeTrashedDocuments(): Collection
    {
        try {
            return Document::onlyTrashed()
                ->latest('deleted_at')
                ->limit(50)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
PHP_CONTROLLER;

$view = <<<'BLADE_VIEW'
@extends('layouts.app')

@section('title', 'جودة البيانات')

@section('content')
<style>
    .dq-page { direction: rtl; }
    .dq-actions { display:flex; gap:.6rem; flex-wrap:wrap; align-items:center; margin-bottom:1rem; }
    .dq-btn { display:inline-flex; align-items:center; justify-content:center; gap:.35rem; border:1px solid rgba(148,163,184,.25); border-radius:.7rem; padding:.55rem .85rem; text-decoration:none; font-weight:700; cursor:pointer; background:#2563eb; color:#fff; }
    .dq-btn.secondary { background:#e5e7eb; color:#111827; }
    .dq-btn.ghost { background:transparent; color:inherit; }
    .dq-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:.75rem; margin:1rem 0; }
    .dq-card, .dq-panel { background:rgba(15,23,42,.86); border:1px solid rgba(148,163,184,.22); border-radius:1rem; color:#e5e7eb; box-shadow:0 10px 24px rgba(0,0,0,.12); }
    .dq-card { padding:1rem; min-height:86px; display:flex; flex-direction:column; justify-content:space-between; }
    .dq-card strong { font-size:1.55rem; color:#fff; }
    .dq-card span { color:#cbd5e1; font-size:.9rem; }
    .dq-panel { padding:1rem; margin-bottom:1rem; overflow:hidden; }
    .dq-panel-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:.85rem; }
    .dq-panel h3 { margin:0; color:#f8fafc; font-size:1.08rem; }
    .dq-panel small { color:#94a3b8; }
    .dq-warning { border:1px solid rgba(245,158,11,.55); background:rgba(120,53,15,.34); color:#fde68a; border-radius:.9rem; padding:.85rem 1rem; margin:1rem 0; font-weight:700; }
    .dq-empty { color:#94a3b8; padding:.9rem 0; }
    .dq-table-wrap { overflow-x:auto; }
    .dq-table { width:100%; border-collapse:collapse; min-width:720px; }
    .dq-table th { background:rgba(30,41,59,.94); color:#cbd5e1; font-size:.85rem; text-align:right; padding:.8rem; white-space:nowrap; }
    .dq-table td { border-top:1px solid rgba(148,163,184,.18); padding:.75rem .8rem; color:#e5e7eb; vertical-align:middle; }
    .dq-pill { display:inline-flex; align-items:center; justify-content:center; border-radius:999px; padding:.25rem .55rem; background:rgba(37,99,235,.18); border:1px solid rgba(59,130,246,.38); color:#dbeafe; font-weight:700; margin:.15rem; text-decoration:none; }
    .dq-pill.light { background:#f1f5f9; color:#111827; border-color:#cbd5e1; }
    .dq-copy { border:0; border-radius:.55rem; padding:.35rem .55rem; background:#334155; color:#fff; cursor:pointer; font-size:.8rem; }
    .dq-doc-link { color:#bfdbfe; text-decoration:none; font-weight:700; }
    .dq-doc-link:hover { text-decoration:underline; }
    .dq-two { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    @media (max-width: 1100px) { .dq-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dq-two { grid-template-columns:1fr; } }
    @media (max-width: 640px) { .dq-grid { grid-template-columns:1fr; } .dq-actions { flex-direction:column; align-items:stretch; } .dq-btn { width:100%; } }
    @media print {
        body { background:#fff !important; color:#111 !important; }
        .sidebar, .app-sidebar, .dq-actions, .dq-copy, .no-print { display:none !important; }
        .dq-card, .dq-panel { background:#fff !important; color:#111 !important; border:1px solid #ddd !important; box-shadow:none !important; }
        .dq-panel h3, .dq-card strong, .dq-table td { color:#111 !important; }
        .dq-table th { background:#f3f4f6 !important; color:#111 !important; }
    }
</style>

<div class="dq-page">
    <div class="page-header">
        <h1>جودة البيانات 🧭</h1>
        <p>مراجعة الكتب التي تحتاج انتباه: مرفقات ناقصة، بوالص مكررة، أو بيانات غير مكتملة.</p>
    </div>

    <div class="dq-actions no-print">
        <a class="dq-btn secondary" href="{{ url()->previous() }}">رجوع</a>
        <a class="dq-btn" href="{{ route('data-quality.index') }}">تحديث المراجعة</a>
        <button class="dq-btn ghost" type="button" onclick="window.print()">طباعة التقرير</button>
    </div>

    @unless($documentsTableExists)
        <div class="dq-warning">جدول الكتب غير موجود حالياً. تأكد من تشغيل migrations أو استعادة قاعدة بيانات سليمة.</div>
    @endunless

    <div class="dq-grid">
        <div class="dq-card"><span>كتب بلا مرفقات</span><strong>{{ $summary['without_attachments'] }}</strong></div>
        <div class="dq-card"><span>بوالص رئيسية مكررة</span><strong>{{ $summary['duplicate_main_policies'] }}</strong></div>
        <div class="dq-card"><span>بوالص فرعية مكررة</span><strong>{{ $summary['duplicate_sub_policies'] }}</strong></div>
        <div class="dq-card"><span>كتب في سلة المحذوفات</span><strong>{{ $summary['trashed_documents'] }}</strong></div>
    </div>

    @if(array_sum($summary) > 0)
        <div class="dq-warning">توجد ملاحظات يفضل مراجعتها. هذه الصفحة لا تمنع العمل، لكنها تساعد المدير على تنظيف البيانات.</div>
    @else
        <div class="dq-warning" style="border-color:rgba(34,197,94,.55);background:rgba(20,83,45,.28);color:#bbf7d0;">لا توجد ملاحظات حالياً. جودة البيانات سليمة.</div>
    @endif

    <div class="dq-two">
        @include('data-quality.partials.documents-panel', [
            'title' => 'كتب بلا مرفقات',
            'subtitle' => 'كتب تم إنشاؤها ولم يتم رفع مرفق لها بعد.',
            'documents' => $withoutAttachments,
            'empty' => 'لا توجد كتب بلا مرفقات.'
        ])

        @include('data-quality.partials.documents-panel', [
            'title' => 'كتب في سلة المحذوفات',
            'subtitle' => 'كتب محذوفة حذفاً مؤقتاً ويمكن مراجعتها من سلة المحذوفات.',
            'documents' => $trashedDocuments,
            'empty' => 'لا توجد كتب محذوفة حالياً.',
            'trashed' => true
        ])
    </div>

    @include('data-quality.partials.duplicate-policy-panel', [
        'title' => 'البوالص الرئيسية المكررة',
        'subtitle' => 'أرقام بوالص رئيسية مرتبطة بأكثر من كتاب.',
        'items' => $duplicateMainPolicies,
        'empty' => 'لا توجد بوالص رئيسية مكررة.'
    ])

    @include('data-quality.partials.duplicate-policy-panel', [
        'title' => 'البوالص الفرعية المكررة',
        'subtitle' => 'أرقام بوالص فرعية مرتبطة بأكثر من كتاب.',
        'items' => $duplicateSubPolicies,
        'empty' => 'لا توجد بوالص فرعية مكررة.'
    ])

    <div class="dq-two">
        @include('data-quality.partials.documents-panel', [
            'title' => 'كتب بدون بوليصة رئيسية',
            'subtitle' => 'كتب لم يتم إدخال رقم البوليصة الرئيسية لها.',
            'documents' => $withoutMainPolicy,
            'empty' => 'لا توجد نتائج.'
        ])

        @include('data-quality.partials.documents-panel', [
            'title' => 'كتب بدون بوليصة فرعية',
            'subtitle' => 'كتب لم يتم إدخال رقم البوليصة الفرعية لها.',
            'documents' => $withoutSubPolicy,
            'empty' => 'لا توجد نتائج.'
        ])
    </div>
</div>

<script>
function copyDQValue(value) {
    if (!value) return;
    navigator.clipboard?.writeText(value).then(function () {
        const toast = document.createElement('div');
        toast.textContent = 'تم نسخ الرقم: ' + value;
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.left = '20px';
        toast.style.zIndex = '9999';
        toast.style.background = '#0f172a';
        toast.style.color = '#fff';
        toast.style.border = '1px solid rgba(148,163,184,.35)';
        toast.style.padding = '.75rem 1rem';
        toast.style.borderRadius = '.75rem';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 1800);
    });
}
</script>
@endsection
BLADE_VIEW;

$documentsPanel = <<<'BLADE_PARTIAL'
@php($trashed = $trashed ?? false)
<div class="dq-panel">
    <div class="dq-panel-header">
        <div>
            <h3>{{ $title }}</h3>
            <small>{{ $subtitle }}</small>
        </div>
        <span class="dq-pill">{{ $documents->count() }}</span>
    </div>

    @if($documents->isEmpty())
        <div class="dq-empty">{{ $empty }}</div>
    @else
        <div class="dq-table-wrap">
            <table class="dq-table">
                <thead>
                    <tr>
                        <th>رقم الكتاب</th>
                        <th>التاريخ</th>
                        <th>الموضوع</th>
                        <th>البوليصة الرئيسية</th>
                        <th>البوليصة الفرعية</th>
                        <th class="no-print">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td>
                                @if(!$trashed)
                                    <a class="dq-doc-link" href="{{ route('documents.show', $document) }}">{{ $document->reference_number }}</a>
                                @else
                                    <span class="dq-pill light">{{ $document->reference_number }}</span>
                                @endif
                            </td>
                            <td>{{ optional($document->reference_date)->format('Y-m-d') ?? $document->reference_date }}</td>
                            <td>{{ $document->subject ?? $document->title ?? '-' }}</td>
                            <td>{{ $document->main_policy_number ?: '-' }}</td>
                            <td>{{ $document->sub_policy_number ?: '-' }}</td>
                            <td class="no-print">
                                @if(!$trashed)
                                    <a class="dq-btn secondary" href="{{ route('documents.show', $document) }}">عرض</a>
                                @else
                                    <a class="dq-btn secondary" href="{{ route('trash.index') }}">السلة</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
BLADE_PARTIAL;

$duplicatePanel = <<<'BLADE_PARTIAL'
<div class="dq-panel">
    <div class="dq-panel-header">
        <div>
            <h3>{{ $title }}</h3>
            <small>{{ $subtitle }}</small>
        </div>
        <span class="dq-pill">{{ $items->count() }}</span>
    </div>

    @if($items->isEmpty())
        <div class="dq-empty">{{ $empty }}</div>
    @else
        <div class="dq-table-wrap">
            <table class="dq-table">
                <thead>
                    <tr>
                        <th>رقم البوليصة</th>
                        <th>عدد التكرار</th>
                        <th>الكتب المرتبطة</th>
                        <th class="no-print">نسخ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td><span class="dq-pill light">{{ $item['policy'] }}</span></td>
                            <td><span class="dq-pill">{{ $item['count'] }}</span></td>
                            <td>
                                @foreach($item['documents'] as $document)
                                    <a class="dq-pill" href="{{ route('documents.show', $document) }}" title="{{ $document->subject ?? $document->title ?? '' }}">
                                        {{ $document->reference_number }}
                                    </a>
                                @endforeach
                            </td>
                            <td class="no-print">
                                <button class="dq-copy" type="button" onclick="copyDQValue(@js($item['policy']))">نسخ الرقم</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
BLADE_PARTIAL;

write_file($controllerPath, $controller);
write_file($viewPath, $view);
write_file($root . '/resources/views/data-quality/partials/documents-panel.blade.php', $documentsPanel);
write_file($root . '/resources/views/data-quality/partials/duplicate-policy-panel.blade.php', $duplicatePanel);

if (file_exists($routesPath)) {
    $routes = file_get_contents($routesPath);
    if (strpos($routes, 'DataQualityController') === false) {
        $routes = preg_replace('/use App\\Http\\Controllers\\DashboardController;\s*/', "$0\nuse App\\Http\\Controllers\\DataQualityController;\n", $routes, 1, $count);
        if ($count === 0) {
            $routes = "<?php\n\nuse App\\Http\\Controllers\\DataQualityController;\n" . preg_replace('/^<\?php\s*/', '', $routes);
        }
    }

    if (strpos($routes, "data-quality.index") === false) {
        $routeLine = "Route::get('/data-quality', [DataQualityController::class, 'index'])->name('data-quality.index');";
        if (strpos($routes, "Route::middleware(['auth'])") !== false) {
            $routes = preg_replace('/Route::middleware\(\[\'auth\'\]\)->group\(function\s*\(\)\s*\{/', "$0\n    {$routeLine}", $routes, 1);
        } elseif (strpos($routes, "Route::middleware('auth')") !== false) {
            $routes = preg_replace('/Route::middleware\(\'auth\'\)->group\(function\s*\(\)\s*\{/', "$0\n    {$routeLine}", $routes, 1);
        } else {
            $routes .= "\nRoute::middleware('auth')->group(function () {\n    {$routeLine}\n});\n";
        }
        backup_file($routesPath);
        file_put_contents($routesPath, $routes);
        echo "Route ensured: data-quality.index\n";
    }
}

echo "\nOK: تم تطبيق تحديث روابط وإجراءات جودة البيانات.\n";

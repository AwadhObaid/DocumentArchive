@extends('layouts.app')

@section('title', 'لوحة التحكم')

@section('content')
@php
    /* DASHBOARD_POLISH_VIEW */
    if (! function_exists('da_dashboard_value')) {
        function da_dashboard_value($item, string $key, $default = null) {
            if (is_array($item)) {
                return $item[$key] ?? $default;
            }

            if (is_object($item)) {
                return $item->{$key} ?? $default;
            }

            return $default;
        }
    }

    $user = auth()->user();

    $hasPermission = function (string $permission) use ($user): bool {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if (isset($user->role) && in_array($user->role, ['admin', 'administrator', 'super_admin', 'مدير النظام', 'مدير'], true)) {
            return true;
        }

        if (method_exists($user, 'hasPermission')) {
            return (bool) $user->hasPermission($permission);
        }

        return true;
    };

    $routeExists = fn (string $name): bool => \Illuminate\Support\Facades\Route::has($name);

    $routeUrl = function (string $name, array $params = [], string $fallback = '#') use ($routeExists): string {
        return $routeExists($name) ? route($name, $params) : url($fallback);
    };

    $num = fn ($value): string => number_format((int) ($value ?? 0));

    $months = da_dashboard_value($charts ?? [], 'documents_by_month', []);
    $departmentRows = da_dashboard_value($charts ?? [], 'documents_by_department', []);
    $typeRows = da_dashboard_value($charts ?? [], 'documents_by_type', []);

    $maxMonthValue = max(array_merge([1], array_map(fn ($item) => (int) da_dashboard_value($item, 'count', 0), $months ?: [])));
    $maxDepartment = max(array_merge([1], array_map(fn ($item) => (int) da_dashboard_value($item, 'count', 0), $departmentRows ?: [])));
    $maxType = max(array_merge([1], array_map(fn ($item) => (int) da_dashboard_value($item, 'count', 0), $typeRows ?: [])));

    $alerts = $dashboardAlerts ?? [];
    $visibleAlerts = array_values(array_filter(
        $alerts,
        fn ($alert) => ! (bool) da_dashboard_value($alert, 'hidden', false)
    ));

    $activityLabel = function (?string $action): string {
        $labels = [
            'document.created' => 'إضافة كتاب',
            'document.updated' => 'تعديل كتاب',
            'document.deleted' => 'حذف كتاب',
            'document.restored' => 'استعادة كتاب',
            'document.printed' => 'طباعة كتاب',
            'attachment.uploaded' => 'رفع مرفق',
            'attachment.previewed' => 'معاينة مرفق',
            'attachment.downloaded' => 'تنزيل مرفق',
            'backup.created' => 'إنشاء نسخة احتياطية',
            'backup.restored' => 'استعادة نسخة احتياطية',
            'settings.updated' => 'تعديل الإعدادات',
            'user.created' => 'إضافة مستخدم',
            'user.updated' => 'تعديل مستخدم',
            'user.deleted' => 'حذف مستخدم',
        ];

        return $labels[$action ?? ''] ?? ($action ?: 'نشاط');
    };
@endphp

<style>
    .da-dashboard {
        --da-bg: rgba(15, 23, 42, .72);
        --da-bg-soft: rgba(30, 41, 59, .56);
        --da-border: rgba(148, 163, 184, .20);
        --da-text: #f8fafc;
        --da-muted: #94a3b8;
        --da-muted-2: #cbd5e1;
        --da-blue: #60a5fa;
        --da-blue-2: #2563eb;
        --da-green: #22c55e;
        --da-yellow: #f59e0b;
        --da-red: #ef4444;
        display: flex;
        flex-direction: column;
        gap: 18px;
        color: var(--da-text);
    }

    .da-dashboard * { box-sizing: border-box; }

    .da-page-head,
    .da-section-title,
    .da-card-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        flex-wrap: wrap;
    }

    .da-page-title {
        margin: 0;
        font-size: clamp(26px, 3vw, 38px);
        font-weight: 950;
        letter-spacing: -0.5px;
    }

    .da-page-subtitle {
        margin: 8px 0 0;
        color: var(--da-muted);
        font-size: 14px;
        line-height: 1.8;
    }

    .da-actions,
    .da-quick-links {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .da-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 12px;
        text-decoration: none;
        border: 1px solid var(--da-border);
        background: rgba(15, 23, 42, .74);
        color: var(--da-text);
        font-weight: 850;
        font-size: 13px;
        transition: .18s ease;
        white-space: nowrap;
    }

    .da-btn:hover { transform: translateY(-1px); color: #fff; }
    .da-btn-primary { background: linear-gradient(135deg, var(--da-blue-2), #1d4ed8); border-color: rgba(37, 99, 235, .70); }
    .da-btn-soft { background: rgba(30, 41, 59, .72); }
    .da-btn-danger { color: #fecaca; background: rgba(127, 29, 29, .25); border-color: rgba(239, 68, 68, .28); }
    .da-btn-warning { color: #fde68a; background: rgba(120, 53, 15, .20); border-color: rgba(245, 158, 11, .30); }

    .da-grid { display: grid; gap: 14px; }
    .da-grid-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .da-grid-secondary { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .da-grid-modules { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .da-grid-main { grid-template-columns: minmax(0, 1.4fr) minmax(320px, .9fr); align-items: start; }
    .da-grid-charts { grid-template-columns: minmax(0, 1.2fr) minmax(320px, .85fr); align-items: stretch; }

    .da-card {
        background: var(--da-bg);
        border: 1px solid var(--da-border);
        border-radius: 18px;
        padding: 18px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .15);
        min-width: 0;
    }

    .da-stat {
        position: relative;
        overflow: hidden;
        min-height: 126px;
    }

    .da-stat::after {
        content: '';
        position: absolute;
        inset-inline-start: -40px;
        top: -42px;
        width: 120px;
        height: 120px;
        border-radius: 999px;
        background: rgba(37, 99, 235, .12);
    }

    .da-stat > * { position: relative; z-index: 1; }
    .da-stat-icon { font-size: 22px; margin-bottom: 10px; }
    .da-stat-label { color: var(--da-muted); font-weight: 850; font-size: 13px; margin-bottom: 9px; }
    .da-stat-value { color: #fff; font-size: 30px; font-weight: 950; line-height: 1; }
    .da-stat-note { margin-top: 10px; color: var(--da-muted); font-size: 12px; line-height: 1.7; }

    /* DASHBOARD_MODULES_V89 */
    .da-module-card {
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        min-height: 178px;
        text-decoration: none;
        color: var(--da-text);
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }

    .da-module-card:hover {
        transform: translateY(-2px);
        color: #fff;
        border-color: rgba(96, 165, 250, .48);
        box-shadow: 0 16px 38px rgba(0, 0, 0, .22);
    }

    .da-module-card::after {
        content: '';
        position: absolute;
        width: 150px;
        height: 150px;
        border-radius: 999px;
        inset-inline-end: -58px;
        top: -62px;
        background: var(--da-module-glow, rgba(96, 165, 250, .14));
        pointer-events: none;
    }

    .da-module-card > * { position: relative; z-index: 1; }
    .da-module-card-memos { --da-module-glow: rgba(168, 85, 247, .18); }
    .da-module-card-circulars { --da-module-glow: rgba(245, 158, 11, .18); }
    .da-module-card-misc { --da-module-glow: rgba(34, 197, 94, .17); }

    .da-module-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 18px;
    }

    .da-module-icon {
        width: 48px;
        height: 48px;
        border-radius: 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(30, 41, 59, .82);
        border: 1px solid rgba(148, 163, 184, .18);
        font-size: 25px;
    }

    .da-module-arrow {
        color: #bfdbfe;
        font-size: 20px;
        font-weight: 950;
        transform: translateX(0);
        transition: transform .18s ease;
    }

    .da-module-card:hover .da-module-arrow { transform: translateX(-3px); }
    .da-module-title { margin: 0; color: #fff; font-size: 17px; font-weight: 950; }
    .da-module-count { margin-top: 9px; color: #fff; font-size: 34px; font-weight: 950; line-height: 1; }
    .da-module-note { margin-top: auto; padding-top: 14px; color: var(--da-muted); font-size: 12px; font-weight: 750; line-height: 1.7; }

    .da-section-title { margin-bottom: 14px; align-items: center; }
    .da-section-title h2 { margin: 0; font-size: 18px; font-weight: 950; }
    .da-section-hint { color: var(--da-muted); font-size: 12px; font-weight: 800; }

    .da-admin-alerts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .da-admin-alert-item {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 12px;
        padding: 14px;
        border-radius: 16px;
        background: var(--da-bg-soft);
        border: 1px solid rgba(148, 163, 184, .16);
        min-width: 0;
    }

    .da-admin-alert-icon {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: rgba(37, 99, 235, .13);
        font-size: 21px;
    }

    .da-admin-alert-title { color: #fff; font-weight: 950; margin-bottom: 5px; }
    .da-admin-alert-message { color: #aebcd2; font-size: 12px; font-weight: 750; line-height: 1.7; }
    .da-admin-alert-link { color: #bfdbfe; text-decoration: none; font-size: 12px; font-weight: 950; white-space: nowrap; }
    .da-admin-alert-item.danger { border-color: rgba(239, 68, 68, .35); background: rgba(127, 29, 29, .18); }
    .da-admin-alert-item.warning { border-color: rgba(245, 158, 11, .38); background: rgba(120, 53, 15, .18); }
    .da-admin-alert-item.info { border-color: rgba(56, 189, 248, .30); background: rgba(12, 74, 110, .15); }
    .da-admin-alert-ok { border-color: rgba(34, 197, 94, .32); background: rgba(20, 83, 45, .18); }

    .da-chart-scroll { overflow-x: auto; padding-bottom: 4px; }
    .da-chart-bars {
        min-width: 560px;
        height: 260px;
        display: flex;
        align-items: flex-end;
        gap: 18px;
        padding: 22px 12px 12px;
        border-radius: 16px;
        background: linear-gradient(180deg, rgba(30,41,59,.44), rgba(15,23,42,.18));
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

    .da-month-bar { position: relative; z-index: 1; flex: 1; min-width: 72px; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; gap: 8px; height: 220px; }
    .da-month-value { color: #e0f2fe; font-weight: 950; font-size: 13px; }
    .da-month-fill { width: 46px; min-height: 8px; border-radius: 999px 999px 10px 10px; background: linear-gradient(180deg, var(--da-blue), var(--da-blue-2)); box-shadow: 0 10px 24px rgba(37,99,235,.26); }
    .da-month-label { color: var(--da-muted); font-size: 11px; font-weight: 850; text-align: center; line-height: 1.35; min-height: 32px; }

    .da-mini-charts { display: flex; flex-direction: column; gap: 14px; }
    .da-horizontal-chart { display: flex; flex-direction: column; gap: 11px; }
    .da-bar-row { display: grid; grid-template-columns: minmax(95px, 135px) 1fr auto; align-items: center; gap: 10px; }
    .da-bar-label { color: #e5e7eb; font-size: 12px; font-weight: 850; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .da-bar-track { height: 13px; border-radius: 999px; background: rgba(30,41,59,.84); border: 1px solid rgba(148,163,184,.12); overflow: hidden; }
    .da-bar-fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #38bdf8, var(--da-blue-2)); min-width: 4px; }
    .da-bar-count { color: #bfdbfe; font-size: 12px; font-weight: 950; min-width: 28px; text-align: left; }

    .da-table-wrap { overflow-x: auto; border-radius: 14px; }
    .da-table { width: 100%; border-collapse: collapse; min-width: 720px; }
    .da-table th, .da-table td { padding: 12px 10px; border-bottom: 1px solid rgba(148, 163, 184, .15); text-align: right; vertical-align: middle; white-space: nowrap; }
    .da-table th { color: var(--da-muted); font-size: 12px; font-weight: 950; }
    .da-table td { color: var(--da-text); font-weight: 750; font-size: 13px; }
    .da-link { color: #bfdbfe; text-decoration: none; font-weight: 950; }
    .da-link:hover { color: #fff; }
    .da-muted { color: var(--da-muted); font-weight: 750; }
    .da-empty { padding: 26px; text-align: center; color: var(--da-muted); border: 1px dashed rgba(148, 163, 184, .25); border-radius: 14px; font-weight: 850; }

    .da-side-list,
    .da-activity { display: flex; flex-direction: column; gap: 12px; }
    .da-info-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 13px; border-radius: 14px; background: var(--da-bg-soft); border: 1px solid rgba(148, 163, 184, .14); }
    .da-info-row strong { color: #fff; text-align: left; }

    .da-badge { display: inline-flex; align-items: center; justify-content: center; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 950; border: 1px solid rgba(148, 163, 184, .24); color: #e5e7eb; white-space: nowrap; }
    .da-badge-ok { color: #bbf7d0; background: rgba(22, 163, 74, .16); border-color: rgba(34, 197, 94, .35); }
    .da-badge-warn { color: #fde68a; background: rgba(245, 158, 11, .15); border-color: rgba(245, 158, 11, .35); }
    .da-badge-info { color: #bfdbfe; background: rgba(37, 99, 235, .16); border-color: rgba(37, 99, 235, .35); }

    .da-activity-item { padding: 12px; border-radius: 14px; background: var(--da-bg-soft); border: 1px solid rgba(148, 163, 184, .12); }
    .da-activity-title { color: #fff; font-weight: 900; margin-bottom: 6px; line-height: 1.7; }
    .da-activity-meta { color: var(--da-muted); font-size: 12px; font-weight: 750; line-height: 1.8; }

    @media (max-width: 1200px) {
        .da-grid-stats,
        .da-grid-secondary,
        .da-grid-modules { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .da-grid-main,
        .da-grid-charts,
        .da-admin-alerts { grid-template-columns: 1fr; }
    }

    @media (max-width: 640px) {
        .da-grid-stats,
        .da-grid-secondary,
        .da-grid-modules { grid-template-columns: 1fr; }
        .da-page-head { align-items: stretch; }
        .da-actions, .da-actions .da-btn, .da-quick-links, .da-quick-links .da-btn { width: 100%; }
        .da-admin-alert-item { grid-template-columns: auto minmax(0, 1fr); }
        .da-admin-alert-link { grid-column: 2; }
        .da-admin-alert-item[hidden] { display: none !important; }
        .da-bar-row { grid-template-columns: 1fr; gap: 6px; }
        .da-bar-count { text-align: right; }
        .da-info-row { align-items: flex-start; flex-direction: column; }
    }
</style>

<div class="da-dashboard" dir="rtl">
    <div class="da-page-head">
        <div>
            <h1 class="da-page-title">لوحة التحكم</h1>
            <p class="da-page-subtitle">ملخص سريع لحالة الأرشيف الإلكتروني والكتب والمرفقات وجودة البيانات والنسخ الاحتياطي.</p>
        </div>

        <div class="da-actions">
            @if($hasPermission('documents.create') && $routeExists('documents.create'))
                <a class="da-btn da-btn-primary" href="{{ route('documents.create') }}">➕ إضافة كتاب</a>
            @endif
            @if($hasPermission('documents.view') && $routeExists('documents.index'))
                <a class="da-btn da-btn-soft" href="{{ route('documents.index') }}">📚 صفحة الكتب</a>
            @endif
            @if($hasPermission('reports.view') && $routeExists('reports.index'))
                <a class="da-btn da-btn-soft" href="{{ route('reports.index') }}">📊 التقارير</a>
            @endif
            @if($hasPermission('backups.view') && $routeExists('backups.index'))
                <a class="da-btn da-btn-soft" href="{{ route('backups.index') }}">💾 النسخ الاحتياطي</a>
            @endif
        </div>
    </div>

    @if(!empty(da_dashboard_value($healthSummary ?? [], 'warnings', [])))
        <div class="da-admin-alert-item warning">
            <div class="da-admin-alert-icon">⚠️</div>
            <div>
                <div class="da-admin-alert-title">توجد تنبيهات في فحص النظام</div>
                <div class="da-admin-alert-message">{{ implode('، ', da_dashboard_value($healthSummary, 'warnings', [])) }}</div>
            </div>
            @if($hasPermission('system_health.view') && $routeExists('system-health.index'))
                <a class="da-admin-alert-link" href="{{ route('system-health.index') }}">فحص النظام</a>
            @endif
        </div>
    @endif

    <div class="da-card">
        <div class="da-section-title">
            <h2>التنبيهات الإدارية</h2>
            <span class="da-section-hint">مؤشرات تحتاج مراجعة سريعة</span>
        </div>

        <div class="da-admin-alerts" data-live-sync-alerts>
            @foreach($alerts as $alert)
                @php
                    $alertKey = (string) da_dashboard_value($alert, 'key', '');
                    $alertHidden = (bool) da_dashboard_value($alert, 'hidden', false);
                @endphp

                <div
                    class="da-admin-alert-item {{ da_dashboard_value($alert, 'type', 'info') }}"
                    @if($alertKey !== '') data-live-sync-alert="{{ $alertKey }}" @endif
                    @if($alertHidden) hidden @endif
                >
                    <div class="da-admin-alert-icon">{{ da_dashboard_value($alert, 'icon', '🔔') }}</div>
                    <div>
                        <div class="da-admin-alert-title">{{ da_dashboard_value($alert, 'title', 'تنبيه') }}</div>

                        @if($alertKey === 'documents_without_attachments')
                            <div class="da-admin-alert-message">
                                يوجد
                                <span data-live-sync-alert-count="documents_without_attachments">{{ $num(da_dashboard_value($alert, 'count', 0)) }}</span>
                                كتاب بدون مرفقات.
                            </div>
                        @else
                            <div class="da-admin-alert-message">{{ da_dashboard_value($alert, 'message', '') }}</div>
                        @endif
                    </div>

                    @if(!empty(da_dashboard_value($alert, 'url')))
                        <a class="da-admin-alert-link" href="{{ da_dashboard_value($alert, 'url') }}">{{ da_dashboard_value($alert, 'action', 'فتح') }}</a>
                    @endif
                </div>
            @endforeach
        </div>

        <div
            class="da-admin-alert-item da-admin-alert-ok"
            data-live-sync-alert-empty
            @if(!empty($visibleAlerts)) hidden @endif
        >
            <div class="da-admin-alert-icon">✅</div>
            <div>
                <div class="da-admin-alert-title">لا توجد تنبيهات حالياً</div>
                <div class="da-admin-alert-message">النسخ الاحتياطي والكتب والمرفقات وحالة النظام تبدو مستقرة.</div>
            </div>
        </div>
    </div>

    {{-- LIVE_DATA_SYNC_V91_DASHBOARD --}}
    <div class="da-grid da-grid-stats">
        <div class="da-card da-stat">
            <div class="da-stat-icon">📚</div>
            <div class="da-stat-label">إجمالي الكتب</div>
            <div class="da-stat-value" data-live-sync-count="documents_total">{{ $num(da_dashboard_value($stats, 'documents_total', 0)) }}</div>
            <div class="da-stat-note">كل الكتب المسجلة في النظام.</div>
        </div>
        <div class="da-card da-stat">
            <div class="da-stat-icon">✅</div>
            <div class="da-stat-label">الكتب الفعالة</div>
            <div class="da-stat-value" data-live-sync-count="documents_active">{{ $num(da_dashboard_value($stats, 'documents_active', 0)) }}</div>
            <div class="da-stat-note">بدون الكتب الموجودة في سلة المحذوفات.</div>
        </div>
        <div class="da-card da-stat">
            <div class="da-stat-icon">📅</div>
            <div class="da-stat-label">كتب اليوم</div>
            <div class="da-stat-value" data-live-sync-count="documents_today">{{ $num(da_dashboard_value($stats, 'documents_today', 0)) }}</div>
            <div class="da-stat-note">حسب تاريخ الكتاب أو تاريخ الإضافة.</div>
        </div>
        <div class="da-card da-stat">
            <div class="da-stat-icon">🗓️</div>
            <div class="da-stat-label">كتب الشهر</div>
            <div class="da-stat-value" data-live-sync-count="documents_month">{{ $num(da_dashboard_value($stats, 'documents_month', 0)) }}</div>
            <div class="da-stat-note">إجمالي الكتب خلال الشهر الحالي.</div>
        </div>
    </div>

    <div class="da-grid da-grid-secondary">
        <div class="da-card da-stat">
            <div class="da-stat-icon">📎</div>
            <div class="da-stat-label">كتب لديها مرفقات</div>
            <div class="da-stat-value" data-live-sync-count="documents_with_attachments">{{ $num(da_dashboard_value($stats, 'documents_with_attachments', 0)) }}</div>
            <div class="da-stat-note">كتب تحتوي على ملف واحد أو أكثر.</div>
        </div>
        <div class="da-card da-stat">
            <div class="da-stat-icon">⚠️</div>
            <div class="da-stat-label">كتب بلا مرفقات</div>
            <div class="da-stat-value" data-live-sync-count="documents_without_attachments">{{ $num(da_dashboard_value($stats, 'documents_without_attachments', 0)) }}</div>
            <div class="da-stat-note">تحتاج مراجعة إذا كان المرفق إلزامياً.</div>
        </div>
        <div class="da-card da-stat">
            <div class="da-stat-icon">🗑️</div>
            <div class="da-stat-label">كتب محذوفة</div>
            <div class="da-stat-value" data-live-sync-count="documents_trashed">{{ $num(da_dashboard_value($stats, 'documents_trashed', 0)) }}</div>
            <div class="da-stat-note">موجودة في سلة المحذوفات.</div>
        </div>
        <div class="da-card da-stat">
            <div class="da-stat-icon">📎</div>
            <div class="da-stat-label">إجمالي المرفقات</div>
            <div class="da-stat-value" data-live-sync-count="attachments_total">{{ $num(da_dashboard_value($stats, 'attachments_total', 0)) }}</div>
            <div class="da-stat-note">كل ملفات PDF والصور المرفوعة.</div>
        </div>
    </div>


    @php
        $canViewMemos = $hasPermission('memos.view') && $routeExists('memos.index');
        $canViewCirculars = $hasPermission('circulars.view') && $routeExists('circulars.index');
        $canViewMiscBooks = $hasPermission('misc_books.view') && $routeExists('misc-books.index');
    @endphp

    @if($canViewMemos || $canViewCirculars || $canViewMiscBooks)
        <div class="da-card">
            <div class="da-section-title">
                <h2>المذكرات والتعاميم والمتفرقات</h2>
                <span class="da-section-hint">إجمالي السجلات الفعالة مع وصول مباشر إلى كل وحدة</span>
            </div>

            <div class="da-grid da-grid-modules">
                @if($canViewMemos)
                    <a class="da-card da-module-card da-module-card-memos" href="{{ route('memos.index') }}">
                        <div class="da-module-head">
                            <span class="da-module-icon">📒</span>
                            <span class="da-module-arrow" aria-hidden="true">←</span>
                        </div>
                        <h3 class="da-module-title">المذكرات</h3>
                        <div class="da-module-count" data-live-sync-count="memos_total">{{ $num(da_dashboard_value($stats, 'memos_total', 0)) }}</div>
                        <div class="da-module-note">المذكرات المسجلة والفعالة، دون العناصر الموجودة في سلة المحذوفات.</div>
                    </a>
                @endif

                @if($canViewCirculars)
                    <a class="da-card da-module-card da-module-card-circulars" href="{{ route('circulars.index') }}">
                        <div class="da-module-head">
                            <span class="da-module-icon">📢</span>
                            <span class="da-module-arrow" aria-hidden="true">←</span>
                        </div>
                        <h3 class="da-module-title">التعاميم</h3>
                        <div class="da-module-count" data-live-sync-count="circulars_total">{{ $num(da_dashboard_value($stats, 'circulars_total', 0)) }}</div>
                        <div class="da-module-note">التعاميم المسجلة والفعالة، دون العناصر الموجودة في سلة المحذوفات.</div>
                    </a>
                @endif

                @if($canViewMiscBooks)
                    <a class="da-card da-module-card da-module-card-misc" href="{{ route('misc-books.index') }}">
                        <div class="da-module-head">
                            <span class="da-module-icon">🗃️</span>
                            <span class="da-module-arrow" aria-hidden="true">←</span>
                        </div>
                        <h3 class="da-module-title">المتفرقات</h3>
                        <div class="da-module-count" data-live-sync-count="misc_books_total">{{ $num(da_dashboard_value($stats, 'misc_books_total', 0)) }}</div>
                        <div class="da-module-note">الكتب المتفرقة المسجلة والفعالة، دون العناصر الموجودة في سلة المحذوفات.</div>
                    </a>
                @endif
            </div>
        </div>
    @endif

    <div class="da-card">
        <div class="da-section-title">
            <h2>روابط متابعة سريعة</h2>
            <span class="da-section-hint">فتح مباشر لأكثر الحالات استخداماً</span>
        </div>
        <div class="da-quick-links">
            @if($hasPermission('documents.view') && $routeExists('documents.index'))
                <a class="da-btn da-btn-soft" href="{{ route('documents.index', ['has_attachment' => 'yes']) }}">📎 كتب لديها مرفقات</a>
                <a class="da-btn da-btn-warning" href="{{ route('documents.index', ['has_attachment' => 'no']) }}">⚠️ كتب بلا مرفقات</a>
                <a class="da-btn da-btn-soft" href="{{ route('documents.index', ['sort' => 'created_at', 'direction' => 'desc']) }}">🆕 آخر الكتب</a>
            @endif
            @if($hasPermission('documents.restore') && $routeExists('documents.trash'))
                <a class="da-btn da-btn-danger" href="{{ route('documents.trash') }}">🗑️ سلة المحذوفات</a>
            @endif
            @if($hasPermission('data_quality.view') && $routeExists('data-quality.index'))
                <a class="da-btn da-btn-soft" href="{{ route('data-quality.index') }}">🧹 جودة البيانات</a>
            @endif
            @if($hasPermission('activity_logs.view') && $routeExists('activity-logs.index'))
                <a class="da-btn da-btn-soft" href="{{ route('activity-logs.index') }}">🧾 سجل النشاط</a>
            @endif
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
                                $value = (int) da_dashboard_value($month, 'count', 0);
                                $height = max(8, (int) round(($value / $maxMonthValue) * 170));
                            @endphp
                            <div class="da-month-bar" title="{{ da_dashboard_value($month, 'label') }}: {{ $value }}">
                                <div class="da-month-value">{{ $num($value) }}</div>
                                <div class="da-month-fill" style="height: {{ $height }}px"></div>
                                <div class="da-month-label">{{ da_dashboard_value($month, 'label') }}</div>
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
                    <h2>أكثر الإدارات استخداماً</h2>
                    <span class="da-section-hint">أعلى الإدارات حسب عدد الكتب</span>
                </div>
                @if(!empty($departmentRows))
                    <div class="da-horizontal-chart">
                        @foreach($departmentRows as $row)
                            @php
                                $value = (int) da_dashboard_value($row, 'count', 0);
                                $width = max(4, (int) round(($value / $maxDepartment) * 100));
                            @endphp
                            <div class="da-bar-row" title="{{ da_dashboard_value($row, 'label') }}: {{ $value }}">
                                <div class="da-bar-label">{{ da_dashboard_value($row, 'label') }}</div>
                                <div class="da-bar-track"><div class="da-bar-fill" style="width: {{ $width }}%"></div></div>
                                <div class="da-bar-count">{{ $num($value) }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="da-empty">لا توجد بيانات كافية للإدارات.</div>
                @endif
            </div>

            <div class="da-card">
                <div class="da-section-title">
                    <h2>أكثر أنواع الكتب استخداماً</h2>
                    <span class="da-section-hint">أعلى الأنواع حسب عدد الكتب</span>
                </div>
                @if(!empty($typeRows))
                    <div class="da-horizontal-chart">
                        @foreach($typeRows as $row)
                            @php
                                $value = (int) da_dashboard_value($row, 'count', 0);
                                $width = max(4, (int) round(($value / $maxType) * 100));
                            @endphp
                            <div class="da-bar-row" title="{{ da_dashboard_value($row, 'label') }}: {{ $value }}">
                                <div class="da-bar-label">{{ da_dashboard_value($row, 'label') }}</div>
                                <div class="da-bar-track"><div class="da-bar-fill" style="width: {{ $width }}%"></div></div>
                                <div class="da-bar-count">{{ $num($value) }}</div>
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
                @if($hasPermission('documents.view') && $routeExists('documents.index'))
                    <a class="da-btn da-btn-soft" href="{{ route('documents.index') }}">عرض الكل</a>
                @endif
            </div>

            @if(!empty($latestDocuments))
                <div class="da-table-wrap">
                    <table class="da-table">
                        <thead>
                            <tr>
                                <th>رقم الكتاب</th>
                                <th>العنوان / الموضوع</th>
                                <th>الإدارة</th>
                                <th>نوع الكتاب</th>
                                <th>البوليصة الرئيسية</th>
                                <th>المرفقات</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($latestDocuments as $document)
                                @php
                                    $docTitle = da_dashboard_value($document, 'title') ?: da_dashboard_value($document, 'subject', 'بدون موضوع');
                                    $docDate = da_dashboard_value($document, 'reference_date') ?: da_dashboard_value($document, 'created_at');
                                @endphp
                                <tr>
                                    <td>
                                        @if($hasPermission('documents.view') && $routeExists('documents.show') && !empty(da_dashboard_value($document, 'id')))
                                            <a class="da-link" href="{{ route('documents.show', da_dashboard_value($document, 'id')) }}">
                                                {{ da_dashboard_value($document, 'reference_number', '#' . da_dashboard_value($document, 'id')) }}
                                            </a>
                                        @else
                                            {{ da_dashboard_value($document, 'reference_number', '#' . da_dashboard_value($document, 'id')) }}
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($docTitle ?: 'بدون موضوع', 48) }}</td>
                                    <td class="da-muted">{{ da_dashboard_value($document, 'department_name', '-') }}</td>
                                    <td class="da-muted">{{ da_dashboard_value($document, 'document_type_name', '-') }}</td>
                                    <td class="da-muted">{{ da_dashboard_value($document, 'main_policy_number', '-') ?: '-' }}</td>
                                    <td><span class="da-badge da-badge-info">{{ $num(da_dashboard_value($document, 'attachments_count', 0)) }}</span></td>
                                    <td class="da-muted">{{ $docDate ? \Carbon\Carbon::parse($docDate)->format('Y-m-d') : '-' }}</td>
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
                    <span class="da-badge {{ da_dashboard_value($healthSummary ?? [], 'ok', false) ? 'da-badge-ok' : 'da-badge-warn' }}">
                        {{ da_dashboard_value($healthSummary ?? [], 'status_text', 'غير معروف') }}
                    </span>
                </div>

                <div class="da-side-list">
                    <div class="da-info-row"><span class="da-muted">جداول قاعدة البيانات</span><strong>{{ empty(da_dashboard_value($healthSummary ?? [], 'missing_tables', [])) ? 'مكتملة' : 'ناقصة' }}</strong></div>
                    <div class="da-info-row"><span class="da-muted">الإدارات</span><strong>{{ $num(da_dashboard_value($stats, 'departments_total', 0)) }}</strong></div>
                    <div class="da-info-row"><span class="da-muted">أنواع الكتب</span><strong>{{ $num(da_dashboard_value($stats, 'document_types_total', 0)) }}</strong></div>
                    <div class="da-info-row"><span class="da-muted">أنشطة النظام</span><strong>{{ $num(da_dashboard_value($stats, 'activities_total', 0)) }}</strong></div>
                    <div class="da-info-row"><span class="da-muted">كتب السنة الحالية</span><strong>{{ $num(da_dashboard_value($stats, 'documents_year', 0)) }}</strong></div>
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
                        <div class="da-info-row"><span class="da-muted">النوع</span><strong>{{ da_dashboard_value($latestBackup, 'type') }}</strong></div>
                        <div class="da-info-row"><span class="da-muted">الحجم</span><strong>{{ da_dashboard_value($latestBackup, 'size') }}</strong></div>
                        <div class="da-info-row"><span class="da-muted">التاريخ</span><strong>{{ da_dashboard_value($latestBackup, 'created_at') }}</strong></div>
                    </div>
                    <div class="da-muted" style="margin-top:12px;font-size:12px;word-break:break-all;">{{ da_dashboard_value($latestBackup, 'name') }}</div>
                @else
                    <div class="da-empty">لم يتم إنشاء نسخة احتياطية بعد.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="da-card">
        <div class="da-section-title">
            <h2>آخر الأنشطة</h2>
            @if($hasPermission('activity_logs.view') && $routeExists('activity-logs.index'))
                <a class="da-btn da-btn-soft" href="{{ route('activity-logs.index') }}">عرض السجل</a>
            @endif
        </div>

        @if(!empty($latestActivities))
            <div class="da-activity">
                @foreach($latestActivities as $activity)
                    @php
                        $activityDate = da_dashboard_value($activity, 'created_at');
                        $actorName = da_dashboard_value($activity, 'user_name');
                        $modelName = da_dashboard_value($activity, 'model_type') ? class_basename(da_dashboard_value($activity, 'model_type')) : null;
                    @endphp
                    <div class="da-activity-item">
                        <div class="da-activity-title">
                            {{ da_dashboard_value($activity, 'description') ?: $activityLabel(da_dashboard_value($activity, 'action')) }}
                        </div>
                        <div class="da-activity-meta">
                            {{ $activityDate ? \Carbon\Carbon::parse($activityDate)->format('Y-m-d H:i') : '' }}
                            @if($actorName)
                                · بواسطة {{ $actorName }}
                            @endif
                            @if($modelName)
                                · {{ $modelName }} {{ da_dashboard_value($activity, 'model_id', '') }}
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

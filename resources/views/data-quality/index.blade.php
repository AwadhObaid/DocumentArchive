@extends('layouts.app')

@section('title', 'جودة البيانات')

@section('content')
<div class="dq-print-report-action" style="display:flex;justify-content:flex-start;gap:8px;margin:10px 0 16px;">
    <a href="{{ route('data-quality.print') }}" target="_blank" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
        🖨️ تقرير م
نسق للطباعة
    </a>
</div>
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
        <p>م
راجعة الكتب التي تحتاج انتباه: م
رفقات ناقصة، بوالص م
كررة، أو بيانات غير م
كتم
لة.</p>
    </div>

    <div class="dq-actions no-print">
        <a class="dq-btn secondary" href="{{ url()->previous() }}">رجوع</a>
        <a class="dq-btn" href="{{ route('data-quality.index') }}">تحديث الم
راجعة</a>
        <button class="dq-btn ghost" type="button" onclick="window.print()">طباعة التقرير</button>
    </div>

    @unless($documentsTableExists)
        <div class="dq-warning">جدول الكتب غير م
وجود حالياً. تأكد م
ن تشغيل migrations أو استعادة قاعدة بيانات سليم
ة.</div>
    @endunless

    <div class="dq-grid">
        <div class="dq-card"><span>كتب بلا م
رفقات</span><strong>{{ $summary['without_attachments'] }}</strong></div>
        <div class="dq-card"><span>بوالص رئيسية م
كررة</span><strong>{{ $summary['duplicate_main_policies'] }}</strong></div>
        <div class="dq-card"><span>بوالص فرعية م
كررة</span><strong>{{ $summary['duplicate_sub_policies'] }}</strong></div>
        <div class="dq-card"><span>كتب في سلة الم
حذوفات</span><strong>{{ $summary['trashed_documents'] }}</strong></div>
    </div>

    @if(array_sum($summary) > 0)
        <div class="dq-warning">توجد م
لاحظات يفضل م
راجعتها. هذه الصفحة لا تم
نع العم
ل، لكنها تساعد الم
دير على تنظيف البيانات.</div>
    @else
        <div class="dq-warning" style="border-color:rgba(34,197,94,.55);background:rgba(20,83,45,.28);color:#bbf7d0;">لا توجد م
لاحظات حالياً. جودة البيانات سليم
ة.</div>
    @endif

    <div class="dq-two">
        @include('data-quality.partials.documents-panel', [
            'title' => 'كتب بلا م
رفقات',
            'subtitle' => 'كتب تم
 إنشاؤها ولم
 يتم
 رفع م
رفق لها بعد.',
            'documents' => $withoutAttachments,
            'empty' => 'لا توجد كتب بلا م
رفقات.'
        ])

        @include('data-quality.partials.documents-panel', [
            'title' => 'كتب في سلة الم
حذوفات',
            'subtitle' => 'كتب م
حذوفة حذفاً م
ؤقتاً ويم
كن م
راجعتها م
ن سلة الم
حذوفات.',
            'documents' => $trashedDocuments,
            'empty' => 'لا توجد كتب م
حذوفة حالياً.',
            'trashed' => true
        ])
    </div>

    @include('data-quality.partials.duplicate-policy-panel', [
        'title' => 'البوالص الرئيسية الم
كررة',
        'subtitle' => 'أرقام
 بوالص رئيسية م
رتبطة بأكثر م
ن كتاب.',
        'items' => $duplicateMainPolicies,
        'empty' => 'لا توجد بوالص رئيسية م
كررة.'
    ])

    @include('data-quality.partials.duplicate-policy-panel', [
        'title' => 'البوالص الفرعية الم
كررة',
        'subtitle' => 'أرقام
 بوالص فرعية م
رتبطة بأكثر م
ن كتاب.',
        'items' => $duplicateSubPolicies,
        'empty' => 'لا توجد بوالص فرعية م
كررة.'
    ])

    <div class="dq-two">
        @include('data-quality.partials.documents-panel', [
            'title' => 'كتب بدون بوليصة رئيسية',
            'subtitle' => 'كتب لم
 يتم
 إدخال رقم
 البوليصة الرئيسية لها.',
            'documents' => $withoutMainPolicy,
            'empty' => 'لا توجد نتائج.'
        ])

        @include('data-quality.partials.documents-panel', [
            'title' => 'كتب بدون بوليصة فرعية',
            'subtitle' => 'كتب لم
 يتم
 إدخال رقم
 البوليصة الفرعية لها.',
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
        toast.textContent = 'تم
 نسخ الرقم
: ' + value;
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
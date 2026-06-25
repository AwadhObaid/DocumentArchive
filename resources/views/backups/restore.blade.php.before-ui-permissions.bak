@extends('layouts.app')

@section('title', 'استعادة نسخة احتياطية')

@section('content')
<div class="page-header">
    <div>
        <h1>استعادة نسخة احتياطية</h1>
        <p>اختر نوع الاستعادة بعد مراجعة محتويات النسخة. سيتم إنشاء نسخة أمان تلقائياً قبل الاستعادة.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('backups.inspect', $fileName) }}" class="btn btn-info">فحص النسخة</a>
        <a href="{{ route('backups.index') }}" class="btn btn-light">رجوع</a>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="alert alert-danger restore-danger">
    <strong>تنبيه شديد الأهمية:</strong>
    الاستعادة ستستبدل البيانات أو الملفات الحالية حسب الخيار المحدد. لا تنفذها إلا بعد التأكد من النسخة وفهم أثر العملية.
</div>

<div class="card restore-summary">
    <h3>{{ $fileName }}</h3>
    <div class="restore-stats-grid">
        <div class="stat-box"><span>نوع النسخة</span><strong>{{ $inferredType }}</strong></div>
        <div class="stat-box"><span>حجم النسخة</span><strong>{{ $fileSize }}</strong></div>
        <div class="stat-box"><span>ملفات SQL</span><strong>{{ count($sqlFiles) }}</strong></div>
        <div class="stat-box"><span>ملفات المرفقات</span><strong>{{ $documentFilesCount }}</strong></div>
    </div>
</div>

@if(count($warnings) > 0)
    <div class="alert alert-warning mt-3">
        <strong>تنبيهات:</strong>
        <ul class="mb-0">
            @foreach($warnings as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="restore-grid mt-4">
    <div class="card restore-card {{ count($sqlFiles) === 0 ? 'disabled-card' : '' }}">
        <h3>استعادة قاعدة البيانات</h3>
        <p>تستبدل جداول النظام الحالية بالبيانات الموجودة داخل ملف SQL في النسخة.</p>
        @if(count($sqlFiles) > 0)
            <form method="POST" action="{{ route('backups.restore.database', $fileName) }}" onsubmit="return confirm('تأكيد نهائي: هل تريد استعادة قاعدة البيانات؟')">
                @csrf
                @include('backups.restore-confirm-fields')
                <button type="submit" class="btn btn-danger">استعادة قاعدة البيانات</button>
            </form>
        @else
            <div class="alert alert-warning">لا يوجد ملف SQL داخل هذه النسخة.</div>
        @endif
    </div>

    <div class="card restore-card {{ $documentFilesCount === 0 ? 'disabled-card' : '' }}">
        <h3>استعادة ملفات المرفقات</h3>
        <p>تستبدل مجلد المرفقات الحالي بمجلد <strong>documents</strong> الموجود داخل النسخة.</p>
        @if($documentFilesCount > 0)
            <form method="POST" action="{{ route('backups.restore.files', $fileName) }}" onsubmit="return confirm('تأكيد نهائي: هل تريد استعادة ملفات المرفقات؟')">
                @csrf
                @include('backups.restore-confirm-fields')
                <button type="submit" class="btn btn-danger">استعادة ملفات المرفقات</button>
            </form>
        @else
            <div class="alert alert-warning">لا توجد ملفات مرفقات داخل هذه النسخة.</div>
        @endif
    </div>

    <div class="card restore-card {{ count($sqlFiles) === 0 || $documentFilesCount === 0 ? 'disabled-card' : '' }}">
        <h3>استعادة نسخة كاملة</h3>
        <p>تستعيد قاعدة البيانات وملفات المرفقات معاً. استخدم هذا الخيار للنسخ الكاملة فقط.</p>
        @if(count($sqlFiles) > 0 && $documentFilesCount > 0)
            <form method="POST" action="{{ route('backups.restore.full', $fileName) }}" onsubmit="return confirm('تأكيد نهائي: هل تريد استعادة النسخة الكاملة؟')">
                @csrf
                @include('backups.restore-confirm-fields')
                <button type="submit" class="btn btn-danger">استعادة النسخة الكاملة</button>
            </form>
        @else
            <div class="alert alert-warning">هذه النسخة لا تحتوي على قاعدة بيانات ومرفقات معاً.</div>
        @endif
    </div>
</div>

<style>
.page-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.restore-danger { font-weight: 700; }
.restore-summary { padding: 20px; }
.restore-summary h3 { direction: ltr; text-align: left; margin-bottom: 16px; }
.restore-stats-grid,
.restore-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 14px;
}
.stat-box {
    border: 1px solid rgba(148, 163, 184, .25);
    border-radius: 14px;
    padding: 14px;
    background: rgba(248, 250, 252, .65);
}
.stat-box span { display: block; color: #64748b; font-size: 13px; margin-bottom: 8px; }
.stat-box strong { font-size: 18px; }
.restore-card { padding: 18px; }
.restore-card h3 { margin-bottom: 8px; }
.restore-card p { color: #64748b; min-height: 72px; }
.restore-card .form-control { margin-bottom: 10px; }
.restore-check-row { display: flex; gap: 8px; align-items: start; margin: 10px 0; }
.disabled-card { opacity: .75; }
</style>
@endsection

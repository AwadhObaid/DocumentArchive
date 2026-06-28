@extends('layouts.app')

@section('title', 'فحص النسخة الاحتياطية')

@section('content')
<div class="page-header">
    <div>
        <h1>فحص النسخة الاحتياطية</h1>
        <p>فحص م
حتويات م
لف النسخة قبل الاعتم
اد عليه أو استخدام
ه في الاستعادة.</p>
    </div>
    <div class="page-actions">
        @if(auth()->user()?->hasPermission('backups.restore'))
        <a href="{{ route('backups.restore', $fileName) }}" class="btn btn-warning">استعادة هذه النسخة</a>
        @endif
        @if(auth()->user()?->hasPermission('backups.view'))
        <a href="{{ route('backups.index') }}" class="btn btn-light">رجوع للنسخ الاحتياطي</a>
        @endif
    </div>
</div>

<div class="card backup-summary-card">
    <div class="backup-title-row">
        <div>
            <h3>{{ $fileName }}</h3>
            <p>نوع النسخة الم
توقع: <strong>{{ $inferredType }}</strong></p>
        </div>
        @if(auth()->user()?->hasPermission('backups.download'))
        <a href="{{ route('backups.download', $fileName) }}" class="btn btn-primary">تنزيل النسخة</a>
        @endif
    </div>

    <div class="backup-stats-grid">
        <div class="stat-box"><span>حجم
 م
لف ZIP</span><strong>{{ $fileSize }}</strong></div>
        <div class="stat-box"><span>تاريخ الإنشاء</span><strong>{{ $createdAt }}</strong></div>
        <div class="stat-box"><span>عدد م
لفات SQL</span><strong>{{ count($sqlFiles) }}</strong></div>
        <div class="stat-box"><span>عدد م
لفات الم
رفقات</span><strong>{{ $documentFilesCount }}</strong></div>
        <div class="stat-box"><span>إجم
الي عناصر ZIP</span><strong>{{ $totalZipEntries }}</strong></div>
        <div class="stat-box"><span>الحجم
 بعد الفك تقريباً</span><strong>{{ $totalUncompressedSize }}</strong></div>
    </div>
</div>

@if(count($warnings) > 0)
    <div class="alert alert-warning mt-3">
        <strong>تنبيهات الفحص:</strong>
        <ul class="mb-0">
            @foreach($warnings as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </div>
@else
    <div class="alert alert-success mt-3">
        لم
 يتم
 رصد م
شاكل أساسية في بنية النسخة الاحتياطية حسب نوعها.
    </div>
@endif

@if(count($sqlFiles) > 0)
    <div class="card mt-4">
        <h3>م
لفات قاعدة البيانات داخل النسخة</h3>
        <ul class="backup-list-ltr">
            @foreach($sqlFiles as $sqlFile)
                <li>{{ $sqlFile }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(!empty($readmeContent))
    <div class="card mt-4">
        <h3>م
لف README داخل النسخة</h3>
        <pre class="backup-readme">{{ $readmeContent }}</pre>
    </div>
@endif

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3>م
حتويات النسخة</h3>
        <small>يتم
 عرض أول 300 عنصر فقط عند كبر حجم
 النسخة.</small>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>الم
سار داخل ZIP</th>
                    <th>الحجم
</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                    <tr>
                        <td class="entry-name">{{ $entry['name'] }}</td>
                        <td>{{ $entry['size'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="text-center text-muted">لا توجد م
حتويات قابلة للعرض.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<style>
.page-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.backup-summary-card { padding: 20px; }
.backup-title-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: center;
    margin-bottom: 18px;
}
.backup-title-row h3 { direction: ltr; text-align: left; margin-bottom: 6px; }
.backup-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
}
.stat-box {
    border: 1px solid rgba(148, 163, 184, .25);
    border-radius: 14px;
    padding: 14px;
    background: rgba(248, 250, 252, .65);
}
.stat-box span { display: block; color: #64748b; font-size: 13px; margin-bottom: 8px; }
.stat-box strong { font-size: 18px; }
.backup-list-ltr, .entry-name, .backup-readme { direction: ltr; text-align: left; }
.backup-readme {
    white-space: pre-wrap;
    background: #0f172a;
    color: #e5e7eb;
    border-radius: 12px;
    padding: 16px;
}
@media (max-width: 700px) {
    .backup-title-row { flex-direction: column; align-items: stretch; }
}
</style>
@endsection

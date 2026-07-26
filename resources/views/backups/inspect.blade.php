@extends('layouts.app')

@section('title', 'فحص النسخة الاحتياطية')

@section('content')
<div class="page-header backup-polish-v1">
    <div>
        <h1>فحص النسخة الاحتياطية</h1>
        <p>فحص قاعدة البيانات ومجلدات المرفقات وملف التحقق قبل الاعتماد على النسخة أو استعادتها.</p>
    </div>
    <div class="page-actions">
        @if(auth()->user()?->hasPermission('backups.restore'))
            <a href="{{ route('backups.restore', $fileName) }}" class="btn btn-warning">خيارات الاستعادة</a>
        @endif
        <a href="{{ route('backups.index') }}" class="btn btn-light">رجوع للنسخ الاحتياطي</a>
    </div>
</div>

<div class="card backup-summary-card">
    <div class="backup-title-row">
        <div>
            <h3 dir="ltr">{{ $fileName }}</h3>
            <p>نوع النسخة المتوقع: <strong>{{ $inferredType }}</strong></p>
        </div>
        @if(auth()->user()?->hasPermission('backups.download'))
            <a href="{{ route('backups.download', $fileName) }}" class="btn btn-primary">تنزيل النسخة</a>
        @endif
    </div>

    <div class="backup-stats-grid">
        <div class="stat-box"><span>حالة التحقق</span><strong>{{ $integrityStatus }}</strong></div>
        <div class="stat-box"><span>حجم ملف ZIP</span><strong>{{ $fileSize }}</strong></div>
        <div class="stat-box"><span>تاريخ الإنشاء</span><strong>{{ $createdAt }}</strong></div>
        <div class="stat-box"><span>ملفات SQL</span><strong>{{ count($sqlFiles) }}</strong></div>
        <div class="stat-box"><span>جداول قاعدة البيانات</span><strong>{{ $databaseTableCount }}</strong></div>
        <div class="stat-box"><span>ملفات المرفقات</span><strong>{{ $attachmentFilesCount }}</strong></div>
        <div class="stat-box"><span>حجم المرفقات</span><strong>{{ $attachmentTotalSize }}</strong></div>
        <div class="stat-box"><span>إجمالي عناصر ZIP</span><strong>{{ $totalZipEntries }}</strong></div>
        <div class="stat-box"><span>الحجم بعد الفك تقريبًا</span><strong>{{ $totalUncompressedSize }}</strong></div>
        <div class="stat-box"><span>Manifest</span><strong>{{ $manifestPresent ? 'موجود' : 'غير موجود' }}</strong></div>
    </div>
</div>

@if(count($criticalWarnings) > 0)
    <div class="alert alert-danger mt-3">
        <strong>فشل التحقق من اكتمال النسخة:</strong>
        <ul class="mb-0">
            @foreach($criticalWarnings as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
        <div class="mt-2"><strong>تم تعطيل الاستعادة الكاملة لهذه النسخة.</strong></div>
    </div>
@elseif($manifestPresent)
    <div class="alert alert-success mt-3">
        تم التحقق من تطابق Manifest مع ملف SQL وجميع مجلدات المرفقات داخل ZIP.
    </div>
@else
    <div class="alert alert-warning mt-3">
        هذه نسخة قديمة غير موثقة بملف Manifest؛ راجع محتوياتها يدويًا قبل أي استعادة جزئية.
    </div>
@endif

@if(count($warnings) > 0)
    <div class="alert alert-warning mt-3">
        <strong>ملاحظات الفحص:</strong>
        <ul class="mb-0">
            @foreach($warnings as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card mt-4 backup-info-card">
    <div class="card-header compact-header">
        <div>
            <h3>مجلدات المرفقات داخل النسخة</h3>
            <small>المجلدات الحديثة التي يعتمد عليها النظام.</small>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle backup-table">
            <thead>
                <tr>
                    <th>المجلد</th>
                    <th>الحالة</th>
                    <th>عدد الملفات</th>
                    <th>الحجم</th>
                </tr>
            </thead>
            <tbody>
                @foreach($attachmentDirectoryStats as $directory)
                    <tr>
                        <td dir="ltr">{{ $directory['directory'] }}</td>
                        <td>
                            @if($directory['present'])
                                <span class="status-badge ok">موجود</span>
                            @else
                                <span class="status-badge missing">غير موجود</span>
                            @endif
                        </td>
                        <td>{{ $directory['file_count'] }}</td>
                        <td>{{ \App\Models\CircularAttachment::formatBytes((int) $directory['total_bytes']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if(count($sqlFiles) > 0)
    <div class="card mt-4 backup-info-card">
        <h3>قاعدة البيانات داخل النسخة</h3>
        <p>عدد الجداول المكتشفة داخل SQL: <strong>{{ $databaseTableCount }}</strong></p>
        <ul class="backup-list-ltr">
            @foreach($sqlFiles as $sqlFile)
                <li>{{ $sqlFile }}</li>
            @endforeach
        </ul>

        @if(count($databaseTables) > 0)
            <details class="mt-3">
                <summary>عرض أسماء الجداول</summary>
                <div class="database-tables-list" dir="ltr">
                    @foreach($databaseTables as $table)
                        <code>{{ $table }}</code>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
@endif

@if($manifestPresent)
    <div class="card mt-4 backup-info-card">
        <h3>ملف التحقق Manifest</h3>
        <pre class="backup-readme">{{ json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}</pre>
    </div>
@endif

@if(!empty($readmeContent))
    <div class="card mt-4 backup-info-card">
        <h3>ملف README داخل النسخة</h3>
        <pre class="backup-readme">{{ $readmeContent }}</pre>
    </div>
@endif

<div class="card mt-4 backup-entries-card">
    <div class="card-header backup-list-header">
        <h3>محتويات النسخة</h3>
        <small>يتم عرض أول 300 عنصر فقط عند كبر حجم النسخة.</small>
    </div>
    <div class="table-responsive backup-table-wrap">
        <table class="table table-hover align-middle backup-table">
            <thead><tr><th>المسار داخل ZIP</th><th>الحجم</th></tr></thead>
            <tbody>
                @forelse($entries as $entry)
                    <tr>
                        <td class="entry-name">{{ $entry['name'] }}</td>
                        <td>{{ $entry['size'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-center text-muted">لا توجد محتويات قابلة للعرض.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<style>
.page-actions{display:flex;gap:8px;flex-wrap:wrap}.backup-summary-card,.backup-info-card{padding:20px}.backup-title-row{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:18px}.backup-title-row h3{text-align:left;margin-bottom:6px}.backup-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}.stat-box{border:1px solid rgba(148,163,184,.25);border-radius:14px;padding:14px;background:rgba(248,250,252,.08)}.stat-box span{display:block;color:#94a3b8;font-size:13px;margin-bottom:8px}.backup-list-ltr,.entry-name,.backup-readme{direction:ltr;text-align:left}.backup-readme{white-space:pre-wrap;max-height:420px;overflow:auto;background:#0f172a;color:#e5e7eb;border-radius:12px;padding:16px}.backup-entries-card{padding:0;overflow:hidden}.backup-list-header,.compact-header{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:18px 20px}.backup-table-wrap{overflow-x:auto}.backup-table{min-width:720px}.entry-name{font-weight:600}.status-badge{display:inline-flex;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}.status-badge.ok{background:rgba(34,197,94,.15);color:#22c55e}.status-badge.missing{background:rgba(239,68,68,.15);color:#ef4444}.database-tables-list{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.database-tables-list code{padding:5px 8px;border-radius:8px;background:rgba(148,163,184,.15)}@media(max-width:700px){.backup-title-row,.backup-list-header,.compact-header{flex-direction:column;align-items:stretch}}
</style>
@endsection

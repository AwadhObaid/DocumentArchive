@extends('layouts.app')

@section('title', 'استعادة نسخة احتياطية')

@section('content')
<div class="page-header backup-polish-v1">
    <div>
        <h1>استعادة نسخة احتياطية</h1>
        <p>تتم الاستعادة بعد إنشاء نسخة أمان تلقائية، مع الحفاظ على المستخدمين وكلمات المرور في الاستعادة عبر الواجهة.</p>
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
    الاستعادة ستستبدل البيانات أو مجلدات الملفات الموجودة داخل النسخة حسب الخيار المحدد.
</div>

<div class="card restore-summary">
    <h3 dir="ltr">{{ $fileName }}</h3>
    <div class="restore-stats-grid">
        <div class="stat-box"><span>نوع النسخة</span><strong>{{ $inferredType }}</strong></div>
        <div class="stat-box"><span>حالة التحقق</span><strong>{{ $integrityStatus }}</strong></div>
        <div class="stat-box"><span>حجم النسخة</span><strong>{{ $fileSize }}</strong></div>
        <div class="stat-box"><span>ملفات SQL</span><strong>{{ count($sqlFiles) }}</strong></div>
        <div class="stat-box"><span>جداول قاعدة البيانات</span><strong>{{ $databaseTableCount }}</strong></div>
        <div class="stat-box"><span>ملفات المرفقات</span><strong>{{ $attachmentFilesCount }}</strong></div>
        <div class="stat-box"><span>Manifest</span><strong>{{ $manifestPresent ? 'موجود' : 'غير موجود' }}</strong></div>
    </div>
</div>

@if(count($criticalWarnings) > 0)
    <div class="alert alert-danger mt-3">
        <strong>النسخة غير مؤهلة للاستعادة الكاملة:</strong>
        <ul class="mb-0">
            @foreach($criticalWarnings as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(count($warnings) > 0)
    <div class="alert alert-warning mt-3">
        <strong>ملاحظات:</strong>
        <ul class="mb-0">
            @foreach($warnings as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card mt-4 restore-directory-card">
    <h3>مجلدات المرفقات الموجودة في النسخة</h3>
    <div class="directory-grid">
        @foreach($attachmentDirectoryStats as $directory)
            <div class="directory-item {{ $directory['present'] ? 'present' : 'missing' }}">
                <strong dir="ltr">{{ $directory['directory'] }}</strong>
                <span>{{ $directory['present'] ? $directory['file_count'] . ' ملف' : 'غير موجود' }}</span>
            </div>
        @endforeach
    </div>
</div>

<div class="restore-grid mt-4">
    <div class="card restore-card {{ count($sqlFiles) === 0 ? 'disabled-card' : '' }}">
        <h3>استعادة قاعدة البيانات</h3>
        <p>تستعيد جميع جداول بيانات النظام الموجودة في النسخة، مع حماية المستخدمين وكلمات المرور وجداول التشغيل.</p>

        @if(count($sqlFiles) > 0)
            @if(auth()->user()?->hasPermission('backups.restore'))
                <form method="POST" action="{{ route('backups.restore.database', $fileName) }}" data-restore-guard onsubmit="return confirm('تأكيد نهائي: هل تريد استعادة قاعدة البيانات؟')">
                    @csrf
                    @include('backups.restore-confirm-fields')
                    <button type="submit" class="btn btn-danger" disabled data-restore-submit>استعادة قاعدة البيانات</button>
                </form>
            @endif
        @else
            <div class="alert alert-warning">لا يوجد ملف SQL داخل هذه النسخة.</div>
        @endif
    </div>

    <div class="card restore-card {{ !$canRestoreFiles ? 'disabled-card' : '' }}">
        <h3>استعادة ملفات المرفقات</h3>
        <p>تستعيد فقط المجلدات الموجودة داخل ZIP من: Books وdocuments وmemos وcirculars وmisc-books، مع إنشاء نسخة أمان أولًا.</p>

        @if($canRestoreFiles)
            @if(auth()->user()?->hasPermission('backups.restore'))
                <form method="POST" action="{{ route('backups.restore.files', $fileName) }}" data-restore-guard onsubmit="return confirm('تأكيد نهائي: هل تريد استعادة مجلدات المرفقات الموجودة داخل النسخة؟')">
                    @csrf
                    @include('backups.restore-confirm-fields')
                    <button type="submit" class="btn btn-danger" disabled data-restore-submit>استعادة ملفات المرفقات</button>
                </form>
            @endif
        @else
            <div class="alert alert-warning">لا توجد مجلدات مرفقات مدعومة داخل هذه النسخة.</div>
        @endif
    </div>

    <div class="card restore-card {{ !$canRestoreFull ? 'disabled-card' : '' }}">
        <h3>استعادة النسخة الكاملة</h3>
        <p>تتاح فقط للنسخ التي اجتازت التحقق الكامل من Manifest وملف SQL وجميع مجلدات المرفقات.</p>

        @if($canRestoreFull)
            @if(auth()->user()?->hasPermission('backups.restore'))
                <form method="POST" action="{{ route('backups.restore.full', $fileName) }}" data-restore-guard onsubmit="return confirm('تأكيد نهائي: هل تريد استعادة النسخة الكاملة الموثقة؟')">
                    @csrf
                    @include('backups.restore-confirm-fields')
                    <button type="submit" class="btn btn-danger" disabled data-restore-submit>استعادة النسخة الكاملة</button>
                </form>
            @endif
        @else
            <div class="alert alert-danger">الاستعادة الكاملة معطلة لأن النسخة لم تجتز فحص الاكتمال.</div>
        @endif
    </div>
</div>

<style>
.page-actions{display:flex;gap:8px;flex-wrap:wrap}.restore-summary,.restore-directory-card{padding:20px}.restore-summary h3{text-align:left;margin-bottom:16px}.restore-stats-grid,.restore-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}.stat-box{border:1px solid rgba(148,163,184,.25);border-radius:14px;padding:14px;background:rgba(248,250,252,.08)}.stat-box span{display:block;color:#94a3b8;font-size:13px;margin-bottom:8px}.restore-card{padding:18px}.restore-card p{color:#94a3b8;min-height:105px}.restore-confirm-panel{border:1px dashed rgba(239,68,68,.35);border-radius:14px;padding:12px;margin:12px 0;background:rgba(239,68,68,.05)}.restore-check-row{display:flex;gap:8px;align-items:flex-start;margin:0 0 10px;cursor:pointer}.restore-check-row input{margin-top:5px}.restore-confirm-label{display:block;margin-bottom:6px;font-weight:700}.disabled-card{opacity:.72}.directory-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px}.directory-item{padding:12px;border-radius:12px;border:1px solid rgba(148,163,184,.25);display:flex;justify-content:space-between;gap:8px}.directory-item.present{background:rgba(34,197,94,.08)}.directory-item.missing{background:rgba(239,68,68,.06)}
</style>

<script>
document.querySelectorAll('[data-restore-guard]').forEach(function(form){
    const checkbox=form.querySelector('[data-restore-checkbox]');
    const text=form.querySelector('[data-restore-text]');
    const submit=form.querySelector('[data-restore-submit]');
    function update(){
        const ok=checkbox&&checkbox.checked&&text&&text.value.trim()==='استعادة';
        if(submit)submit.disabled=!ok;
    }
    if(checkbox)checkbox.addEventListener('change',update);
    if(text)text.addEventListener('input',update);
    update();
});
</script>
@endsection

@extends('layouts.app')

@section('title', 'النسخ الاحتياطي')

@section('content')
<div class="page-header">
    <div>
        <h1>النسخ الاحتياطي</h1>
        <p>إنشاء نسخ احتياطية م
ن قاعدة البيانات وم
لفات الم
رفقات وحفظها داخل التخزين الخاص.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="grid-cards backup-actions">
    <div class="card">
        <h3>نسخة قاعدة البيانات</h3>
        <p>تصدير جداول النظام
 إلى م
لف SQL داخل ZIP.</p>
        @if(auth()->user()?->hasPermission('backups.create'))
        <form method="POST" action="{{ route('backups.database') }}">
            @csrf
            <button type="submit" class="btn btn-primary">إنشاء نسخة قاعدة البيانات</button>
        </form>
        @endif
    </div>

    <div class="card">
        <h3>نسخة م
لفات الم
رفقات</h3>
        <p>نسخ م
لفات الكتب والم
رفقات م
ن التخزين الخاص.</p>
        @if(auth()->user()?->hasPermission('backups.create'))
        <form method="POST" action="{{ route('backups.files') }}">
            @csrf
            <button type="submit" class="btn btn-secondary">إنشاء نسخة الم
لفات</button>
        </form>
        @endif
    </div>

    <div class="card">
        <h3>نسخة كام
لة</h3>
        <p>قاعدة البيانات + م
لفات الم
رفقات في م
لف ZIP واحد.</p>
        @if(auth()->user()?->hasPermission('backups.create'))
        <form method="POST" action="{{ route('backups.full') }}">
            @csrf
            <button type="submit" class="btn btn-success">إنشاء نسخة كام
لة</button>
        </form>
        @endif
    </div>
</div>

<div class="alert alert-warning backup-note">
    <strong>تنبيه م
هم
:</strong>
    الاستعادة عم
لية حساسة. استخدم
 زر <strong>استعادة</strong> فقط بعد فحص النسخة والتأكد م
ن م
حتوياتها. النظام
 سينشئ نسخة أم
ان تلقائياً قبل أي استعادة.
</div>

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3>م
لفات النسخ الاحتياطية</h3>
        <small>الم
سار: {{ $backupPath }}</small>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>اسم
 الم
لف</th>
                    <th>الحجم
</th>
                    <th>تاريخ الإنشاء</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($backups as $backup)
                    <tr>
                        <td class="backup-file-name">{{ $backup['name'] }}</td>
                        <td>{{ $backup['size'] }}</td>
                        <td>{{ $backup['created_at'] }}</td>
                        <td class="table-actions">
                            @if(auth()->user()?->hasPermission('backups.view'))
                            <a href="{{ route('backups.inspect', $backup['name']) }}" class="btn btn-sm btn-info">فحص</a>
                            @endif
                            @if(auth()->user()?->hasPermission('backups.restore'))
                            <a href="{{ route('backups.restore', $backup['name']) }}" class="btn btn-sm btn-warning">استعادة</a>
                            @endif
                            @if(auth()->user()?->hasPermission('backups.download'))
                            <a href="{{ route('backups.download', $backup['name']) }}" class="btn btn-sm btn-primary">تنزيل</a>
                            @endif
                            @if(auth()->user()?->hasPermission('backups.delete'))
                            <form method="POST" action="{{ route('backups.destroy', $backup['name']) }}" class="d-inline" onsubmit="return confirm('هل تريد حذف م
لف النسخة الاحتياطية؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted">لا توجد نسخ احتياطية حالياً.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<style>
.backup-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
}
.backup-actions .card {
    padding: 18px;
}
.backup-actions h3 {
    margin-bottom: 8px;
}
.backup-actions p {
    color: #64748b;
    min-height: 48px;
}
.backup-note {
    margin-top: 16px;
}
.backup-file-name {
    direction: ltr;
    text-align: left;
    font-weight: 700;
}
.table-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
</style>
@endsection
